/**
 * Centralized Real-time Configuration
 *
 * @module shared/config/realtimeConfig
 */

export interface RetryConfig {
    maxRetries: number;
    initialDelay: number;
    maxDelay: number;
    multiplier: number;
    jitterPercent: number;
}

export const RETRY_CONFIG: RetryConfig = {
    maxRetries: 10,
    initialDelay: 1000,
    maxDelay: 30000,
    multiplier: 1.5,
    jitterPercent: 0.2,
};

export interface DebounceConfig {
    apiRefresh: number;
    sound: number;
    toast: number;
}

export const DEBOUNCE_CONFIG: DebounceConfig = {
    apiRefresh: 300,
    sound: 500,
    toast: 1000,
};

export interface HealthConfig {
    checkInterval: number;
    staleThreshold: number;
    debugMode: boolean;
}

export const HEALTH_CONFIG: HealthConfig = {
    checkInterval: 60000,
    staleThreshold: 120000, // 2 мин — переподключение если нет событий
    debugMode: false,
};

export const EVENT_TYPES: Record<string, string[]> = {
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

export function getRetryDelay(retryCount: number): number {
    const delay = Math.min(
        RETRY_CONFIG.initialDelay * Math.pow(RETRY_CONFIG.multiplier, retryCount),
        RETRY_CONFIG.maxDelay
    );
    const jitter = 1 - RETRY_CONFIG.jitterPercent + Math.random() * RETRY_CONFIG.jitterPercent * 2;
    return Math.round(delay * jitter);
}

interface DebouncedFn<T extends (...args: any[]) => void> {
    (...args: Parameters<T>): void;
    cancel: () => void;
}

export function debounce<T extends (...args: any[]) => void>(fn: T, delay: number): DebouncedFn<T> {
    let timeoutId: ReturnType<typeof setTimeout> | null = null;

    const debounced = ((...args: Parameters<T>) => {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
        timeoutId = setTimeout(() => {
            timeoutId = null;
            fn(...args);
        }, delay);
    }) as DebouncedFn<T>;

    debounced.cancel = () => {
        if (timeoutId) {
            clearTimeout(timeoutId);
            timeoutId = null;
        }
    };

    return debounced;
}

export function throttle<T extends (...args: any[]) => void>(fn: T, delay: number): (...args: Parameters<T>) => void {
    let lastCall = 0;
    let timeoutId: ReturnType<typeof setTimeout> | null = null;

    return (...args: Parameters<T>) => {
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
