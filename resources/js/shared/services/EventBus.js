/**
 * EventBus - Typed event emitter for real-time events
 *
 * Centralized event distribution system that allows components
 * to subscribe/unsubscribe to events without direct WebSocket coupling.
 *
 * @module shared/services/EventBus
 */

export class EventBus {
    constructor() {
        /** @type {Map<string, Set<Function>>} */
        this.handlers = new Map();
    }

    /**
     * Subscribe to an event
     * @param {string} event - Event name or '*' for all events
     * @param {Function} handler - Event handler function
     */
    on(event, handler) {
        if (!this.handlers.has(event)) {
            this.handlers.set(event, new Set());
        }
        this.handlers.get(event).add(handler);
    }

    /**
     * Unsubscribe from an event
     * @param {string} event - Event name
     * @param {Function} handler - Handler to remove
     */
    off(event, handler) {
        const handlers = this.handlers.get(event);
        if (handlers) {
            handlers.delete(handler);
            if (handlers.size === 0) {
                this.handlers.delete(event);
            }
        }
    }

    /**
     * Emit an event to all subscribers
     * @param {string} event - Event name
     * @param {*} data - Event data
     */
    emit(event, data) {
        const handlers = this.handlers.get(event);
        if (handlers) {
            handlers.forEach(handler => {
                try {
                    handler(data);
                } catch (err) {
                    console.error(`[EventBus] Error in handler for ${event}:`, err);
                }
            });
        }
    }

    /**
     * Clear all subscriptions
     */
    clear() {
        this.handlers.clear();
    }

    /**
     * Get subscription statistics (for debugging)
     * @returns {Object} Map of event names to subscriber counts
     */
    getSubscriptions() {
        const result = {};
        this.handlers.forEach((handlers, event) => {
            result[event] = handlers.size;
        });
        return result;
    }

    /**
     * Get total number of subscriptions
     * @returns {number}
     */
    getSubscriptionCount() {
        let count = 0;
        this.handlers.forEach(handlers => {
            count += handlers.size;
        });
        return count;
    }

    /**
     * Check if an event has any subscribers
     * @param {string} event - Event name
     * @returns {boolean}
     */
    hasSubscribers(event) {
        const handlers = this.handlers.get(event);
        return handlers ? handlers.size > 0 : false;
    }
}
