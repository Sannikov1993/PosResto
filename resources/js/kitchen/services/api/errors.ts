/**
 * Kitchen API Errors
 *
 * Custom error classes for API operations.
 *
 * @module kitchen/services/api/errors
 */

import { API_ERROR_CODE } from '../../constants/api.js';
import type { AxiosError } from 'axios';

interface KitchenApiErrorOptions {
    status?: number;
    retryable?: boolean;
    response?: Record<string, any>;
    cause?: Error;
}

export class KitchenApiError extends Error {
    name = 'KitchenApiError';
    code: string;
    status?: number;
    retryable: boolean;
    response?: Record<string, any>;
    cause?: Error;

    constructor(message: string, code: string, options: KitchenApiErrorOptions = {}) {
        super(message);
        this.code = code;
        this.status = options.status;
        this.retryable = options.retryable ?? false;
        this.response = options.response;
        this.cause = options.cause;

        if (Error.captureStackTrace) {
            Error.captureStackTrace(this, KitchenApiError);
        }
    }

    isNetworkError(): boolean {
        return this.code === API_ERROR_CODE.NETWORK_ERROR || this.status === 0;
    }

    isAuthError(): boolean {
        return this.code === API_ERROR_CODE.UNAUTHORIZED || this.status === 401;
    }

    isDeviceError(): boolean {
        return [
            API_ERROR_CODE.DEVICE_NOT_FOUND,
            API_ERROR_CODE.DEVICE_DISABLED,
            API_ERROR_CODE.INVALID_CODE,
        ].includes(this.code as any);
    }

    isRetryable(): boolean {
        return this.retryable;
    }

    static networkError(cause: Error): KitchenApiError {
        return new KitchenApiError(
            'Network error - check your connection',
            API_ERROR_CODE.NETWORK_ERROR,
            { status: 0, retryable: true, cause }
        );
    }

    static timeoutError(timeout?: number): KitchenApiError {
        return new KitchenApiError(
            `Request timed out after ${timeout}ms`,
            API_ERROR_CODE.NETWORK_ERROR,
            { status: 0, retryable: true }
        );
    }

    static fromAxiosError(axiosError: AxiosError<{ message?: string; error_code?: string }>): KitchenApiError {
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
            response: data as Record<string, any>,
            cause: axiosError,
        });
    }

    static statusToCode(status: number): string {
        const mapping: Record<number, string> = {
            401: API_ERROR_CODE.UNAUTHORIZED,
            403: API_ERROR_CODE.DEVICE_DISABLED,
            404: API_ERROR_CODE.DEVICE_NOT_FOUND,
        };
        return mapping[status] || (status >= 500 ? API_ERROR_CODE.SERVER_ERROR : API_ERROR_CODE.NETWORK_ERROR);
    }

    getUserMessage(): string {
        const messages: Record<string, string> = {
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
