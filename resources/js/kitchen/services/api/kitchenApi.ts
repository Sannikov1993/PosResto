/**
 * Kitchen API Client
 *
 * Base API client with retry logic, error handling,
 * and request/response interceptors.
 *
 * @module kitchen/services/api/kitchenApi
 */

import axios from 'axios';
import type { AxiosInstance, InternalAxiosRequestConfig } from 'axios';
import { REQUEST_CONFIG, isRetryableStatus } from '../../constants/api.js';
import { KitchenApiError } from './errors.js';
import { createLogger } from '../../../shared/services/logger.js';
import type { KitchenApiClientOptions, ExecuteOptions } from '../../types/index.js';

const log = createLogger('KitchenAPI');

export class KitchenApiClient {
    timeout: number;
    maxRetries: number;
    retryBaseDelay: number;
    retryMaxDelay: number;
    debug: boolean;
    client: AxiosInstance;
    private _pendingRequests: Map<string, Promise<unknown>>;

    constructor(options: KitchenApiClientOptions = {}) {
        this.timeout = options.timeout ?? REQUEST_CONFIG.TIMEOUT;
        this.maxRetries = options.maxRetries ?? REQUEST_CONFIG.MAX_RETRIES;
        this.retryBaseDelay = options.retryBaseDelay ?? REQUEST_CONFIG.RETRY_BASE_DELAY;
        this.retryMaxDelay = options.retryMaxDelay ?? REQUEST_CONFIG.RETRY_MAX_DELAY;
        this.debug = options.debug ?? false;

        this.client = axios.create({
            timeout: this.timeout,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });

        this.client.interceptors.request.use(
            (config) => this._onRequest(config),
            (error) => Promise.reject(error)
        );

        this.client.interceptors.response.use(
            (response) => this._onResponse(response) as any,
            (error) => this._onResponseError(error)
        );

        this._pendingRequests = new Map();
    }

    private _onRequest(config: InternalAxiosRequestConfig): InternalAxiosRequestConfig {
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

    private _onResponse(response: unknown): unknown {
        return response;
    }

    private _onResponseError(error: unknown): never {
        throw KitchenApiError.fromAxiosError(error as any);
    }

    private _calculateRetryDelay(attempt: number): number {
        const exponentialDelay = this.retryBaseDelay * Math.pow(2, attempt);
        const jitter = Math.random() * 0.3 * exponentialDelay;
        return Math.min(exponentialDelay + jitter, this.retryMaxDelay);
    }

    private _sleep(ms: number): Promise<void> {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    private async _executeWithRetry<T>(requestFn: () => Promise<any>, options: ExecuteOptions = {}): Promise<T> {
        const maxRetries = options.maxRetries ?? this.maxRetries;
        const dedupeKey = options.dedupeKey;

        if (dedupeKey && this._pendingRequests.has(dedupeKey)) {
            return this._pendingRequests.get(dedupeKey) as Promise<T>;
        }

        const execute = async (): Promise<T> => {
            let lastError: unknown;

            for (let attempt = 0; attempt <= maxRetries; attempt++) {
                try {
                    const response = await requestFn();
                    return response.data;
                } catch (error: any) {
                    lastError = error;

                    if (error instanceof KitchenApiError && !error.isRetryable()) {
                        throw error;
                    }

                    const status = (error as any).status ?? (error as any).response?.status ?? 0;
                    if (!isRetryableStatus(status)) {
                        throw error;
                    }

                    if (attempt === maxRetries) {
                        throw error;
                    }

                    const delay = this._calculateRetryDelay(attempt);
                    if (this.debug) {
                        log.debug(`Retry ${attempt + 1}/${maxRetries} after ${delay}ms`);
                    }
                    await this._sleep(delay);
                }
            }

            throw lastError;
        };

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

    async get<T = any>(url: string, params: Record<string, any> = {}, options: ExecuteOptions = {}): Promise<T> {
        return this._executeWithRetry<T>(
            () => this.client.get(url, { params }),
            options
        );
    }

    async post<T = any>(url: string, data: Record<string, any> = {}, options: ExecuteOptions = {}): Promise<T> {
        return this._executeWithRetry<T>(
            () => this.client.post(url, data),
            { ...options, maxRetries: 0 }
        );
    }

    async patch<T = any>(url: string, data: Record<string, any> = {}, options: ExecuteOptions = {}): Promise<T> {
        return this._executeWithRetry<T>(
            () => this.client.patch(url, data),
            { ...options, maxRetries: 0 }
        );
    }

    async put<T = any>(url: string, data: Record<string, any> = {}, options: ExecuteOptions = {}): Promise<T> {
        return this._executeWithRetry<T>(
            () => this.client.put(url, data),
            { ...options, maxRetries: 0 }
        );
    }

    async delete<T = any>(url: string, options: ExecuteOptions = {}): Promise<T> {
        return this._executeWithRetry<T>(
            () => this.client.delete(url),
            { ...options, maxRetries: 0 }
        );
    }
}

export const kitchenApi = new KitchenApiClient({
    debug: import.meta.env?.DEV ?? false,
});
