/**
 * Timing Thresholds Constants
 *
 * Defines timing thresholds for order urgency,
 * overdue warnings, and time slot calculations.
 *
 * @module kitchen/constants/thresholds
 */

export const OVERDUE_THRESHOLDS = Object.freeze({
    WARNING: 10,
    CRITICAL: 15,
    ALERT: 20,
});

export const TIME_SLOT_CONFIG = Object.freeze({
    DURATION_MINUTES: 30,
    URGENT_THRESHOLD: 30,
    WARNING_THRESHOLD: 60,
});

export const ALERT_CONFIG = Object.freeze({
    OVERDUE_ALERT_INTERVAL: 30000,
    CRITICAL_WARNING_INTERVAL: 60000,
    NEW_ORDER_ALERT_DURATION: 5000,
    OVERDUE_ALERT_DURATION: 10000,
    TOAST_DURATION: 4000,
});

export const POLLING_CONFIG = Object.freeze({
    ORDERS_INTERVAL: 5000,
    ORDERS_FALLBACK_INTERVAL: 60000,
    DEVICE_STATUS_INTERVAL: 30000,
    OVERDUE_CHECK_INTERVAL: 10000,
    TIME_UPDATE_INTERVAL: 1000,
});

export const URGENCY_LEVEL = Object.freeze({
    OVERDUE: 'overdue',
    URGENT: 'urgent',
    WARNING: 'warning',
    NORMAL: 'normal',
});

export function calculateUrgency(minutesUntil: number): string {
    if (minutesUntil < 0) return URGENCY_LEVEL.OVERDUE;
    if (minutesUntil <= TIME_SLOT_CONFIG.URGENT_THRESHOLD) return URGENCY_LEVEL.URGENT;
    if (minutesUntil <= TIME_SLOT_CONFIG.WARNING_THRESHOLD) return URGENCY_LEVEL.WARNING;
    return URGENCY_LEVEL.NORMAL;
}

export function getUrgencyClass(urgency: string): string {
    const classes: Record<string, string> = {
        [URGENCY_LEVEL.OVERDUE]: 'text-red-400',
        [URGENCY_LEVEL.URGENT]: 'text-red-400',
        [URGENCY_LEVEL.WARNING]: 'text-yellow-400',
        [URGENCY_LEVEL.NORMAL]: 'text-green-400',
    };
    return classes[urgency] || 'text-gray-400';
}

export function getUrgencyIndicator(urgency: string): string {
    const indicators: Record<string, string> = {
        [URGENCY_LEVEL.OVERDUE]: '🔴',
        [URGENCY_LEVEL.URGENT]: '🔴',
        [URGENCY_LEVEL.WARNING]: '🟡',
        [URGENCY_LEVEL.NORMAL]: '🟢',
    };
    return indicators[urgency] || '⚪';
}
