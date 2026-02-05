/**
 * Centralized Real-time Configuration
 *
 * Single source of truth for all real-time connection settings.
 * Used by all Reverb composables across apps.
 *
 * @module shared/config/realtimeConfig
 */

/**
 * Retry configuration with exponential backoff
 */
export const RETRY_CONFIG = {
    maxRetries: 10,
    initialDelay: 1000,    // 1 second
    maxDelay: 30000,       // 30 seconds
    multiplier: 1.5,       // exponential factor
    jitterPercent: 0.2,    // ±20% randomization
};

/**
 * Debounce configuration for event handlers
 */
export const DEBOUNCE_CONFIG = {
    // API refresh debounce (prevents rapid successive calls)
    apiRefresh: 300,       // 300ms - wait before refreshing data

    // Sound notification debounce (prevents sound spam)
    sound: 500,            // 500ms - minimum time between same sounds

    // Toast notification debounce
    toast: 1000,           // 1s - minimum time between same toasts
};

/**
 * Connection health monitoring
 */
export const HEALTH_CONFIG = {
    // How often to check connection health
    checkInterval: 60000,  // 1 minute

    // Consider connection stale after this time without events
    // Set to 0 to disable automatic stale reconnection
    // (rely on WebSocket disconnect events instead)
    staleThreshold: 0, // Disabled - restaurants can have long quiet periods

    // Enable verbose logging in development
    debugMode: false, // Set to true for debugging
};

/**
 * Event types per channel (synced with backend BroadcastsEvents.php)
 */
export const EVENT_TYPES = {
    orders: [
        'new_order',
        'order_status',
        'order_paid',
        'order_cancelled',
        'order_updated',
        'order_transferred',
        'cancellation_requested',
        'item_cancellation_requested',
    ],
    kitchen: [
        'kitchen_new',
        'kitchen_ready',
        'item_cancelled',
    ],
    delivery: [
        'delivery_new',
        'delivery_status',
        'courier_assigned',
        'delivery_problem_created',
        'delivery_problem_resolved',
    ],
    tables: [
        'table_status',
    ],
    reservations: [
        'reservation_new',
        'reservation_confirmed',
        'reservation_cancelled',
        'reservation_seated',
        'deposit_paid',
        'deposit_refunded',
        'prepayment_received',
    ],
    bar: [
        'bar_order_created',
        'bar_order_updated',
        'bar_order_completed',
    ],
    cash: [
        'cash_operation_created',
        'shift_opened',
        'shift_closed',
    ],
    global: [
        'stop_list_changed',
        'settings_changed',
    ],
};

/**
 * Calculate retry delay with exponential backoff and jitter
 * @param {number} retryCount - Current retry attempt (0-based)
 * @returns {number} Delay in milliseconds
 */
export function getRetryDelay(retryCount) {
    const delay = Math.min(
        RETRY_CONFIG.initialDelay * Math.pow(RETRY_CONFIG.multiplier, retryCount),
        RETRY_CONFIG.maxDelay
    );
    // Add jitter
    const jitter = 1 - RETRY_CONFIG.jitterPercent + Math.random() * RETRY_CONFIG.jitterPercent * 2;
    return Math.round(delay * jitter);
}

/**
 * Create a debounced function
 * @param {Function} fn - Function to debounce
 * @param {number} delay - Debounce delay in ms
 * @returns {Function} Debounced function
 */
export function debounce(fn, delay) {
    let timeoutId = null;

    const debounced = (...args) => {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
        timeoutId = setTimeout(() => {
            timeoutId = null;
            fn(...args);
        }, delay);
    };

    debounced.cancel = () => {
        if (timeoutId) {
            clearTimeout(timeoutId);
            timeoutId = null;
        }
    };

    return debounced;
}

/**
 * Create a throttled function (first call executes immediately)
 * @param {Function} fn - Function to throttle
 * @param {number} delay - Throttle delay in ms
 * @returns {Function} Throttled function
 */
export function throttle(fn, delay) {
    let lastCall = 0;
    let timeoutId = null;

    return (...args) => {
        const now = Date.now();
        const remaining = delay - (now - lastCall);

        if (remaining <= 0) {
            if (timeoutId) {
                clearTimeout(timeoutId);
                timeoutId = null;
            }
            lastCall = now;
            fn(...args);
        } else if (!timeoutId) {
            timeoutId = setTimeout(() => {
                lastCall = Date.now();
                timeoutId = null;
                fn(...args);
            }, remaining);
        }
    };
}

export default {
    RETRY_CONFIG,
    DEBOUNCE_CONFIG,
    HEALTH_CONFIG,
    EVENT_TYPES,
    getRetryDelay,
    debounce,
    throttle,
};
