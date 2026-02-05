/**
 * Error Handler Composable Unit Tests
 *
 * @group unit
 * @group kitchen
 * @group composables
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { useErrorHandler, ERROR_SEVERITY } from '../../composables/useErrorHandler.js';
import { KitchenApiError } from '../../services/api/errors.js';
import { API_ERROR_CODE } from '../../constants/api.js';

describe('useErrorHandler', () => {
    let errorHandler;

    beforeEach(() => {
        errorHandler = useErrorHandler();
        errorHandler.clearHistory();
        vi.spyOn(console, 'error').mockImplementation(() => {});
        vi.spyOn(console, 'warn').mockImplementation(() => {});
        vi.spyOn(console, 'info').mockImplementation(() => {});
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    // ==================== handleError ====================

    describe('handleError()', () => {
        it('should handle generic errors', () => {
            const error = new Error('Test error');
            const result = errorHandler.handleError(error, { context: 'test' });

            expect(result.message).toBe('Test error');
            expect(result.context).toBe('test');
            expect(result.severity).toBe(ERROR_SEVERITY.ERROR);
            expect(errorHandler.lastError.value).toEqual(result);
        });

        it('should handle KitchenApiError with user message', () => {
            const error = new KitchenApiError(
                'Server error',
                API_ERROR_CODE.SERVER_ERROR,
                { status: 500 }
            );

            const result = errorHandler.handleError(error, { context: 'api' });

            expect(result.message).toBe('Ошибка сервера. Попробуйте позже');
            expect(result.code).toBe(API_ERROR_CODE.SERVER_ERROR);
        });

        it('should add error to history', () => {
            const error = new Error('Test');
            errorHandler.handleError(error);

            expect(errorHandler.errorHistory.value).toHaveLength(1);
        });

        it('should limit history size', () => {
            // Add more than max history size
            for (let i = 0; i < 60; i++) {
                errorHandler.handleError(new Error(`Error ${i}`));
            }

            expect(errorHandler.errorHistory.value.length).toBeLessThanOrEqual(50);
        });

        it('should re-throw when rethrow option is true', () => {
            const error = new Error('Test');

            expect(() => {
                errorHandler.handleError(error, { rethrow: true });
            }).toThrow('Test');
        });

        it('should log to console', () => {
            const error = new Error('Test');
            errorHandler.handleError(error, { context: 'test' });

            expect(console.error).toHaveBeenCalled();
        });
    });

    // ==================== Severity Detection ====================

    describe('severity detection', () => {
        it('should detect WARNING for network errors', () => {
            const error = new KitchenApiError(
                'Network',
                API_ERROR_CODE.NETWORK_ERROR,
                { status: 0 }
            );

            const result = errorHandler.handleError(error);

            expect(result.severity).toBe(ERROR_SEVERITY.WARNING);
        });

        it('should detect ERROR for auth errors', () => {
            const error = new KitchenApiError(
                'Unauthorized',
                API_ERROR_CODE.UNAUTHORIZED,
                { status: 401 }
            );

            const result = errorHandler.handleError(error);

            expect(result.severity).toBe(ERROR_SEVERITY.ERROR);
        });

        it('should detect CRITICAL for 5xx errors', () => {
            const error = new KitchenApiError(
                'Server error',
                API_ERROR_CODE.SERVER_ERROR,
                { status: 500 }
            );

            const result = errorHandler.handleError(error);

            expect(result.severity).toBe(ERROR_SEVERITY.CRITICAL);
        });
    });

    // ==================== withErrorHandling ====================

    describe('withErrorHandling()', () => {
        it('should execute function and return result', async () => {
            const fn = vi.fn().mockResolvedValue('success');

            const result = await errorHandler.withErrorHandling(fn);

            expect(result).toBe('success');
        });

        it('should catch errors and return null', async () => {
            const fn = vi.fn().mockRejectedValue(new Error('Test'));

            const result = await errorHandler.withErrorHandling(fn, { context: 'test' });

            expect(result).toBeNull();
            expect(errorHandler.lastError.value).not.toBeNull();
        });
    });

    // ==================== withRetry ====================

    describe('withRetry()', () => {
        it('should succeed on first attempt', async () => {
            const fn = vi.fn().mockResolvedValue('success');

            const result = await errorHandler.withRetry(fn, { maxRetries: 3 });

            expect(result).toBe('success');
            expect(fn).toHaveBeenCalledTimes(1);
        });

        it('should retry on failure', async () => {
            const fn = vi.fn()
                .mockRejectedValueOnce(new Error('Fail 1'))
                .mockRejectedValueOnce(new Error('Fail 2'))
                .mockResolvedValue('success');

            const result = await errorHandler.withRetry(fn, {
                maxRetries: 3,
                delay: 10,
            });

            expect(result).toBe('success');
            expect(fn).toHaveBeenCalledTimes(3);
        });

        it('should throw after max retries', async () => {
            const fn = vi.fn().mockRejectedValue(new Error('Always fails'));

            await expect(
                errorHandler.withRetry(fn, { maxRetries: 2, delay: 10 })
            ).rejects.toThrow('Always fails');

            expect(fn).toHaveBeenCalledTimes(2);
        });

        it('should not retry non-retryable errors', async () => {
            const error = new KitchenApiError(
                'Not found',
                API_ERROR_CODE.DEVICE_NOT_FOUND,
                { retryable: false }
            );
            const fn = vi.fn().mockRejectedValue(error);

            await expect(
                errorHandler.withRetry(fn, { maxRetries: 3, delay: 10 })
            ).rejects.toThrow();

            expect(fn).toHaveBeenCalledTimes(1);
        });
    });

    // ==================== clearLastError ====================

    describe('clearLastError()', () => {
        it('should clear last error', () => {
            errorHandler.handleError(new Error('Test'));
            expect(errorHandler.lastError.value).not.toBeNull();

            errorHandler.clearLastError();
            expect(errorHandler.lastError.value).toBeNull();
        });
    });

    // ==================== clearHistory ====================

    describe('clearHistory()', () => {
        it('should clear error history', () => {
            errorHandler.handleError(new Error('Test 1'));
            errorHandler.handleError(new Error('Test 2'));
            expect(errorHandler.errorHistory.value.length).toBe(2);

            errorHandler.clearHistory();
            expect(errorHandler.errorHistory.value.length).toBe(0);
        });
    });

    // ==================== getErrorsBySeverity ====================

    describe('getErrorsBySeverity()', () => {
        it('should filter errors by severity', () => {
            // Add different severity errors
            errorHandler.handleError(
                new KitchenApiError('Network', API_ERROR_CODE.NETWORK_ERROR, { status: 0 })
            );
            errorHandler.handleError(
                new KitchenApiError('Server', API_ERROR_CODE.SERVER_ERROR, { status: 500 })
            );

            const warnings = errorHandler.getErrorsBySeverity(ERROR_SEVERITY.WARNING);
            const critical = errorHandler.getErrorsBySeverity(ERROR_SEVERITY.CRITICAL);

            expect(warnings).toHaveLength(1);
            expect(critical).toHaveLength(1);
        });
    });
});
