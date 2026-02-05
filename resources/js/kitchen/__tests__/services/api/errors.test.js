/**
 * Kitchen API Errors Unit Tests
 *
 * @group unit
 * @group kitchen
 * @group api
 */

import { describe, it, expect } from 'vitest';
import { KitchenApiError } from '../../../services/api/errors.js';
import { API_ERROR_CODE } from '../../../constants/api.js';

describe('KitchenApiError', () => {
    // ==================== Constructor ====================

    describe('constructor', () => {
        it('should create error with basic properties', () => {
            const error = new KitchenApiError('Test error', API_ERROR_CODE.SERVER_ERROR);

            expect(error.message).toBe('Test error');
            expect(error.code).toBe(API_ERROR_CODE.SERVER_ERROR);
            expect(error.name).toBe('KitchenApiError');
        });

        it('should set optional properties', () => {
            const cause = new Error('Original');
            const error = new KitchenApiError('Test error', API_ERROR_CODE.SERVER_ERROR, {
                status: 500,
                retryable: true,
                response: { data: 'test' },
                cause,
            });

            expect(error.status).toBe(500);
            expect(error.retryable).toBe(true);
            expect(error.response).toEqual({ data: 'test' });
            expect(error.cause).toBe(cause);
        });

        it('should default retryable to false', () => {
            const error = new KitchenApiError('Test', API_ERROR_CODE.INVALID_CODE);
            expect(error.retryable).toBe(false);
        });
    });

    // ==================== Error Type Checks ====================

    describe('isNetworkError()', () => {
        it('should return true for network error code', () => {
            const error = new KitchenApiError('Network', API_ERROR_CODE.NETWORK_ERROR);
            expect(error.isNetworkError()).toBe(true);
        });

        it('should return true for status 0', () => {
            const error = new KitchenApiError('Network', API_ERROR_CODE.SERVER_ERROR, {
                status: 0,
            });
            expect(error.isNetworkError()).toBe(true);
        });

        it('should return false for other errors', () => {
            const error = new KitchenApiError('Server', API_ERROR_CODE.SERVER_ERROR, {
                status: 500,
            });
            expect(error.isNetworkError()).toBe(false);
        });
    });

    describe('isAuthError()', () => {
        it('should return true for unauthorized code', () => {
            const error = new KitchenApiError('Unauthorized', API_ERROR_CODE.UNAUTHORIZED);
            expect(error.isAuthError()).toBe(true);
        });

        it('should return true for status 401', () => {
            const error = new KitchenApiError('Unauthorized', API_ERROR_CODE.SERVER_ERROR, {
                status: 401,
            });
            expect(error.isAuthError()).toBe(true);
        });

        it('should return false for other errors', () => {
            const error = new KitchenApiError('Forbidden', API_ERROR_CODE.DEVICE_DISABLED, {
                status: 403,
            });
            expect(error.isAuthError()).toBe(false);
        });
    });

    describe('isDeviceError()', () => {
        it('should return true for device not found', () => {
            const error = new KitchenApiError('Not found', API_ERROR_CODE.DEVICE_NOT_FOUND);
            expect(error.isDeviceError()).toBe(true);
        });

        it('should return true for device disabled', () => {
            const error = new KitchenApiError('Disabled', API_ERROR_CODE.DEVICE_DISABLED);
            expect(error.isDeviceError()).toBe(true);
        });

        it('should return true for invalid code', () => {
            const error = new KitchenApiError('Invalid', API_ERROR_CODE.INVALID_CODE);
            expect(error.isDeviceError()).toBe(true);
        });

        it('should return false for other errors', () => {
            const error = new KitchenApiError('Server', API_ERROR_CODE.SERVER_ERROR);
            expect(error.isDeviceError()).toBe(false);
        });
    });

    describe('isRetryable()', () => {
        it('should return retryable flag value', () => {
            const retryable = new KitchenApiError('Test', API_ERROR_CODE.NETWORK_ERROR, {
                retryable: true,
            });
            const notRetryable = new KitchenApiError('Test', API_ERROR_CODE.INVALID_CODE, {
                retryable: false,
            });

            expect(retryable.isRetryable()).toBe(true);
            expect(notRetryable.isRetryable()).toBe(false);
        });
    });

    // ==================== Static Factory Methods ====================

    describe('networkError()', () => {
        it('should create network error with cause', () => {
            const cause = new Error('Connection refused');
            const error = KitchenApiError.networkError(cause);

            expect(error.code).toBe(API_ERROR_CODE.NETWORK_ERROR);
            expect(error.status).toBe(0);
            expect(error.retryable).toBe(true);
            expect(error.cause).toBe(cause);
        });
    });

    describe('timeoutError()', () => {
        it('should create timeout error with duration', () => {
            const error = KitchenApiError.timeoutError(5000);

            expect(error.message).toContain('5000ms');
            expect(error.code).toBe(API_ERROR_CODE.NETWORK_ERROR);
            expect(error.retryable).toBe(true);
        });
    });

    describe('fromAxiosError()', () => {
        it('should handle network error (no response)', () => {
            const axiosError = {
                response: null,
                message: 'Network Error',
            };

            const error = KitchenApiError.fromAxiosError(axiosError);

            expect(error.code).toBe(API_ERROR_CODE.NETWORK_ERROR);
            expect(error.retryable).toBe(true);
        });

        it('should handle timeout error', () => {
            const axiosError = {
                response: null,
                code: 'ECONNABORTED',
                config: { timeout: 10000 },
            };

            const error = KitchenApiError.fromAxiosError(axiosError);

            expect(error.message).toContain('10000ms');
        });

        it('should handle response error with custom error_code', () => {
            const axiosError = {
                response: {
                    status: 400,
                    data: {
                        message: 'Invalid order',
                        error_code: API_ERROR_CODE.INVALID_STATUS,
                    },
                },
            };

            const error = KitchenApiError.fromAxiosError(axiosError);

            expect(error.message).toBe('Invalid order');
            expect(error.code).toBe(API_ERROR_CODE.INVALID_STATUS);
            expect(error.status).toBe(400);
        });

        it('should mark 5xx errors as retryable', () => {
            const axiosError = {
                response: {
                    status: 503,
                    data: { message: 'Service unavailable' },
                },
            };

            const error = KitchenApiError.fromAxiosError(axiosError);

            expect(error.retryable).toBe(true);
        });

        it('should not mark 4xx errors as retryable', () => {
            const axiosError = {
                response: {
                    status: 404,
                    data: { message: 'Not found' },
                },
            };

            const error = KitchenApiError.fromAxiosError(axiosError);

            expect(error.retryable).toBe(false);
        });
    });

    describe('statusToCode()', () => {
        it('should map 401 to UNAUTHORIZED', () => {
            expect(KitchenApiError.statusToCode(401)).toBe(API_ERROR_CODE.UNAUTHORIZED);
        });

        it('should map 403 to DEVICE_DISABLED', () => {
            expect(KitchenApiError.statusToCode(403)).toBe(API_ERROR_CODE.DEVICE_DISABLED);
        });

        it('should map 404 to DEVICE_NOT_FOUND', () => {
            expect(KitchenApiError.statusToCode(404)).toBe(API_ERROR_CODE.DEVICE_NOT_FOUND);
        });

        it('should map 5xx to SERVER_ERROR', () => {
            expect(KitchenApiError.statusToCode(500)).toBe(API_ERROR_CODE.SERVER_ERROR);
            expect(KitchenApiError.statusToCode(503)).toBe(API_ERROR_CODE.SERVER_ERROR);
        });

        it('should map unknown 4xx to NETWORK_ERROR', () => {
            expect(KitchenApiError.statusToCode(400)).toBe(API_ERROR_CODE.NETWORK_ERROR);
        });
    });

    // ==================== User Message ====================

    describe('getUserMessage()', () => {
        it('should return Russian message for known codes', () => {
            const errors = {
                [API_ERROR_CODE.INVALID_CODE]: 'Неверный или просроченный код',
                [API_ERROR_CODE.DEVICE_NOT_FOUND]: 'Устройство не найдено',
                [API_ERROR_CODE.DEVICE_DISABLED]: 'Устройство отключено',
                [API_ERROR_CODE.ORDER_NOT_FOUND]: 'Заказ не найден',
                [API_ERROR_CODE.NETWORK_ERROR]: 'Ошибка сети. Проверьте подключение',
                [API_ERROR_CODE.SERVER_ERROR]: 'Ошибка сервера. Попробуйте позже',
            };

            for (const [code, expectedMessage] of Object.entries(errors)) {
                const error = new KitchenApiError('Original', code);
                expect(error.getUserMessage()).toBe(expectedMessage);
            }
        });

        it('should fall back to original message for unknown codes', () => {
            const error = new KitchenApiError('Custom error message', 'UNKNOWN_CODE');
            expect(error.getUserMessage()).toBe('Custom error message');
        });
    });
});
