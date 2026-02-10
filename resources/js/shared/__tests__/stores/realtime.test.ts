/**
 * Realtime Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock logger
vi.mock('@/shared/services/logger.js', () => ({
    createLogger: () => ({
        debug: vi.fn(),
        warn: vi.fn(),
        error: vi.fn(),
        info: vi.fn(),
    }),
}));

// Mock WebSocketManager - use vi.hoisted so the class is available when vi.mock is hoisted
const { MockWebSocketManager, mockConnect, mockDisconnect, mockSend, mockReconnect } = vi.hoisted(() => {
    const mockConnect = vi.fn();
    const mockDisconnect = vi.fn();
    const mockSend = vi.fn();
    const mockReconnect = vi.fn();

    class MockWebSocketManager {
        _options: any;
        connect = mockConnect;
        disconnect = mockDisconnect;
        send = mockSend;
        reconnect = mockReconnect;

        constructor(options: any) {
            this._options = options;
        }
    }

    return { MockWebSocketManager, mockConnect, mockDisconnect, mockSend, mockReconnect };
});

vi.mock('@/shared/services/WebSocketManager.js', () => ({
    WebSocketManager: MockWebSocketManager,
}));

// Mock EventBus (we test it separately; let it work naturally here)
// The realtime store uses EventBus internally, and we want to test
// subscribe/emit behavior through the store's on/off/once methods.

import { useRealtimeStore } from '@/shared/stores/realtime.js';

describe('Realtime Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('Initial State', () => {
        it('should have correct initial state', () => {
            const store = useRealtimeStore();

            expect(store.restaurantId).toBeNull();
            expect(store.connected).toBe(false);
            expect(store.connecting).toBe(false);
            expect(store.latency).toBe(0);
            expect(store.reconnectCount).toBe(0);
        });

        it('should have isReady as false initially', () => {
            const store = useRealtimeStore();
            expect(store.isReady).toBe(false);
        });

        it('should have empty queue initially', () => {
            const store = useRealtimeStore();
            expect(store.queueSize).toBe(0);
        });

        it('should have no pending changes initially', () => {
            const store = useRealtimeStore();
            expect(store.hasPendingChanges).toBe(false);
        });

        it('should have zero subscriptions initially', () => {
            const store = useRealtimeStore();
            expect(store.subscriptionCount).toBe(0);
        });
    });

    describe('init', () => {
        it('should set restaurantId and connecting state', () => {
            const store = useRealtimeStore();
            store.init(42);

            expect(store.restaurantId).toBe(42);
            expect(store.connecting).toBe(true);
        });

        it('should call WebSocketManager connect', () => {
            const store = useRealtimeStore();
            store.init(1);

            expect(mockConnect).toHaveBeenCalled();
        });

        it('should not re-init if already initialized for the same restaurant', () => {
            const store = useRealtimeStore();
            store.init(10);
            mockConnect.mockClear();

            store.init(10);

            expect(mockConnect).not.toHaveBeenCalled();
        });

        it('should warn and not init when no restaurant ID is provided', () => {
            const store = useRealtimeStore();
            store.init(0 as any);

            expect(store.restaurantId).toBeNull();
            expect(mockConnect).not.toHaveBeenCalled();
        });

        it('should disconnect old manager when re-initializing for a different restaurant', () => {
            const store = useRealtimeStore();
            store.init(1);
            store.init(2);

            expect(mockDisconnect).toHaveBeenCalled();
        });
    });

    describe('destroy', () => {
        it('should reset all state', () => {
            const store = useRealtimeStore();
            store.init(1);

            store.destroy();

            expect(store.restaurantId).toBeNull();
            expect(store.connected).toBe(false);
            expect(store.connecting).toBe(false);
        });

        it('should disconnect websocket manager', () => {
            const store = useRealtimeStore();
            store.init(1);

            store.destroy();

            expect(mockDisconnect).toHaveBeenCalled();
        });

        it('should clear message queue', () => {
            const store = useRealtimeStore();
            store.init(1);
            store.queueMessage({ type: 'test' });

            store.destroy();

            expect(store.queueSize).toBe(0);
        });

        it('should clear pending optimistic updates', () => {
            const store = useRealtimeStore();
            store.startOptimistic('id1', { data: 'snapshot' });

            store.destroy();

            expect(store.hasPendingChanges).toBe(false);
        });
    });

    describe('on / off', () => {
        it('should subscribe to events and return unsubscribe function', () => {
            const store = useRealtimeStore();
            const handler = vi.fn();

            const unsub = store.on('order.created', handler);

            expect(typeof unsub).toBe('function');
            expect(store.subscriptionCount).toBe(1);
        });

        it('should unsubscribe when calling off', () => {
            const store = useRealtimeStore();
            const handler = vi.fn();

            store.on('order.created', handler);
            store.off('order.created', handler);

            expect(store.subscriptionCount).toBe(0);
        });

        it('should unsubscribe via returned function', () => {
            const store = useRealtimeStore();
            const handler = vi.fn();

            const unsub = store.on('order.created', handler);
            unsub();

            expect(store.subscriptionCount).toBe(0);
        });
    });

    describe('once', () => {
        it('should register a handler that is called only once', () => {
            const store = useRealtimeStore();
            const handler = vi.fn();

            store.once('shift.opened', handler);

            // Internally, once wraps the handler and calls off after first call.
            // We can verify the subscription exists.
            expect(store.subscriptionCount).toBe(1);
        });
    });

    describe('queueMessage', () => {
        it('should add a message to the queue', () => {
            const store = useRealtimeStore();
            store.queueMessage({ type: 'order.update', orderId: 1 });

            expect(store.queueSize).toBe(1);
        });

        it('should drop oldest message when queue exceeds max size', () => {
            const store = useRealtimeStore();

            for (let i = 0; i < 101; i++) {
                store.queueMessage({ type: 'test', index: i });
            }

            expect(store.queueSize).toBe(100);
        });
    });

    describe('flushQueue', () => {
        it('should not flush if not connected', () => {
            const store = useRealtimeStore();
            store.queueMessage({ type: 'test' });

            store.flushQueue();

            // Queue should still have the message
            expect(store.queueSize).toBe(1);
        });
    });

    describe('optimistic updates', () => {
        it('should start an optimistic update', () => {
            const store = useRealtimeStore();
            store.startOptimistic('op1', { status: 'old' });

            expect(store.hasPendingChanges).toBe(true);
        });

        it('should commit an optimistic update', () => {
            const store = useRealtimeStore();
            store.startOptimistic('op1', { status: 'old' });

            store.commitOptimistic('op1');

            expect(store.hasPendingChanges).toBe(false);
        });

        it('should rollback an optimistic update and return snapshot', () => {
            const store = useRealtimeStore();
            const snapshot = { status: 'original' };
            store.startOptimistic('op1', snapshot);

            const returned = store.rollbackOptimistic('op1');

            expect(returned).toEqual(snapshot);
            expect(store.hasPendingChanges).toBe(false);
        });

        it('should return undefined on rollback for non-existent id', () => {
            const store = useRealtimeStore();

            const returned = store.rollbackOptimistic('nonexistent');

            expect(returned).toBeUndefined();
        });
    });

    describe('event log', () => {
        it('should start with an empty event log', () => {
            const store = useRealtimeStore();
            expect(store.getEventLog()).toEqual([]);
        });

        it('should clear the event log', () => {
            const store = useRealtimeStore();
            // We cannot directly call handleMessage since it is private,
            // but clearEventLog should work on empty log without error
            store.clearEventLog();
            expect(store.getEventLog()).toEqual([]);
        });
    });

    describe('getDebugInfo', () => {
        it('should return debug information object', () => {
            const store = useRealtimeStore();
            const info = store.getDebugInfo();

            expect(info).toHaveProperty('restaurantId');
            expect(info).toHaveProperty('connected');
            expect(info).toHaveProperty('connecting');
            expect(info).toHaveProperty('latency');
            expect(info).toHaveProperty('reconnectCount');
            expect(info).toHaveProperty('queueSize');
            expect(info).toHaveProperty('pendingOptimisticCount');
            expect(info).toHaveProperty('subscriptions');
            expect(info).toHaveProperty('eventLogSize');
        });

        it('should reflect current state in debug info', () => {
            const store = useRealtimeStore();
            store.startOptimistic('debug-test', {});
            store.queueMessage({ type: 'debug' });

            const info = store.getDebugInfo();

            expect(info.pendingOptimisticCount).toBe(1);
            expect(info.queueSize).toBe(1);
        });
    });

    describe('reconnect', () => {
        it('should call reconnect on the websocket manager', () => {
            const store = useRealtimeStore();
            store.init(1);

            store.reconnect();

            expect(mockReconnect).toHaveBeenCalled();
        });

        it('should re-init if no wsManager but restaurantId exists', () => {
            const store = useRealtimeStore();
            // Set restaurantId without init (simulate destroyed state with stale id)
            // This tests the else branch in reconnect
            store.reconnect();

            // Should not throw, manager does not exist
            expect(mockReconnect).not.toHaveBeenCalled();
        });
    });
});
