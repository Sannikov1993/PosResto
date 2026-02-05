/**
 * EventEmitter - Lightweight event system for session management
 *
 * Provides a pub/sub mechanism for session-related events.
 * Follows the Observer pattern with support for:
 * - Multiple listeners per event
 * - One-time listeners
 * - Listener removal
 * - Event history for debugging
 *
 * @module services/session/EventEmitter
 */

/**
 * Maximum number of events to keep in history
 */
const MAX_HISTORY_SIZE = 100;

/**
 * EventEmitter class for handling session events
 */
export class EventEmitter {
    /**
     * Creates a new EventEmitter instance
     * @param {Object} options - Configuration options
     * @param {boolean} options.debug - Enable debug logging
     * @param {number} options.maxHistorySize - Maximum events to keep in history
     */
    constructor(options = {}) {
        this._listeners = new Map();
        this._onceListeners = new Map();
        this._history = [];
        this._debug = options.debug || false;
        this._maxHistorySize = options.maxHistorySize || MAX_HISTORY_SIZE;
        this._paused = false;
        this._queuedEvents = [];
    }

    /**
     * Subscribe to an event
     * @param {string} event - Event name
     * @param {Function} callback - Callback function
     * @returns {Function} Unsubscribe function
     */
    on(event, callback) {
        if (typeof callback !== 'function') {
            throw new TypeError('Callback must be a function');
        }

        if (!this._listeners.has(event)) {
            this._listeners.set(event, new Set());
        }

        this._listeners.get(event).add(callback);

        if (this._debug) {
            console.debug(`[EventEmitter] Subscribed to "${event}". Total listeners: ${this._listeners.get(event).size}`);
        }

        // Return unsubscribe function
        return () => this.off(event, callback);
    }

    /**
     * Subscribe to an event (fires only once)
     * @param {string} event - Event name
     * @param {Function} callback - Callback function
     * @returns {Function} Unsubscribe function
     */
    once(event, callback) {
        if (typeof callback !== 'function') {
            throw new TypeError('Callback must be a function');
        }

        if (!this._onceListeners.has(event)) {
            this._onceListeners.set(event, new Set());
        }

        this._onceListeners.get(event).add(callback);

        return () => {
            const listeners = this._onceListeners.get(event);
            if (listeners) {
                listeners.delete(callback);
            }
        };
    }

    /**
     * Unsubscribe from an event
     * @param {string} event - Event name
     * @param {Function} callback - Callback function to remove
     * @returns {boolean} Whether the listener was removed
     */
    off(event, callback) {
        const listeners = this._listeners.get(event);
        if (!listeners) {
            return false;
        }

        const removed = listeners.delete(callback);

        if (listeners.size === 0) {
            this._listeners.delete(event);
        }

        if (this._debug && removed) {
            console.debug(`[EventEmitter] Unsubscribed from "${event}"`);
        }

        return removed;
    }

    /**
     * Remove all listeners for an event or all events
     * @param {string} [event] - Event name (optional, removes all if not provided)
     */
    removeAllListeners(event) {
        if (event) {
            this._listeners.delete(event);
            this._onceListeners.delete(event);

            if (this._debug) {
                console.debug(`[EventEmitter] Removed all listeners for "${event}"`);
            }
        } else {
            this._listeners.clear();
            this._onceListeners.clear();

            if (this._debug) {
                console.debug('[EventEmitter] Removed all listeners');
            }
        }
    }

    /**
     * Emit an event
     * @param {string} event - Event name
     * @param {*} data - Event data
     * @returns {boolean} Whether any listeners were called
     */
    emit(event, data) {
        // If paused, queue the event
        if (this._paused) {
            this._queuedEvents.push({ event, data, timestamp: Date.now() });
            return false;
        }

        return this._emitInternal(event, data);
    }

    /**
     * Internal emit implementation
     * @private
     */
    _emitInternal(event, data) {
        const timestamp = Date.now();

        // Add to history
        this._addToHistory(event, data, timestamp);

        let called = false;

        // Call regular listeners
        const listeners = this._listeners.get(event);
        if (listeners && listeners.size > 0) {
            listeners.forEach(callback => {
                try {
                    callback(data, { event, timestamp });
                    called = true;
                } catch (error) {
                    console.error(`[EventEmitter] Error in listener for "${event}":`, error);
                }
            });
        }

        // Call and remove once listeners
        const onceListeners = this._onceListeners.get(event);
        if (onceListeners && onceListeners.size > 0) {
            onceListeners.forEach(callback => {
                try {
                    callback(data, { event, timestamp });
                    called = true;
                } catch (error) {
                    console.error(`[EventEmitter] Error in once listener for "${event}":`, error);
                }
            });
            this._onceListeners.delete(event);
        }

        if (this._debug) {
            console.debug(`[EventEmitter] Emitted "${event}". Listeners called: ${called}`);
        }

        return called;
    }

