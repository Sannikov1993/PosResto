/**
 * NetworkRetry - Intelligent HTTP retry mechanism with exponential backoff
 *
 * Provides robust network request handling with:
 * - Exponential backoff with jitter
 * - Configurable retry policies
 * - Request timeout handling
 * - Circuit breaker pattern
 * - Request deduplication
 * - Offline detection
 *
 * @module services/session/NetworkRetry
 */

import { RETRY_CONFIG } from './constants.js';

/**
 * Circuit breaker states
 */
const CIRCUIT_STATES = {
    CLOSED: 'closed',      // Normal operation
    OPEN: 'open',          // Failing, reject requests
    HALF_OPEN: 'half_open' // Testing if service recovered
};

/**
 * NetworkRetry class for resilient HTTP requests
 */
export class NetworkRetry {
    /**
     * Creates a new NetworkRetry instance
     * @param {Object} options - Configuration options
     */
    constructor(options = {}) {
        // Retry configuration
        this._maxAttempts = options.maxAttempts || RETRY_CONFIG.MAX_ATTEMPTS;
        this._baseDelay = options.baseDelay || RETRY_CONFIG.BASE_DELAY;
        this._maxDelay = options.maxDelay || RETRY_CONFIG.MAX_DELAY;
        this._backoffMultiplier = options.backoffMultiplier || RETRY_CONFIG.BACKOFF_MULTIPLIER;
        this._jitterFactor = options.jitterFactor || RETRY_CONFIG.JITTER_FACTOR;
        this._requestTimeout = options.requestTimeout || RETRY_CONFIG.REQUEST_TIMEOUT;
        this._retryableStatusCodes = options.retryableStatusCodes || RETRY_CONFIG.RETRYABLE_STATUS_CODES;
        this._retryableErrors = options.retryableErrors || RETRY_CONFIG.RETRYABLE_ERRORS;

        // Circuit breaker configuration
        this._circuitBreakerEnabled = options.circuitBreakerEnabled !== false;
        this._circuitFailureThreshold = options.circuitFailureThreshold || 5;
        this._circuitResetTimeout = options.circuitResetTimeout || 30000;

        // Debug mode
        this._debug = options.debug || false;

        // Internal state
        this._circuitState = CIRCUIT_STATES.CLOSED;
        this._failureCount = 0;
        this._lastFailureTime = null;
        this._pendingRequests = new Map();
        this._isOnline = typeof navigator !== 'undefined' ? navigator.onLine : true;

        // Setup online/offline listeners
        this._setupNetworkListeners();
    }

    /**
     * Setup network status listeners
     * @private
     */
    _setupNetworkListeners() {
        if (typeof window === 'undefined') {
            return;
        }

        window.addEventListener('online', () => {
            this._isOnline = true;
            this._log('Network online');

            // Reset circuit breaker when back online
            if (this._circuitState === CIRCUIT_STATES.OPEN) {
                this._circuitState = CIRCUIT_STATES.HALF_OPEN;
            }
        });

        window.addEventListener('offline', () => {
            this._isOnline = false;
            this._log('Network offline');
        });
    }

    /**
     * Execute a request with retry logic
     * @param {Function} requestFn - Async function that performs the request
     * @param {Object} options - Request options
     * @returns {Promise} Request result
     */
    async execute(requestFn, options = {}) {
        const {
            maxAttempts = this._maxAttempts,
            dedupeKey = null,
            onRetry = null,
            abortSignal = null,
        } = options;

        // Check network status
        if (!this._isOnline) {
            throw new NetworkRetryError('Network offline', 'OFFLINE', { retryable: true });
        }

        // Check circuit breaker
        if (!this._checkCircuitBreaker()) {
            throw new NetworkRetryError(
                'Circuit breaker open - too many failures',
                'CIRCUIT_OPEN',
                { retryable: false, retryAfter: this._getCircuitResetTime() }
            );
        }

        // Handle request deduplication
        if (dedupeKey && this._pendingRequests.has(dedupeKey)) {
            this._log(`Deduplicating request: ${dedupeKey}`);
            return this._pendingRequests.get(dedupeKey);
        }

        // Create the request promise
        const requestPromise = this._executeWithRetry(requestFn, {
            maxAttempts,
            onRetry,
            abortSignal,
            attempt: 1,
        });

        // Store for deduplication
        if (dedupeKey) {
            this._pendingRequests.set(dedupeKey, requestPromise);

            // Clean up after completion
            requestPromise.finally(() => {
                this._pendingRequests.delete(dedupeKey);
            });
        }

        return requestPromise;
    }

