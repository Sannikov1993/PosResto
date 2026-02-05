/**
 * Timing Thresholds Constants
 *
 * Defines timing thresholds for order urgency,
 * overdue warnings, and time slot calculations.
 *
 * @module kitchen/constants/thresholds
 */

/**
 * Overdue order thresholds (in minutes)
 * @readonly
 */
export const OVERDUE_THRESHOLDS = Object.freeze({
    /** Yellow warning threshold */
    WARNING: 10,
    /** Red critical threshold */
    CRITICAL: 15,
    /** Full-screen alert threshold */
    ALERT: 20,
});

/**
 * Time slot configuration for preorder grouping
 * @readonly
 */
export const TIME_SLOT_CONFIG = Object.freeze({
    /** Duration of each time slot in minutes */
    DURATION_MINUTES: 30,
    /** Minutes before slot time to show as "urgent" */
    URGENT_THRESHOLD: 30,
    /** Minutes before slot time to show as "warning" */
    WARNING_THRESHOLD: 60,
});

/**
 * Alert timing configuration
 * @readonly
 */
export const ALERT_CONFIG = Object.freeze({
    /** Minimum interval between overdue alerts (ms) */
    OVERDUE_ALERT_INTERVAL: 30000,
    /** Minimum interval between critical warnings (ms) */
    CRITICAL_WARNING_INTERVAL: 60000,
    /** Auto-dismiss time for new order alert (ms) */
    NEW_ORDER_ALERT_DURATION: 5000,
    /** Auto-dismiss time for overdue alert (ms) */
    OVERDUE_ALERT_DURATION: 10000,
    /** Duration for toast notifications (ms) */
    TOAST_DURATION: 4000,
});

/**
 * Polling intervals
 * @readonly
 */
export const POLLING_CONFIG = Object.freeze({
    /** Interval for fetching orders (ms) - used when real-time is disconnected */
    ORDERS_INTERVAL: 5000,
    /** Fallback interval when real-time is connected (ms) - for sync/backup */
    ORDERS_FALLBACK_INTERVAL: 60000,
    /** Interval for checking device status (ms) */
    DEVICE_STATUS_INTERVAL: 30000,
    /** Interval for checking overdue orders (ms) */
    OVERDUE_CHECK_INTERVAL: 10000,
    /** Interval for time display update (ms) */
    TIME_UPDATE_INTERVAL: 1000,
});

/**
 * Urgency levels for time-based sorting
 * @readonly
 * @enum {string}
 */
export const URGENCY_LEVEL = Object.freeze({
    /** Past scheduled time */
    OVERDUE: 'overdue',
    /** Within urgent threshold */
    URGENT: 'urgent',
    /** Within warning threshold */
    WARNING: 'warning',
    /** Normal priority */
    NORMAL: 'normal',
});

/**
 * Calculate urgency level based on minutes until scheduled time
 * @param {number} minutesUntil - Minutes until scheduled time (negative if overdue)
 * @returns {string} Urgency level
 */
export function calculateUrgency(minutesUntil) {
    if (minutesUntil < 0) return URGENCY_LEVEL.OVERDUE;
    if (minutesUntil <= TIME_SLOT_CONFIG.URGENT_THRESHOLD) return URGENCY_LEVEL.URGENT;
    if (minutesUntil <= TIME_SLOT_CONFIG.WARNING_THRESHOLD) return URGENCY_LEVEL.WARNING;
    return URGENCY_LEVEL.NORMAL;
}

/**
 * Get CSS class for urgency level
 * @param {string} urgency - Urgency level
 * @returns {string} Tailwind CSS class
 */
export function getUrgencyClass(urgency) {
    const classes = {
        [URGENCY_LEVEL.OVERDUE]: 'text-red-400',
        [URGENCY_LEVEL.URGENT]: 'text-red-400',
        [URGENCY_LEVEL.WARNING]: 'text-yellow-400',
        [URGENCY_LEVEL.NORMAL]: 'text-green-400',
    };
    return classes[urgency] || 'text-gray-400';
}

/**
 * Get urgency indicator emoji
 * @param {string} urgency - Urgency level
 * @returns {string} Emoji indicator
 */
export function getUrgencyIndicator(urgency) {
    const indicators = {
        [URGENCY_LEVEL.OVERDUE]: '🔴',
        [URGENCY_LEVEL.URGENT]: '🔴',
        [URGENCY_LEVEL.WARNING]: '🟡',
        [URGENCY_LEVEL.NORMAL]: '🟢',
    };
    return indicators[urgency] || '⚪';
}
