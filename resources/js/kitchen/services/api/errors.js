/**
 * Kitchen API Errors
 *
 * Custom error classes for API operations.
 *
 * @module kitchen/services/api/errors
 */

import { API_ERROR_CODE } from '../../constants/api.js';

/**
 * Custom error class for Kitchen API errors
 */
export class KitchenApiError extends Error {
    /**
     * @param {string} message - Error message
     * @param {string} code - Error code from API_ERROR_CODE
     * @param {Object} [options] - Additional options
     * @param {number} [options.status] - HTTP status code
     * @param {boolean} [options.retryable] - Whether request can be retried
     * @param {Object} [options.response] - Original response data
     * @param {Error} [options.cause] - Original error that caused this
     */
    constructor(message, code, options = {}) {
        super(message);
        this.name = 'KitchenApiError';
        this.code = code;
        this.status = options.status;
        this.retryable = options.retryable ?? false;
        this.response = options.response;
        this.cause = options.cause;

        // Maintains proper stack trace for where error was thrown
        if (Error.captureStackTrace) {
            Error.captureStackTrace(this, KitchenApiError);
        }
    }

    /**
     * Check if this is a network error
     * @returns {boolean}
     */
    isNetworkError() {
        return this.code === API_ERROR_CODE.NETWORK_ERROR || this.status === 0;
    }

    /**
     * Check if this is an authentication error
     * @returns {boolean}
     */
    isAuthError() {
        return this.code === API_ERROR_CODE.UNAUTHORIZED || this.status === 401;
    }

    /**
     * Check if this is a device-related error
     * @returns {boolean}
     */
    isDeviceError() {
        return [
            API_ERROR_CODE.DEVICE_NOT_FOUND,
            API_ERROR_CODE.DEVICE_DISABLED,
            API_ERROR_CODE.INVALID_CODE,
        ].includes(this.code);
    }

    /**
     * Check if error is retryable
     * @returns {boolean}
     */
    isRetryable() {
        return this.retryable;
    }

    /**
     * Create a network error instance
     * @param {Error} cause - Original error
     * @returns {KitchenApiError}
     */
    static networkError(cause) {
        return new KitchenApiError(
            'Network error - check your connection',
            API_ERROR_CODE.NETWORK_ERROR,
            { status: 0, retryable: true, cause }
        );
    }

    /**
     * Create a timeout error instance
     * @param {number} timeout - Timeout value in ms
     * @returns {KitchenApiError}
     */
    static timeoutError(timeout) {
        return new KitchenApiError(
            `Request timed out after ${timeout}ms`,
            API_ERROR_CODE.NETWORK_ERROR,
            { status: 0, retryable: true }
        );
    }

    /**
     * Create error from axios error response
     * @param {Error} axiosError - Axios error object
     * @returns {KitchenApiError}
     */
    static fromAxiosError(axiosError) {
        // Network error (no response)
        if (!axiosError.response) {
            if (axiosError.code === 'ECONNABORTED') {
                return KitchenApiError.timeoutError(axiosError.config?.timeout);
            }
            return KitchenApiError.networkError(axiosError);
        }

        const { status, data } = axiosError.response;
        const message = data?.message || axiosError.message || 'Unknown error';
        const errorCode = data?.error_code || KitchenApiError.statusToCode(status);

        return new KitchenApiError(message, errorCode, {
            status,
            retryable: status >= 500 || status === 0,
            response: data,
            cause: axiosError,
        });
    }

    /**
     * Convert HTTP status to error code
     * @param {number} status - HTTP status code
     * @returns {string} Error code
     */
    static statusToCode(status) {
        const mapping = {
            401: API_ERROR_CODE.UNAUTHORIZED,
            403: API_ERROR_CODE.DEVICE_DISABLED,
            404: API_ERROR_CODE.DEVICE_NOT_FOUND,
        };
        return mapping[status] || (status >= 500 ? API_ERROR_CODE.SERVER_ERROR : API_ERROR_CODE.NETWORK_ERROR);
    }

    /**
     * Get user-friendly error message in Russian
     * @returns {string}
     */
    getUserMessage() {
        const messages = {
            [API_ERROR_CODE.INVALID_CODE]: 'Неверный или просроченный код',
            [API_ERROR_CODE.DEVICE_NOT_FOUND]: 'Устройство не найдено',
            [API_ERROR_CODE.DEVICE_DISABLED]: 'Устройство отключено',
            [API_ERROR_CODE.ORDER_NOT_FOUND]: 'Заказ не найден',
            [API_ERROR_CODE.INVALID_STATUS]: 'Недопустимый статус',
            [API_ERROR_CODE.NETWORK_ERROR]: 'Ошибка сети. Проверьте подключение',
            [API_ERROR_CODE.SERVER_ERROR]: 'Ошибка сервера. Попробуйте позже',
            [API_ERROR_CODE.UNAUTHORIZED]: 'Требуется авторизация',
        };
        return messages[this.code] || this.message;
    }
}