    /**
     * Execute request with retry logic
     * @private
     */
    async _executeWithRetry(requestFn, options) {
        const { maxAttempts, onRetry, abortSignal, attempt } = options;

        try {
            // Check if aborted
            if (abortSignal?.aborted) {
                throw new NetworkRetryError('Request aborted', 'ABORTED', { retryable: false });
            }

            // Execute with timeout
            const result = await this._executeWithTimeout(requestFn, abortSignal);

            // Success - reset circuit breaker
            this._onSuccess();

            return result;
        } catch (error) {
            // Check if we should retry
            const shouldRetry = this._shouldRetry(error, attempt, maxAttempts);

            if (!shouldRetry) {
                // Final failure
                this._onFailure(error);
                throw this._wrapError(error);
            }

            // Calculate delay with exponential backoff and jitter
            const delay = this._calculateDelay(attempt);

            this._log(`Retry attempt ${attempt}/${maxAttempts} after ${delay}ms`, {
                error: error.message,
                status: error.response?.status,
            });

            // Call retry callback if provided
            if (onRetry) {
                try {
                    onRetry({ attempt, maxAttempts, delay, error });
                } catch (e) {
                    // Ignore callback errors
                }
            }

            // Wait before retry
            await this._sleep(delay);

            // Recursive retry
            return this._executeWithRetry(requestFn, {
                maxAttempts,
                onRetry,
                abortSignal,
                attempt: attempt + 1,
            });
        }
    }

    /**
     * Execute request with timeout
     * @private
     */
    async _executeWithTimeout(requestFn, abortSignal) {
        return new Promise(async (resolve, reject) => {
            let timeoutId;
            let completed = false;

            // Setup timeout
            const timeoutPromise = new Promise((_, timeoutReject) => {
                timeoutId = setTimeout(() => {
                    if (!completed) {
                        timeoutReject(new NetworkRetryError(
                            `Request timeout after ${this._requestTimeout}ms`,
                            'TIMEOUT',
                            { retryable: true }
                        ));
                    }
                }, this._requestTimeout);
            });

            // Setup abort handler
            if (abortSignal) {
                abortSignal.addEventListener('abort', () => {
                    if (!completed) {
                        completed = true;
                        clearTimeout(timeoutId);
                        reject(new NetworkRetryError('Request aborted', 'ABORTED', { retryable: false }));
                    }
                });
            }

            try {
                // Race between request and timeout
                const result = await Promise.race([
                    requestFn(),
                    timeoutPromise,
                ]);

                completed = true;
                clearTimeout(timeoutId);
                resolve(result);
            } catch (error) {
                completed = true;
                clearTimeout(timeoutId);
                reject(error);
            }
        });
    }

    /**
     * Determine if error should trigger retry
     * @private
     */
    _shouldRetry(error, attempt, maxAttempts) {
        // Check attempt count
        if (attempt >= maxAttempts) {
            return false;
        }

        // Check if error is explicitly non-retryable
        if (error.retryable === false) {
            return false;
        }

        // Check HTTP status codes
        if (error.response?.status) {
            const status = error.response.status;

            // 4xx errors (except specific ones) are not retryable
            if (status >= 400 && status < 500) {
                return this._retryableStatusCodes.includes(status);
            }

            // 5xx errors are retryable
            if (status >= 500) {
                return this._retryableStatusCodes.includes(status);
            }
        }

        // Check error codes
        if (error.code && this._retryableErrors.includes(error.code)) {
            return true;
        }

        // Check for network errors
        if (error.message?.includes('Network Error') ||
            error.message?.includes('network') ||
            error.message?.includes('ECONNREFUSED')) {
            return true;
        }

        // Check for timeout errors
        if (error.code === 'TIMEOUT' || error.message?.includes('timeout')) {
            return true;
        }

        // Default: retry for unknown errors
        return true;
    }

    /**
     * Calculate delay with exponential backoff and jitter
     * @private
     */
    _calculateDelay(attempt) {
        // Exponential backoff: baseDelay * (multiplier ^ attempt)
        let delay = this._baseDelay * Math.pow(this._backoffMultiplier, attempt - 1);

        // Cap at max delay
        delay = Math.min(delay, this._maxDelay);

        // Add jitter (randomness to prevent thundering herd)
        const jitter = delay * this._jitterFactor * Math.random();
        delay = delay + jitter;

        return Math.round(delay);
    }

    /**
     * Check circuit breaker state
     * @private
     * @returns {boolean} Whether request should proceed
     */
    _checkCircuitBreaker() {
        if (!this._circuitBreakerEnabled) {
            return true;
        }

        switch (this._circuitState) {
            case CIRCUIT_STATES.CLOSED:
                return true;

            case CIRCUIT_STATES.OPEN:
                // Check if reset timeout has passed
                if (Date.now() - this._lastFailureTime >= this._circuitResetTimeout) {
                    this._circuitState = CIRCUIT_STATES.HALF_OPEN;
                    this._log('Circuit breaker: half-open (testing)');
                    return true;
                }
                return false;

            case CIRCUIT_STATES.HALF_OPEN:
                // Allow one test request
                return true;

            default:
                return true;
        }
    }

