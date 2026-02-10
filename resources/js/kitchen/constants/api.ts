/**
 * API Configuration Constants
 *
 * Defines API endpoints, error codes, and request configuration
 * for the kitchen display system.
 *
 * @module kitchen/constants/api
 */

/**
 * Base API paths
 */
export const API_PATHS = Object.freeze({
    DEVICES: '/api/kitchen-devices',
    STATIONS: '/api/kitchen-stations',
});

/**
 * API endpoints
 */
export const API_ENDPOINTS = Object.freeze({
    // Device endpoints
    DEVICE_STATUS: `${API_PATHS.DEVICES}/my-station`,
    DEVICE_LINK: `${API_PATHS.DEVICES}/link`,

    // Order endpoints
    ORDERS: `${API_PATHS.DEVICES}/orders`,
    ORDER_STATUS: (orderId: number) => `${API_PATHS.DEVICES}/orders/${orderId}/status`,
    ITEM_STATUS: (itemId: number) => `${API_PATHS.DEVICES}/order-items/${itemId}/status`,
    ORDER_COUNTS: `${API_PATHS.DEVICES}/orders/count-by-dates`,

    // Station endpoints
    STATIONS_ACTIVE: `${API_PATHS.STATIONS}/active`,

    // Waiter call
    CALL_WAITER: (orderId: number) => `${API_PATHS.DEVICES}/orders/${orderId}/call-waiter`,
});

/**
 * API error codes
 */
export const API_ERROR_CODE = Object.freeze({
    INVALID_CODE: 'invalid_code',
    DEVICE_NOT_FOUND: 'device_not_found',
    DEVICE_DISABLED: 'device_disabled',
    ORDER_NOT_FOUND: 'order_not_found',
    INVALID_STATUS: 'invalid_status',
    NETWORK_ERROR: 'network_error',
    SERVER_ERROR: 'server_error',
    UNAUTHORIZED: 'unauthorized',
});

/**
 * HTTP status codes for common responses
 */
export const HTTP_STATUS = Object.freeze({
    OK: 200,
    BAD_REQUEST: 400,
    UNAUTHORIZED: 401,
    FORBIDDEN: 403,
    NOT_FOUND: 404,
    SERVER_ERROR: 500,
    SERVICE_UNAVAILABLE: 503,
});

/**
 * Request configuration
 */
export const REQUEST_CONFIG = Object.freeze({
    TIMEOUT: 10000,
    MAX_RETRIES: 3,
    RETRY_BASE_DELAY: 1000,
    RETRY_MAX_DELAY: 10000,
});

/**
 * Status codes that should trigger retry
 */
export const RETRYABLE_STATUS_CODES: readonly number[] = Object.freeze([
    HTTP_STATUS.SERVER_ERROR,
    HTTP_STATUS.SERVICE_UNAVAILABLE,
    0, // Network error
]);

/**
 * Status codes that should NOT retry
 */
export const NON_RETRYABLE_STATUS_CODES: readonly number[] = Object.freeze([
    HTTP_STATUS.BAD_REQUEST,
    HTTP_STATUS.UNAUTHORIZED,
    HTTP_STATUS.FORBIDDEN,
    HTTP_STATUS.NOT_FOUND,
]);

/**
 * Check if error status code is retryable
 */
export function isRetryableStatus(status: number): boolean {
    if (NON_RETRYABLE_STATUS_CODES.includes(status)) return false;
    return RETRYABLE_STATUS_CODES.includes(status) || status >= 500;
}
