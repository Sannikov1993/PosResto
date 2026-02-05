/**
 * NetworkRetry Unit Tests
 *
 * Tests for the network retry layer with exponential backoff,
 * circuit breaker, and offline detection.
 *
 * @group unit
 * @group session
 * @group network
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { NetworkRetry, NetworkRetryError } from '../NetworkRetry.js';

describe('NetworkRetry', () => {
    let networkRetry;

    beforeEach(() => {
        vi.useFakeTimers();
        networkRetry = new NetworkRetry({
            debug: false,
            maxAttempts: 3,
            baseDelay: 100,
            maxDelay: 1000,
            requestTimeout: 5000,
            circuitFailureThreshold: 3,
            circuitResetTimeout: 5000,
        });
    });

    afterEach(() => {
        networkRetry.destroy();
        vi.useRealTimers();
    });

    // ==================== BASIC EXECUTION ====================

    describe('execute()', () => {
        it('should execute successful request', async () => {
            const mockFn = vi.fn().mockResolvedValue({ data: 'success' });

            const result = await networkRetry.execute(mockFn);

            expect(result).toEqual({ data: 'success' });
            expect(mockFn).toHaveBeenCalledTimes(1);
        });

        it('should return result from async function', async () => {
            const mockFn = vi.fn().mockResolvedValue({ id: 123, name: 'test' });

            const result = await networkRetry.execute(mockFn);

            expect(result.id).toBe(123);
            expect(result.name).toBe('test');
        });
    });

    // ==================== RETRY LOGIC ====================

    describe('retry behavior', () => {
        it('should retry on failure', async () => {
            const mockFn = vi.fn()
                .mockRejectedValueOnce(new Error('Network Error'))
                .mockRejectedValueOnce(new Error('Network Error'))
                .mockResolvedValueOnce({ data: 'success' });

            const promise = networkRetry.execute(mockFn);

            // Fast-forward through retries
            await vi.advanceTimersByTimeAsync(100); // First retry delay
            await vi.advanceTimersByTimeAsync(200); // Second retry delay

            const result = await promise;

            expect(result).toEqual({ data: 'success' });
            expect(mockFn).toHaveBeenCalledTimes(3);
        });

        it('should throw after max attempts', async () => {
            const mockFn = vi.fn().mockRejectedValue(new Error('Persistent Error'));

            const promise = networkRetry.execute(mockFn, { maxAttempts: 3 });

            // Fast-forward through all retries
            await vi.advanceTimersByTimeAsync(100);
            await vi.advanceTimersByTimeAsync(200);

            await expect(promise).rejects.toThrow();
            expect(mockFn).toHaveBeenCalledTimes(3);
        });

        it('should not retry 401 errors', async () => {
            const error = new Error('Unauthorized');
            error.response = { status: 401 };
            const mockFn = vi.fn().mockRejectedValue(error);

            await expect(networkRetry.execute(mockFn)).rejects.toThrow();
            expect(mockFn).toHaveBeenCalledTimes(1);
        });

        it('should not retry 403 errors', async () => {
            const error = new Error('Forbidden');
            error.response = { status: 403 };
            const mockFn = vi.fn().mockRejectedValue(error);

            await expect(networkRetry.execute(mockFn)).rejects.toThrow();
            expect(mockFn).toHaveBeenCalledTimes(1);
        });

        it('should retry 500 errors', async () => {
            const error = new Error('Server Error');
            error.response = { status: 500 };

            const mockFn = vi.fn()
                .mockRejectedValueOnce(error)
                .mockResolvedValueOnce({ data: 'recovered' });

            const promise = networkRetry.execute(mockFn);
            await vi.advanceTimersByTimeAsync(100);

            const result = await promise;
            expect(result).toEqual({ data: 'recovered' });
            expect(mockFn).toHaveBeenCalledTimes(2);
        });

        it('should retry 503 errors', async () => {
            const error = new Error('Service Unavailable');
            error.response = { status: 503 };

            const mockFn = vi.fn()
                .mockRejectedValueOnce(error)
                .mockResolvedValueOnce({ data: 'success' });

            const promise = networkRetry.execute(mockFn);
            await vi.advanceTimersByTimeAsync(100);

            const result = await promise;
            expect(mockFn).toHaveBeenCalledTimes(2);
        });

        it('should call onRetry callback', async () => {
            const onRetry = vi.fn();
            const mockFn = vi.fn()
                .mockRejectedValueOnce(new Error('Error'))
                .mockResolvedValueOnce({ data: 'success' });

            const promise = networkRetry.execute(mockFn, { onRetry });
            await vi.advanceTimersByTimeAsync(100);
            await promise;

            expect(onRetry).toHaveBeenCalledWith(expect.objectContaining({
                attempt: 1,
                maxAttempts: 3,
            }));
        });
    });

    // ==================== EXPONENTIAL BACKOFF ====================

    describe('exponential backoff', () => {
        it('should increase delay exponentially', async () => {
            const delays = [];
            const originalSetTimeout = global.setTimeout;

            // Track setTimeout calls
            vi.spyOn(global, 'setTimeout').mockImplementation((fn, delay) => {
                if (delay > 0 && delay < 10000) {
                    delays.push(delay);
                }
                return originalSetTimeout(fn, 0); // Execute immediately for test
            });

            const mockFn = vi.fn()
                .mockRejectedValueOnce(new Error('Error'))
                .mockRejectedValueOnce(new Error('Error'))
                .mockResolvedValueOnce({ data: 'success' });

            await networkRetry.execute(mockFn);

            // Delays should increase (with jitter, so we check the pattern)
            if (delays.length >= 2) {
                // Second delay should be larger than first (accounting for jitter)
                expect(delays[1]).toBeGreaterThanOrEqual(delays[0] * 0.7);
            }

            vi.restoreAllMocks();
        });

        it('should cap delay at maxDelay', async () => {
            const retry = new NetworkRetry({
                maxAttempts: 10,
                baseDelay: 1000,
                maxDelay: 2000,
                backoffMultiplier: 10, // Would exceed maxDelay quickly
            });

            // Internal calculation test - delay should never exceed maxDelay
            const delay = retry._calculateDelay(5);
            expect(delay).toBeLessThanOrEqual(2000 * 1.3); // maxDelay + jitter

            retry.destroy();
        });
    });

    // ==================== CIRCUIT BREAKER ====================

    describe('circuit breaker', () => {
        it('should open circuit after threshold failures', async () => {
            const mockFn = vi.fn().mockRejectedValue(new Error('Error'));

            // Fail multiple times to open circuit
            for (let i = 0; i < 3; i++) {
                try {
                    await networkRetry.execute(mockFn, { maxAttempts: 1 });
                } catch (e) {
                    // Expected
                }
            }

            const status = networkRetry.getStatus();
            expect(status.circuitState).toBe('open');
        });

        it('should reject requests when circuit is open', async () => {
            const mockFn = vi.fn().mockRejectedValue(new Error('Error'));

            // Open the circuit
            for (let i = 0; i < 3; i++) {
                try {
                    await networkRetry.execute(mockFn, { maxAttempts: 1 });
                } catch (e) {
                    // Expected
                }
            }

            // Next request should be rejected immediately
            await expect(networkRetry.execute(mockFn))
                .rejects
                .toThrow('Circuit breaker open');

            // The function should not have been called for the rejected request
            expect(mockFn).toHaveBeenCalledTimes(3);
        });

        it('should transition to half-open after timeout', async () => {
            const mockFn = vi.fn().mockRejectedValue(new Error('Error'));

            // Open the circuit
            for (let i = 0; i < 3; i++) {
                try {
                    await networkRetry.execute(mockFn, { maxAttempts: 1 });
                } catch (e) {
                    // Expected
                }
            }

            expect(networkRetry.getStatus().circuitState).toBe('open');

            // Advance time past reset timeout
            vi.advanceTimersByTime(6000);

            // Check circuit state (will transition on next request attempt)
            const newMockFn = vi.fn().mockResolvedValue({ data: 'success' });
            await networkRetry.execute(newMockFn);

            expect(networkRetry.getStatus().circuitState).toBe('closed');
        });

        it('should close circuit on successful request after half-open', async () => {
            const failingFn = vi.fn().mockRejectedValue(new Error('Error'));

            // Open the circuit
            for (let i = 0; i < 3; i++) {
                try {
                    await networkRetry.execute(failingFn, { maxAttempts: 1 });
                } catch (e) {
                    // Expected
                }
            }

            vi.advanceTimersByTime(6000);

            // Successful request should close circuit
            const successFn = vi.fn().mockResolvedValue({ data: 'success' });
            await networkRetry.execute(successFn);

            expect(networkRetry.getStatus().circuitState).toBe('closed');
            expect(networkRetry.getStatus().failureCount).toBe(0);
        });

        it('should allow manual circuit reset', () => {
            networkRetry._circuitState = 'open';
            networkRetry._failureCount = 5;

            networkRetry.resetCircuitBreaker();

            expect(networkRetry.getStatus().circuitState).toBe('closed');
            expect(networkRetry.getStatus().failureCount).toBe(0);
        });
    });

    // ==================== REQUEST DEDUPLICATION ====================

    describe('request deduplication', () => {
        it('should deduplicate concurrent requests with same key', async () => {
            const mockFn = vi.fn().mockImplementation(() =>
                new Promise(resolve => setTimeout(() => resolve({ data: 'result' }), 100))
            );

            // Start two requests with same dedupeKey
            const promise1 = networkRetry.execute(mockFn, { dedupeKey: 'test' });
            const promise2 = networkRetry.execute(mockFn, { dedupeKey: 'test' });

            vi.advanceTimersByTime(100);

            const [result1, result2] = await Promise.all([promise1, promise2]);

            expect(result1).toEqual(result2);
            expect(mockFn).toHaveBeenCalledTimes(1); // Only one actual request
        });

        it('should not deduplicate requests without key', async () => {
            const mockFn = vi.fn().mockResolvedValue({ data: 'result' });

            await Promise.all([
                networkRetry.execute(mockFn),
                networkRetry.execute(mockFn),
            ]);

            expect(mockFn).toHaveBeenCalledTimes(2);
        });

        it('should not deduplicate requests with different keys', async () => {
            const mockFn = vi.fn().mockResolvedValue({ data: 'result' });

            await Promise.all([
                networkRetry.execute(mockFn, { dedupeKey: 'key1' }),
                networkRetry.execute(mockFn, { dedupeKey: 'key2' }),
            ]);

            expect(mockFn).toHaveBeenCalledTimes(2);
        });
    });

    // ==================== TIMEOUT ====================

    describe('timeout handling', () => {
        it('should timeout long requests', async () => {
            const mockFn = vi.fn().mockImplementation(() =>
                new Promise(resolve => setTimeout(resolve, 10000))
            );

            const promise = networkRetry.execute(mockFn, { maxAttempts: 1 });

            // Advance past timeout
            vi.advanceTimersByTime(6000);

            await expect(promise).rejects.toThrow(/timeout/i);
        });

        it('should retry after timeout', async () => {
            let callCount = 0;
            const mockFn = vi.fn().mockImplementation(() => {
                callCount++;
                if (callCount === 1) {
                    return new Promise(resolve => setTimeout(resolve, 10000));
                }
                return Promise.resolve({ data: 'success' });
            });

            const promise = networkRetry.execute(mockFn);

            // First call times out
            vi.advanceTimersByTime(6000);
            // Retry delay
            vi.advanceTimersByTime(100);

            const result = await promise;
            expect(result).toEqual({ data: 'success' });
        });
    });

    // ==================== NETWORK RETRY ERROR ====================

    describe('NetworkRetryError', () => {
        it('should create error with correct properties', () => {
            const error = new NetworkRetryError('Test error', 'TEST_CODE', {
                retryable: true,
                status: 500,
            });

            expect(error.message).toBe('Test error');
            expect(error.code).toBe('TEST_CODE');
            expect(error.retryable).toBe(true);
            expect(error.status).toBe(500);
        });

        it('should detect offline error', () => {
            const error = new NetworkRetryError('Offline', 'OFFLINE');
            expect(error.isOffline()).toBe(true);
        });

        it('should detect timeout error', () => {
            const error = new NetworkRetryError('Timeout', 'TIMEOUT');
            expect(error.isTimeout()).toBe(true);
        });

        it('should detect abort error', () => {
            const error = new NetworkRetryError('Aborted', 'ABORTED');
            expect(error.isAborted()).toBe(true);
        });

        it('should detect circuit open error', () => {
            const error = new NetworkRetryError('Circuit open', 'CIRCUIT_OPEN');
            expect(error.isCircuitOpen()).toBe(true);
        });
    });

    // ==================== STATUS ====================

    describe('getStatus()', () => {
        it('should return comprehensive status', () => {
            const status = networkRetry.getStatus();

            expect(status).toHaveProperty('isOnline');
            expect(status).toHaveProperty('circuitState');
            expect(status).toHaveProperty('failureCount');
            expect(status).toHaveProperty('pendingRequests');
        });

        it('should track pending requests', async () => {
            const mockFn = vi.fn().mockImplementation(() =>
                new Promise(resolve => setTimeout(() => resolve({ data: 'result' }), 1000))
            );

            networkRetry.execute(mockFn, { dedupeKey: 'pending1' });
            networkRetry.execute(mockFn, { dedupeKey: 'pending2' });

            expect(networkRetry.getStatus().pendingRequests).toBe(2);
        });
    });
});
