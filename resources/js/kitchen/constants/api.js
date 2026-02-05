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
 * @readonly
 */
export const API_PATHS = Object.freeze({
    /** Kitchen devices base path */
    DEVICES: '/api/kitchen-devices',
    /** Kitchen stations path */
    STATIONS: '/api/kitchen-stations',
});

/**
 * API endpoints
 * @readonly
 */
export const API_ENDPOINTS = Object.freeze({
    // Device endpoints
    DEVICE_STATUS: `${API_PATHS.DEVICES}/my-station`,
    DEVICE_LINK: `${API_PATHS.DEVICES}/link`,

    // Order endpoints
    ORDERS: `${API_PATHS.DEVICES}/orders`,
    ORDER_STATUS: (orderId) => `${API_PATHS.DEVICES}/orders/${orderId}/status`,
    ITEM_STATUS: (itemId) => `${API_PATHS.DEVICES}/order-items/${itemId}/status`,
    ORDER_COUNTS: `${API_PATHS.DEVICES}/orders/count-by-dates`,

    // Station endpoints
    STATIONS_ACTIVE: `${API_PATHS.STATIONS}/active`,

    // Waiter call
    CALL_WAITER: (orderId) => `${API_PATHS.DEVICES}/orders/${orderId}/call-waiter`,
});

/**
 * API error codes
 * @readonly
 * @enum {string}
 */
export const API_ERROR_CODE = Object.freeze({
    /** Invalid or expired linking code */
    INVALID_CODE: 'invalid_code',
    /** Device not found */
    DEVICE_NOT_FOUND: 'device_not_found',
    /** Device is disabled */
    DEVICE_DISABLED: 'device_disabled',
    /** Order not found */
    ORDER_NOT_FOUND: 'order_not_found',
    /** Invalid status transition */
    INVALID_STATUS: 'invalid_status',
    /** Network error */
    NETWORK_ERROR: 'network_error',
    /** Server error */
    SERVER_ERROR: 'server_error',
    /** Unauthorized */
    UNAUTHORIZED: 'unauthorized',
});

/**
 * HTTP status codes for common responses
 * @readonly
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
 * @readonly
 */
export const REQUEST_CONFIG = Object.freeze({
    /** Default request timeout (ms) */
    TIMEOUT: 10000,
    /** Max retry attempts for failed requests */
    MAX_RETRIES: 3,
    /** Base delay for retry backoff (ms) */
    RETRY_BASE_DELAY: 1000,
    /** Maximum delay for retry backoff (ms) */
    RETRY_MAX_DELAY: 10000,
});

/**
 * Status codes that should trigger retry
 * @type {readonly number[]}
 */
export const RETRYABLE_STATUS_CODES = Object.freeze([
    HTTP_STATUS.SERVER_ERROR,
    HTTP_STATUS.SERVICE_UNAVAILABLE,
    0, // Network error
]);

/**
 * Status codes that should NOT retry
 * @type {readonly number[]}
 */
export const NON_RETRYABLE_STATUS_CODES = Object.freeze([
    HTTP_STATUS.BAD_REQUEST,
    HTTP_STATUS.UNAUTHORIZED,
    HTTP_STATUS.FORBIDDEN,
    HTTP_STATUS.NOT_FOUND,
]);

/**
 * Check if error status code is retryable
 * @param {number} status - HTTP status code
 * @returns {boolean}
 */
export function isRetryableStatus(status) {
    if (NON_RETRYABLE_STATUS_CODES.includes(status)) return false;
    return RETRYABLE_STATUS_CODES.includes(status) || status >= 500;
}
