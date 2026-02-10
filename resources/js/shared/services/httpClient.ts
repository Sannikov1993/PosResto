/**
 * HTTP Client Factory
 *
 * Enterprise-level factory for creating axios instances
 * with centralized auth, error handling, and response normalization.
 *
 * @module shared/services/httpClient
 */

import axios, { type AxiosInstance, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios';
import authService from './auth.js';
import { createLogger } from './logger.js';

const DEFAULT_BASE_URL = '/api';

export interface HttpClientOptions {
    module?: string;
    baseURL?: string;
}

export interface HttpClientResult {
    http: AxiosInstance;
    extractArray: <T>(response: unknown) => T[];
    extractData: <T>(response: unknown) => T;
}

interface ApiErrorExtended extends Error {
    response?: { data: unknown };
    isApiError?: boolean;
}

export function createHttpClient(options: HttpClientOptions = {}): HttpClientResult {
    const {
        module = 'API',
        baseURL = DEFAULT_BASE_URL,
    } = options;

    const log = createLogger(module);

    const http = axios.create({
        baseURL,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
    });

    http.interceptors.request.use((config: InternalAxiosRequestConfig) => {
        const authHeader = authService.getAuthHeader();
        if (authHeader) {
            config.headers.Authorization = authHeader;
        }
        return config;
    });

    http.interceptors.response.use(
        (response: AxiosResponse) => {
            const data = response.data;

            if (data?.success === false) {
                const error: ApiErrorExtended = new Error(data.message || 'API Error');
                error.response = { data };
                error.isApiError = true;
                throw error;
            }

            return data;
        },
        (error: unknown) => {
            const axiosError = error as { response?: { data?: { message?: string } }; message?: string };
            log.error('Request failed:', axiosError.response?.data?.message || axiosError.message);
            throw error;
        }
    );

    const extractArray = <T>(response: unknown): T[] => {
        if (Array.isArray(response)) return response as T[];
        const resp = response as Record<string, any> | null;
        if (resp?.data && Array.isArray(resp.data)) return resp.data as T[];
        return (response || []) as T[];
    };

    const extractData = <T>(response: unknown): T => {
        const resp = response as Record<string, any> | null;
        if (resp?.data !== undefined) return resp.data as T;
        return response as T;
    };

    return { http, extractArray, extractData };
}

export default createHttpClient;
