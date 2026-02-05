/**
 * Kitchen API Client
 *
 * Base API client with retry logic, error handling,
 * and request/response interceptors.
 *
 * @module kitchen/services/api/kitchenApi
 */

import axios from 'axios';
import { REQUEST_CONFIG, isRetryableStatus } from '../../constants/api.js';
import { KitchenApiError } from './errors.js';
import { createLogger } from '../../../shared/services/logger.js';

const log = createLogger('KitchenAPI');

/**
 * Kitchen API Client class
 * Provides HTTP methods with automatic retry and error handling
 */
export class KitchenApiClient {
    /**
     * @param {Object} [options] - Configuration options
     * @param {number} [options.timeout] - Request timeout in ms
     * @param {number} [options.maxRetries] - Max retry attempts
     * @param {number} [options.retryBaseDelay] - Base delay for exponential backoff
     * @param {boolean} [options.debug] - Enable debug logging
     */
    constructor(options = {}) {
        this.timeout = options.timeout ?? REQUEST_CONFIG.TIMEOUT;
        this.maxRetries = options.maxRetries ?? REQUEST_CONFIG.MAX_RETRIES;
        this.retryBaseDelay = options.retryBaseDelay ?? REQUEST_CONFIG.RETRY_BASE_DELAY;
        this.retryMaxDelay = options.retryMaxDelay ?? REQUEST_CONFIG.RETRY_MAX_DELAY;
        this.debug = options.debug ?? false;

        // Create axios instance
        this.client = axios.create({
            timeout: this.timeout,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });

        // Request interceptor
        this.client.interceptors.request.use(
            (config) => this._onRequest(config),
            (error) => Promise.reject(error)
        );

        // Response interceptor
        this.client.interceptors.response.use(
            (response) => this._onResponse(response),
            (error) => this._onResponseError(error)
        );

        // Pending requests for deduplication
        this._pendingRequests = new Map();
    }

    /**
     * Request interceptor - add auth headers if available
     * @private
     */
    _onRequest(config) {
        // Try to get auth token from localStorage
        const token = localStorage.getItem('backoffice_token') ||
                      localStorage.getItem('pos_token');

        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        if (this.debug) {
            log.debug(`${config.method?.toUpperCase()} ${config.url}`);
        }

        return config;
    }

    /**
     * Response interceptor - extract data
     * @private
     */
    _onResponse(response) {
        return response;
    }

    /**
     * Response error interceptor
     * @private
     */
    _onResponseError(error) {
        // Convert to our error format
        throw KitchenApiError.fromAxiosError(error);
    }

    /**
     * Calculate retry delay with exponential backoff and jitter
     * @param {number} attempt - Current attempt number (0-based)
     * @returns {number} Delay in milliseconds
     */
    _calculateRetryDelay(attempt) {
        const exponentialDelay = this.retryBaseDelay * Math.pow(2, attempt);
        const jitter = Math.random() * 0.3 * exponentialDelay;
        return Math.min(exponentialDelay + jitter, this.retryMaxDelay);
    }

    /**
     * Sleep for specified duration
     * @param {number} ms - Milliseconds to sleep
     * @returns {Promise<void>}
     */
    _sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Execute request with retry logic
     * @template T
     * @param {Function} requestFn - Function that returns a promise
     * @param {Object} [options] - Options
     * @param {number} [options.maxRetries] - Override max retries
     * @param {string} [options.dedupeKey] - Key for request deduplication
     * @returns {Promise<T>}
     */
    async _executeWithRetry(requestFn, options = {}) {
        const maxRetries = options.maxRetries ?? this.maxRetries;
        const dedupeKey = options.dedupeKey;

        // Check for pending request with same key
        if (dedupeKey && this._pendingRequests.has(dedupeKey)) {
            return this._pendingRequests.get(dedupeKey);
        }

        const execute = async () => {
            let lastError;

            for (let attempt = 0; attempt <= maxRetries; attempt++) {
                try {
                    const response = await requestFn();
                    return response.data;
                } catch (error) {
                    lastError = error;

                    // Don't retry non-retryable errors
                    if (error instanceof KitchenApiError && !error.isRetryable()) {
                        throw error;
                    }

                    // Check status for retry decision
                    const status = error.status ?? error.response?.status ?? 0;
                    if (!isRetryableStatus(status)) {
                        throw error;
                    }

                    // Don't retry after last attempt
                    if (attempt === maxRetries) {
                        throw error;
                    }

                    // Wait before retry
                    const delay = this._calculateRetryDelay(attempt);
                    if (this.debug) {
                        log.debug(`Retry ${attempt + 1}/${maxRetries} after ${delay}ms`);
                    }
                    await this._sleep(delay);
                }
            }

            throw lastError;
        };

        // Store pending request for deduplication
        const promise = execute().finally(() => {
            if (dedupeKey) {
                this._pendingRequests.delete(dedupeKey);
            }
        });

        if (dedupeKey) {
            this._pendingRequests.set(dedupeKey, promise);
        }

        return promise;
    }

    /**
     * Make GET request
     * @template T
     * @param {string} url - Request URL
     * @param {Object} [params] - Query parameters
     * @param {Object} [options] - Request options
     * @returns {Promise<T>}
     */
    async get(url, params = {}, options = {}) {
        return this._executeWithRetry(
            () => this.client.get(url, { params }),
            options
        );
    }

    /**
     * Make POST request
     * @template T
     * @param {string} url - Request URL
     * @param {Object} [data] - Request body
     * @param {Object} [options] - Request options
     * @returns {Promise<T>}
     */
    async post(url, data = {}, options = {}) {
        return this._executeWithRetry(
            () => this.client.post(url, data),
            { ...options, maxRetries: 0 } // Don't retry POST by default
        );
    }

    /**
     * Make PATCH request
     * @template T
     * @param {string} url - Request URL
     * @param {Object} [data] - Request body
     * @param {Object} [options] - Request options
     * @returns {Promise<T>}
     */
    async patch(url, data = {}, options = {}) {
        return this._executeWithRetry(
            () => this.client.patch(url, data),
            { ...options, maxRetries: 0 } // Don't retry PATCH by default
        );
    }

    /**
     * Make PUT request
     * @template T
     * @param {string} url - Request URL
     * @param {Object} [data] - Request body
     * @param {Object} [options] - Request options
     * @returns {Promise<T>}
     */
    async put(url, data = {}, options = {}) {
        return this._executeWithRetry(
            () => this.client.put(url, data),
            { ...options, maxRetries: 0 } // Don't retry PUT by default
        );
    }

    /**
     * Make DELETE request
     * @template T
     * @param {string} url - Request URL
     * @param {Object} [options] - Request options
     * @returns {Promise<T>}
     */
    async delete(url, options = {}) {
        return this._executeWithRetry(
            () => this.client.delete(url),
            { ...options, maxRetries: 0 } // Don't retry DELETE by default
        );
    }
}

// Singleton instance
export const kitchenApi = new KitchenApiClient({
    debug: import.meta.env?.DEV ?? false,
});
