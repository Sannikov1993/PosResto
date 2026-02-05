/**
 * useRealtimeEvents - Composable for components to subscribe to real-time events
 *
 * Provides a clean API for components to subscribe to events without
 * managing WebSocket connections directly. All subscriptions are automatically
 * cleaned up on component unmount.
 *
 * @module shared/composables/useRealtimeEvents
 *
 * @example
 * const { on, connected } = useRealtimeEvents();
 *
 * on('new_order', (data) => {
 *     console.log('New order:', data);
 * });
 *
 * on('order_status', (data) => {
 *     if (data.order_id === currentOrderId) {
 *         refreshOrder();
 *     }
 * });
 */

import { onBeforeUnmount, getCurrentInstance, toRef } from 'vue';
import { storeToRefs } from 'pinia';
import { useRealtimeStore } from '../stores/realtime.js';
import { debounce } from '../config/realtimeConfig.js';

/**
 * @typedef {Object} UseRealtimeEventsReturn
 * @property {import('vue').Ref<boolean>} connected - Connection status
 * @property {import('vue').Ref<boolean>} connecting - Connecting in progress
 * @property {import('vue').Ref<number>} latency - Connection latency in ms
 * @property {import('vue').ComputedRef<boolean>} isReady - Connection ready
 * @property {Function} on - Subscribe to event
 * @property {Function} off - Unsubscribe from event
 * @property {Function} once - Subscribe for one occurrence
 */

/**
 * Composable for subscribing to real-time events
 * @returns {UseRealtimeEventsReturn}
 */
export function useRealtimeEvents() {
    const store = useRealtimeStore();
    const { connected, connecting, latency, isReady } = storeToRefs(store);

    /** @type {Array<Function>} */
    const unsubscribers = [];

    /** @type {Map<Function, Function>} */
    const debouncedHandlers = new Map();

    // Track component instance for auto-cleanup
    const instance = getCurrentInstance();

    /**
     * Subscribe to an event
     * @param {string} event - Event name or '*' for all events
     * @param {Function} handler - Event handler
     * @param {Object} options - Options
     * @param {number} options.debounce - Debounce delay in ms
     * @returns {Function} Unsubscribe function
     */
    function on(event, handler, options = {}) {
        let wrappedHandler = handler;

        // Apply debounce if specified
        if (options.debounce && options.debounce > 0) {
            wrappedHandler = debounce(handler, options.debounce);
            debouncedHandlers.set(handler, wrappedHandler);
        }

        const unsub = store.on(event, wrappedHandler);

        // Create cleanup function that also cancels debounce
        const cleanup = () => {
            unsub();
            const debounced = debouncedHandlers.get(handler);
            if (debounced?.cancel) {
                debounced.cancel();
            }
            debouncedHandlers.delete(handler);
        };

        unsubscribers.push(cleanup);
        return cleanup;
    }

    /**
     * Unsubscribe from an event
     * @param {string} event - Event name
     * @param {Function} handler - Handler to remove
     */
    function off(event, handler) {
        // Get the wrapped handler if it was debounced
        const wrappedHandler = debouncedHandlers.get(handler) || handler;

        // Cancel debounce timer if exists
        if (wrappedHandler.cancel) {
            wrappedHandler.cancel();
        }
        debouncedHandlers.delete(handler);

        store.off(event, wrappedHandler);
    }

    /**
     * Subscribe to an event for one occurrence only
     * @param {string} event - Event name
     * @param {Function} handler - Event handler
     */
    function once(event, handler) {
        store.once(event, handler);
    }

    /**
     * Cleanup all subscriptions for this component
     */
    function cleanup() {
        unsubscribers.forEach(unsub => {
            try {
                unsub();
            } catch (e) {
                // Ignore cleanup errors
            }
        });
        unsubscribers.length = 0;
        debouncedHandlers.clear();
    }

    // Auto-cleanup on component unmount
    if (instance) {
        onBeforeUnmount(() => {
            cleanup();
        });
    }

    return {
        // State (readonly refs)
        connected,
        connecting,
        latency,
        isReady,

        // Methods
        on,
        off,
        once,
        cleanup,
    };
}
