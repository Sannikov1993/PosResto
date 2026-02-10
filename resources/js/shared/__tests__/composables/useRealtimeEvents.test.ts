/**
 * useRealtimeEvents Composable Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock getCurrentInstance to simulate component context
vi.mock('vue', async () => {
    const actual = await vi.importActual('vue');
    return {
        ...actual as Record<string, unknown>,
        getCurrentInstance: vi.fn(() => null), // no component context by default
        onBeforeUnmount: vi.fn(),
    };
});

// Track event subscriptions manually
const { mockListeners, mockOn, mockOff, mockOnce } = vi.hoisted(() => {
    const mockListeners = new Map<string, Set<(data: unknown) => void>>();
    const mockOn = vi.fn((event: string, handler: (data: unknown) => void) => {
        if (!mockListeners.has(event)) {
            mockListeners.set(event, new Set());
        }
        mockListeners.get(event)!.add(handler);
        return () => {
            mockListeners.get(event)?.delete(handler);
        };
    });
    const mockOff = vi.fn((event: string, handler: (data: unknown) => void) => {
        mockListeners.get(event)?.delete(handler);
    });
    const mockOnce = vi.fn();
    return { mockListeners, mockOn, mockOff, mockOnce };
});

// Mock realtime store
vi.mock('@/shared/stores/realtime.js', async () => {
    const { ref } = await vi.importActual<typeof import('vue')>('vue');
    return {
        useRealtimeStore: vi.fn(() => ({
            connected: ref(true),
            connecting: ref(false),
            latency: ref(50),
            isReady: ref(true),
            on: mockOn,
            off: mockOff,
            once: mockOnce,
        })),
    };
});

// Mock realtimeConfig debounce
vi.mock('@/shared/config/realtimeConfig.js', () => ({
    debounce: (fn: (...args: unknown[]) => void, ms: number) => {
        const debounced = (...args: unknown[]) => fn(...args);
        debounced.cancel = vi.fn();
        return debounced;
    },
}));

// Mock logger
vi.mock('@/shared/services/logger.js', () => ({
    createLogger: () => ({
        debug: vi.fn(),
        warn: vi.fn(),
        error: vi.fn(),
    }),
}));

import { useRealtimeEvents } from '@/shared/composables/useRealtimeEvents.js';

describe('useRealtimeEvents', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        mockListeners.clear();
    });

    describe('return values', () => {
        it('should return connected, connecting, latency, isReady refs', () => {
            const { connected, connecting, latency, isReady } = useRealtimeEvents();

            expect(connected.value).toBe(true);
            expect(connecting.value).toBe(false);
            expect(latency.value).toBe(50);
            expect(isReady.value).toBe(true);
        });

        it('should return on, off, once, cleanup functions', () => {
            const result = useRealtimeEvents();

            expect(typeof result.on).toBe('function');
            expect(typeof result.off).toBe('function');
            expect(typeof result.once).toBe('function');
            expect(typeof result.cleanup).toBe('function');
        });
    });

    describe('on', () => {
        it('should subscribe to events via store', () => {
            const { on } = useRealtimeEvents();
            const handler = vi.fn();

            on('order.created', handler);

            expect(mockOn).toHaveBeenCalledWith('order.created', handler);
        });

        it('should return an unsubscribe function', () => {
            const { on } = useRealtimeEvents();
            const handler = vi.fn();

            const unsub = on('order.created', handler);

            expect(typeof unsub).toBe('function');
        });

        it('should unsubscribe when calling returned function', () => {
            const { on } = useRealtimeEvents();
            const handler = vi.fn();

            const unsub = on('order.created', handler);
            unsub();

            // The handler should be removed from listeners
            expect(mockListeners.get('order.created')?.has(handler)).toBeFalsy();
        });

        it('should wrap handler with debounce when option provided', () => {
            const { on } = useRealtimeEvents();
            const handler = vi.fn();

            on('order.updated', handler, { debounce: 300 });

            // mockOn should be called with a wrapped handler (not the original)
            expect(mockOn).toHaveBeenCalled();
        });
    });

    describe('off', () => {
        it('should call store.off with event and handler', () => {
            const { off } = useRealtimeEvents();
            const handler = vi.fn();

            off('order.created', handler);

            expect(mockOff).toHaveBeenCalledWith('order.created', handler);
        });
    });

    describe('once', () => {
        it('should call store.once', () => {
            const { once } = useRealtimeEvents();
            const handler = vi.fn();

            once('shift.opened', handler);

            expect(mockOnce).toHaveBeenCalledWith('shift.opened', handler);
        });
    });

    describe('cleanup', () => {
        it('should unsubscribe all registered handlers', () => {
            const { on, cleanup } = useRealtimeEvents();

            const handler1 = vi.fn();
            const handler2 = vi.fn();

            on('order.created', handler1);
            on('order.updated', handler2);

            cleanup();

            // After cleanup, all listeners should be removed
            expect(mockListeners.get('order.created')?.has(handler1)).toBeFalsy();
            expect(mockListeners.get('order.updated')?.has(handler2)).toBeFalsy();
        });

        it('should be safe to call multiple times', () => {
            const { on, cleanup } = useRealtimeEvents();

            on('test.event', vi.fn());

            cleanup();
            cleanup(); // Should not throw
        });
    });
});
