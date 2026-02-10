/**
 * RealtimeStore - Centralized Pinia store for real-time communication
 *
 * @module shared/stores/realtime
 */

import { defineStore } from 'pinia';
import { ref, computed, shallowRef } from 'vue';
import { WebSocketManager } from '../services/WebSocketManager.js';
import { EventBus } from '../services/EventBus.js';
import { createLogger } from '../services/logger.js';

const log = createLogger('RealtimeStore');

function generateUUID(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

interface EventLogEntry {
    event: string;
    data: unknown;
    timestamp: number;
    id: string;
}

interface PendingOptimisticEntry {
    snapshot: unknown;
    startedAt: number;
}

interface QueuedMessage {
    queuedAt: number;
    id: string;
    [key: string]: unknown;
}

type EventHandler = (data: unknown) => void;

export const useRealtimeStore = defineStore('realtime', () => {
    const restaurantId = ref<number | null>(null);
    const connected = ref(false);
    const connecting = ref(false);
    const latency = ref(0);
    const reconnectCount = ref(0);

    const messageQueue = ref<QueuedMessage[]>([]);
    const MAX_QUEUE_SIZE = 100;

    const eventLog = shallowRef<EventLogEntry[]>([]);
    const MAX_EVENT_LOG = 500;

    const pendingOptimistic = ref(new Map<string, PendingOptimisticEntry>());

    let wsManager: WebSocketManager | null = null;
    const eventBus = new EventBus();

    const isReady = computed(() => connected.value && restaurantId.value !== null);
    const queueSize = computed(() => messageQueue.value.length);
    const hasPendingChanges = computed(() => pendingOptimistic.value.size > 0);
    const subscriptionCount = computed(() => eventBus.getSubscriptionCount());

    function init(restId: number, options: { channels?: string[] } = {}): void {
        if (!restId) {
            log.warn('No restaurant ID provided');
            return;
        }

        if (wsManager && restaurantId.value === restId) {
            log.debug('Already initialized for restaurant', restId);
            return;
        }

        if (wsManager) {
            wsManager.disconnect();
            wsManager = null;
        }

        restaurantId.value = restId;
        connecting.value = true;

        const defaultChannels = ['orders', 'kitchen', 'delivery', 'tables', 'reservations', 'bar', 'cash', 'global'];

        wsManager = new WebSocketManager({
            restaurantId: restId,
            channels: options.channels || defaultChannels,
            onConnected: handleConnected,
            onDisconnected: handleDisconnected,
            onMessage: handleMessage,
            onLatency: (ms: number) => { latency.value = ms; },
            onReconnect: () => { reconnectCount.value++; },
        });

        wsManager.connect();
    }

    function destroy(): void {
        if (wsManager) {
            wsManager.disconnect();
            wsManager = null;
        }
        restaurantId.value = null;
        connected.value = false;
        connecting.value = false;
        eventBus.clear();
        messageQueue.value = [];
        pendingOptimistic.value = new Map();
    }

    function handleConnected(): void {
        connected.value = true;
        connecting.value = false;
        flushQueue();
        eventBus.emit('connection:established', { restaurantId: restaurantId.value });
        log.debug('Connected');
    }

    function handleDisconnected(): void {
        connected.value = false;
        eventBus.emit('connection:lost', { restaurantId: restaurantId.value });
        log.debug('Disconnected');
    }

    function handleMessage(event: string, data: unknown): void {
        logEvent(event, data);
        eventBus.emit(event, data);
        eventBus.emit('*', { event, data });
    }

    function on(event: string, handler: EventHandler): () => void {
        eventBus.on(event, handler);
        return () => off(event, handler);
    }

    function off(event: string, handler: EventHandler): void {
        eventBus.off(event, handler);
    }

    function once(event: string, handler: EventHandler): void {
        const wrappedHandler = (data: unknown) => {
            handler(data);
            off(event, wrappedHandler);
        };
        on(event, wrappedHandler);
    }

    function queueMessage(message: Record<string, any>): void {
        if (messageQueue.value.length >= MAX_QUEUE_SIZE) {
            messageQueue.value.shift();
        }
        messageQueue.value.push({
            ...message,
            queuedAt: Date.now(),
            id: generateUUID(),
        });
    }

    function flushQueue(): void {
        if (!connected.value || messageQueue.value.length === 0) return;

        const messages = [...messageQueue.value];
        messageQueue.value = [];

        messages.forEach((msg: any) => {
            wsManager?.send(msg);
        });

        if (messages.length > 0) {
            log.debug(`Flushed ${messages.length} queued messages`);
        }
    }

    function startOptimistic(id: string, snapshot: unknown): void {
        pendingOptimistic.value.set(id, { snapshot, startedAt: Date.now() });
    }

    function commitOptimistic(id: string): void {
        pendingOptimistic.value.delete(id);
    }

    function rollbackOptimistic(id: string): unknown {
        const pending = pendingOptimistic.value.get(id);
        pendingOptimistic.value.delete(id);
        return pending?.snapshot;
    }

    function logEvent(event: string, data: unknown): void {
        const entry: EventLogEntry = {
            event,
            data,
            timestamp: Date.now(),
            id: generateUUID(),
        };
        eventLog.value = [entry, ...eventLog.value.slice(0, MAX_EVENT_LOG - 1)];
    }

    function getEventLog(filter: { event?: string; since?: number; limit?: number } = {}): EventLogEntry[] {
        let result = eventLog.value;

        if (filter.event) {
            result = result.filter((e: any) => e.event === filter.event);
        }
        if (filter.since) {
            result = result.filter((e: any) => e.timestamp >= filter.since!);
        }
        if (filter.limit && filter.limit > 0) {
            result = result.slice(0, filter.limit);
        }

        return result;
    }

    function clearEventLog(): void {
        eventLog.value = [];
    }

    function getDebugInfo() {
        return {
            restaurantId: restaurantId.value,
            connected: connected.value,
            connecting: connecting.value,
            latency: latency.value,
            reconnectCount: reconnectCount.value,
            queueSize: messageQueue.value.length,
            pendingOptimisticCount: pendingOptimistic.value.size,
            subscriptions: eventBus.getSubscriptions(),
            eventLogSize: eventLog.value.length,
        };
    }

    function reconnect(): void {
        if (wsManager) {
            wsManager.reconnect();
        } else if (restaurantId.value) {
            init(restaurantId.value);
        }
    }

    return {
        restaurantId,
        connected,
        connecting,
        latency,
        reconnectCount,
        isReady,
        queueSize,
        hasPendingChanges,
        subscriptionCount,
        init,
        destroy,
        reconnect,
        on,
        off,
        once,
        queueMessage,
        flushQueue,
        startOptimistic,
        commitOptimistic,
        rollbackOptimistic,
        getEventLog,
        clearEventLog,
        getDebugInfo,
    };
});