    /**
     * Get time until circuit breaker resets
     * @private
     */
    _getCircuitResetTime() {
        if (this._circuitState !== CIRCUIT_STATES.OPEN) {
            return 0;
        }

        const elapsed = Date.now() - this._lastFailureTime;
        return Math.max(0, this._circuitResetTimeout - elapsed);
    }

    /**
     * Handle successful request
     * @private
     */
    _onSuccess() {
        this._failureCount = 0;

        if (this._circuitState === CIRCUIT_STATES.HALF_OPEN) {
            this._circuitState = CIRCUIT_STATES.CLOSED;
            this._log('Circuit breaker: closed (recovered)');
        }
    }

    /**
     * Handle failed request
     * @private
     */
    _onFailure(error) {
        this._failureCount++;
        this._lastFailureTime = Date.now();

        this._log(`Failure count: ${this._failureCount}/${this._circuitFailureThreshold}`);

        // Check if we should open circuit breaker
        if (this._circuitBreakerEnabled &&
            this._failureCount >= this._circuitFailureThreshold) {
            this._circuitState = CIRCUIT_STATES.OPEN;
            this._log('Circuit breaker: open (too many failures)');
        }
    }

    /**
     * Wrap error with additional context
     * @private
     */
    _wrapError(error) {
        if (error instanceof NetworkRetryError) {
            return error;
        }

        const code = error.code ||
            (error.response?.status ? `HTTP_${error.response.status}` : 'UNKNOWN');

        return new NetworkRetryError(
            error.message || 'Request failed',
            code,
            {
                originalError: error,
                status: error.response?.status,
                data: error.response?.data,
                retryable: this._shouldRetry(error, 1, 2), // Check if it was retryable
            }
        );
    }

    /**
     * Sleep for specified duration
     * @private
     */
    _sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Debug logging
     * @private
     */
    _log(message, data) {
        if (this._debug) {
            if (data) {
                console.debug(`[NetworkRetry] ${message}`, data);
            } else {
                console.debug(`[NetworkRetry] ${message}`);
            }
        }
    }

    /**
     * Get current status
     * @returns {Object} Status information
     */
    getStatus() {
        return {
            isOnline: this._isOnline,
            circuitState: this._circuitState,
            failureCount: this._failureCount,
            pendingRequests: this._pendingRequests.size,
            circuitResetTime: this._getCircuitResetTime(),
        };
    }

    /**
     * Reset circuit breaker manually
     */
    resetCircuitBreaker() {
        this._circuitState = CIRCUIT_STATES.CLOSED;
        this._failureCount = 0;
        this._lastFailureTime = null;
        this._log('Circuit breaker manually reset');
    }

    /**
     * Check if currently online
     * @returns {boolean}
     */
    isOnline() {
        return this._isOnline;
    }

    /**
     * Wait for network to come back online
     * @param {number} timeout - Maximum wait time in ms
     * @returns {Promise<boolean>} Whether network is online
     */
    async waitForOnline(timeout = 30000) {
        if (this._isOnline) {
            return true;
        }

        return new Promise((resolve) => {
            const startTime = Date.now();

            const checkOnline = () => {
                if (this._isOnline) {
                    resolve(true);
                    return;
                }

                if (Date.now() - startTime >= timeout) {
                    resolve(false);
                    return;
                }

                setTimeout(checkOnline, 1000);
            };

            checkOnline();
        });
    }

    /**
     * Destroy the instance
     */
    destroy() {
        this._pendingRequests.clear();
    }
}

/**
 * Custom error class for network retry errors
 */
export class NetworkRetryError extends Error {
    constructor(message, code, options = {}) {
        super(message);
        this.name = 'NetworkRetryError';
        this.code = code;
        this.retryable = options.retryable !== undefined ? options.retryable : true;
        this.retryAfter = options.retryAfter || null;
        this.originalError = options.originalError || null;
        this.status = options.status || null;
        this.data = options.data || null;

        // Capture stack trace
        if (Error.captureStackTrace) {
            Error.captureStackTrace(this, NetworkRetryError);
        }
    }

    /**
     * Check if error is due to being offline
     */
    isOffline() {
        return this.code === 'OFFLINE';
    }

    /**
     * Check if error is due to timeout
     */
    isTimeout() {
        return this.code === 'TIMEOUT';
    }

    /**
     * Check if error is due to abort
     */
    isAborted() {
        return this.code === 'ABORTED';
    }

    /**
     * Check if error is due to circuit breaker
     */
    isCircuitOpen() {
        return this.code === 'CIRCUIT_OPEN';
    }

    /**
     * Get HTTP status code if available
     */
    getHttpStatus() {
        return this.status || this.originalError?.response?.status || null;
    }
}

// Export factory function
export function createNetworkRetry(options = {}) {
    return new NetworkRetry(options);
}

export default NetworkRetry;
