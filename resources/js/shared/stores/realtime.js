/**
 * RealtimeStore - Centralized Pinia store for real-time communication
 *
 * Enterprise-level real-time architecture with:
 * - Single WebSocket connection per application
 * - Centralized event bus
 * - Message queue for offline support
 * - Optimistic updates with rollback
 * - Event logging for debugging/audit
 *
 * @module shared/stores/realtime
 */

import { defineStore } from 'pinia';
import { ref, computed, shallowRef } from 'vue';
import { WebSocketManager } from '../services/WebSocketManager.js';
import { EventBus } from '../services/EventBus.js';
import { createLogger } from '../services/logger.js';

const log = createLogger('RealtimeStore');

/**
 * Generate a UUID with fallback for browsers without crypto.randomUUID
 * @returns {string} UUID string
 */
function generateUUID() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    // Fallback for older browsers
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

export const useRealtimeStore = defineStore('realtime', () => {
    // ═══════════════════════════════════════════════════════════════
    // STATE
    // ═══════════════════════════════════════════════════════════════

    const restaurantId = ref(null);
    const connected = ref(false);
    const connecting = ref(false);
    const latency = ref(0);
    const reconnectCount = ref(0);

    // Message queue for offline support
    const messageQueue = ref([]);
    const MAX_QUEUE_SIZE = 100;

    // Event log for debugging/audit (last 500 events)
    const eventLog = shallowRef([]);
    const MAX_EVENT_LOG = 500;

    // Optimistic updates tracking
    const pendingOptimistic = ref(new Map());

    // Internal (not exposed)
    let wsManager = null;
    const eventBus = new EventBus();

    // ═══════════════════════════════════════════════════════════════
    // GETTERS
    // ═══════════════════════════════════════════════════════════════

    const isReady = computed(() => connected.value && restaurantId.value !== null);
    const queueSize = computed(() => messageQueue.value.length);
    const hasPendingChanges = computed(() => pendingOptimistic.value.size > 0);
    const subscriptionCount = computed(() => eventBus.getSubscriptionCount());

    // ═══════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ═══════════════════════════════════════════════════════════════

    /**
     * Initialize real-time connection for a restaurant
     * @param {number} restId - Restaurant ID
     * @param {Object} options - Configuration options
     * @param {string[]} options.channels - Channels to subscribe to
     */
    function init(restId, options = {}) {
        if (!restId) {
            log.warn('No restaurant ID provided');
            return;
        }

        if (wsManager && restaurantId.value === restId) {
            log.debug('Already initialized for restaurant', restId);
            return;
        }

        // Cleanup previous connection
        if (wsManager) {
            wsManager.disconnect();
            wsManager = null;
        }

        restaurantId.value = restId;
        connecting.value = true;

        const defaultChannels = [
            'orders',
            'kitchen',
            'delivery',
            'tables',
            'reservations',
            'bar',
            'cash',
            'global',
        ];

        wsManager = new WebSocketManager({
            restaurantId: restId,
            channels: options.channels || defaultChannels,
            onConnected: handleConnected,
            onDisconnected: handleDisconnected,
            onMessage: handleMessage,
            onLatency: (ms) => { latency.value = ms; },
            onReconnect: () => { reconnectCount.value++; },
        });

        wsManager.connect();
    }

    /**
     * Destroy the real-time connection
     */
    function destroy() {
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

    // ═══════════════════════════════════════════════════════════════
    // CONNECTION HANDLERS
    // ═══════════════════════════════════════════════════════════════

    function handleConnected() {
        connected.value = true;
        connecting.value = false;

        // Flush queued messages
        flushQueue();

        // Emit connection event
        eventBus.emit('connection:established', { restaurantId: restaurantId.value });

        log.debug('Connected');
    }

    function handleDisconnected() {
        connected.value = false;
        eventBus.emit('connection:lost', { restaurantId: restaurantId.value });

        log.debug('Disconnected');
    }

    function handleMessage(event, data) {
        // Log event
        logEvent(event, data);

        // Emit to subscribers
        eventBus.emit(event, data);

        // Also emit wildcard for debugging/monitoring
        eventBus.emit('*', { event, data });
    }

    // ═══════════════════════════════════════════════════════════════
    // EVENT SUBSCRIPTION (used by components via useRealtimeEvents)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Subscribe to an event
     * @param {string} event - Event name or '*' for all
     * @param {Function} handler - Event handler
     * @returns {Function} Unsubscribe function
     */
    function on(event, handler) {
        eventBus.on(event, handler);
        return () => off(event, handler);
    }

    /**
     * Unsubscribe from an event
     * @param {string} event - Event name
     * @param {Function} handler - Handler to remove
     */
    function off(event, handler) {
        eventBus.off(event, handler);
    }

    /**
     * Subscribe to an event for one occurrence only
     * @param {string} event - Event name
     * @param {Function} handler - Event handler
     */
    function once(event, handler) {
        const wrappedHandler = (data) => {
            handler(data);
            off(event, wrappedHandler);
        };
        on(event, wrappedHandler);
    }

    // ═══════════════════════════════════════════════════════════════
    // MESSAGE QUEUE (offline support)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Queue a message for sending when connection is restored
     * @param {Object} message - Message to queue
     */
    function queueMessage(message) {
        if (messageQueue.value.length >= MAX_QUEUE_SIZE) {
            messageQueue.value.shift(); // Remove oldest
        }
        messageQueue.value.push({
            ...message,
            queuedAt: Date.now(),
            id: generateUUID(),
        });
    }

    /**
     * Send all queued messages
     */
    function flushQueue() {
        if (!connected.value || messageQueue.value.length === 0) return;

        const messages = [...messageQueue.value];
        messageQueue.value = [];

        messages.forEach(msg => {
            wsManager?.send(msg);
        });

        if (messages.length > 0) {
            log.debug(`Flushed ${messages.length} queued messages`);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // OPTIMISTIC UPDATES
    // ═══════════════════════════════════════════════════════════════

    /**
     * Start tracking an optimistic update
     * @param {string} id - Unique ID for this update
     * @param {*} snapshot - State snapshot for rollback
     */
    function startOptimistic(id, snapshot) {
        pendingOptimistic.value.set(id, {
            snapshot,
            startedAt: Date.now(),
        });
    }

    /**
     * Commit an optimistic update (server confirmed)
     * @param {string} id - Update ID
     */
    function commitOptimistic(id) {
        pendingOptimistic.value.delete(id);
    }

    /**
     * Rollback an optimistic update (server rejected)
     * @param {string} id - Update ID
     * @returns {*} The saved snapshot
     */
    function rollbackOptimistic(id) {
        const pending = pendingOptimistic.value.get(id);
        pendingOptimistic.value.delete(id);
        return pending?.snapshot;
    }

    // ═══════════════════════════════════════════════════════════════
    // EVENT LOG (for debugging/audit)
    // ═══════════════════════════════════════════════════════════════

    function logEvent(event, data) {
        const entry = {
            event,
            data,
            timestamp: Date.now(),
            id: generateUUID(),
        };

        // Keep only last MAX_EVENT_LOG entries
        eventLog.value = [entry, ...eventLog.value.slice(0, MAX_EVENT_LOG - 1)];
    }

    /**
     * Get event log with optional filtering
     * @param {Object} filter - Filter options
     * @param {string} filter.event - Filter by event name
     * @param {number} filter.since - Filter events after this timestamp
     * @param {number} filter.limit - Limit number of results
     * @returns {Array} Filtered event log
     */
    function getEventLog(filter = {}) {
        let log = eventLog.value;

        if (filter.event) {
            log = log.filter(e => e.event === filter.event);
        }
        if (filter.since) {
            log = log.filter(e => e.timestamp >= filter.since);
        }
        if (filter.limit && filter.limit > 0) {
            log = log.slice(0, filter.limit);
        }

        return log;
    }

    /**
     * Clear the event log
     */
    function clearEventLog() {
        eventLog.value = [];
    }

    // ═══════════════════════════════════════════════════════════════
    // DEBUG UTILITIES
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get current subscription statistics
     * @returns {Object} Subscription info
     */
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

    /**
     * Force reconnection
     */
    function reconnect() {
        if (wsManager) {
            wsManager.reconnect();
        } else if (restaurantId.value) {
            init(restaurantId.value);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // RETURN
    // ═══════════════════════════════════════════════════════════════

    return {
        // State (reactive)
        restaurantId,
        connected,
        connecting,
        latency,
        reconnectCount,

        // Computed
        isReady,
        queueSize,
        hasPendingChanges,
        subscriptionCount,

        // Lifecycle
        init,
        destroy,
        reconnect,

        // Events
        on,
        off,
        once,

        // Queue
        queueMessage,
        flushQueue,

        // Optimistic
        startOptimistic,
        commitOptimistic,
        rollbackOptimistic,

        // Debug
        getEventLog,
        clearEventLog,
        getDebugInfo,
    };
});