    /**
     * Add event to history
     * @private
     */
    _addToHistory(event, data, timestamp) {
        this._history.push({
            event,
            data: this._sanitizeForHistory(data),
            timestamp,
        });

        // Trim history if needed
        while (this._history.length > this._maxHistorySize) {
            this._history.shift();
        }
    }

    /**
     * Sanitize data for history (remove sensitive information)
     * @private
     */
    _sanitizeForHistory(data) {
        if (!data || typeof data !== 'object') {
            return data;
        }

        const sanitized = { ...data };

        // Remove sensitive fields
        const sensitiveFields = ['token', 'password', 'pin', 'pin_code', 'secret'];
        sensitiveFields.forEach(field => {
            if (field in sanitized) {
                sanitized[field] = '[REDACTED]';
            }
        });

        return sanitized;
    }

    /**
     * Pause event emission (events will be queued)
     */
    pause() {
        this._paused = true;

        if (this._debug) {
            console.debug('[EventEmitter] Paused');
        }
    }

    /**
     * Resume event emission and flush queued events
     */
    resume() {
        this._paused = false;

        // Flush queued events
        const queued = [...this._queuedEvents];
        this._queuedEvents = [];

        queued.forEach(({ event, data }) => {
            this._emitInternal(event, data);
        });

        if (this._debug) {
            console.debug(`[EventEmitter] Resumed. Flushed ${queued.length} queued events`);
        }
    }

    /**
     * Get event history
     * @param {string} [event] - Filter by event name (optional)
     * @returns {Array} Event history
     */
    getHistory(event) {
        if (event) {
            return this._history.filter(h => h.event === event);
        }
        return [...this._history];
    }

    /**
     * Clear event history
     */
    clearHistory() {
        this._history = [];
    }

    /**
     * Get listener count for an event
     * @param {string} event - Event name
     * @returns {number} Number of listeners
     */
    listenerCount(event) {
        const regular = this._listeners.get(event)?.size || 0;
        const once = this._onceListeners.get(event)?.size || 0;
        return regular + once;
    }

    /**
     * Get all registered event names
     * @returns {string[]} Array of event names
     */
    eventNames() {
        const names = new Set([
            ...this._listeners.keys(),
            ...this._onceListeners.keys(),
        ]);
        return Array.from(names);
    }

    /**
     * Wait for an event to be emitted
     * @param {string} event - Event name
     * @param {number} [timeout] - Timeout in milliseconds
     * @returns {Promise} Resolves with event data
     */
    waitFor(event, timeout) {
        return new Promise((resolve, reject) => {
            let timeoutId;

            const unsubscribe = this.once(event, (data) => {
                if (timeoutId) {
                    clearTimeout(timeoutId);
                }
                resolve(data);
            });

            if (timeout) {
                timeoutId = setTimeout(() => {
                    unsubscribe();
                    reject(new Error(`Timeout waiting for event "${event}"`));
                }, timeout);
            }
        });
    }

    /**
     * Create a namespaced emitter (all events prefixed)
     * @param {string} namespace - Namespace prefix
     * @returns {Object} Namespaced emitter interface
     */
    namespace(namespace) {
        const prefix = `${namespace}:`;

        return {
            on: (event, callback) => this.on(`${prefix}${event}`, callback),
            once: (event, callback) => this.once(`${prefix}${event}`, callback),
            off: (event, callback) => this.off(`${prefix}${event}`, callback),
            emit: (event, data) => this.emit(`${prefix}${event}`, data),
        };
    }

    /**
     * Destroy the emitter and clean up
     */
    destroy() {
        this.removeAllListeners();
        this._history = [];
        this._queuedEvents = [];

        if (this._debug) {
            console.debug('[EventEmitter] Destroyed');
        }
    }
}

// Export singleton instance for global session events
let globalEmitter = null;

/**
 * Get the global session event emitter
 * @returns {EventEmitter}
 */
export function getSessionEventEmitter() {
    if (!globalEmitter) {
        globalEmitter = new EventEmitter({ debug: false });
    }
    return globalEmitter;
}

export default EventEmitter;
