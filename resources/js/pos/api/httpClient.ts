/**
 * POS HTTP Client — axios instance с interceptors и 401 retry logic
 */

import axios, { type AxiosInstance, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios';
import authService from '../../shared/services/auth.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('POS:HTTP');

const API_BASE = '/api';

interface QueueItem {
    resolve: (token: string) => void;
    reject: (error: unknown) => void;
}

interface ApiErrorLike extends Error {
    response?: { data: unknown };
    isApiError?: boolean;
}

interface RetryAxiosRequestConfig extends InternalAxiosRequestConfig {
    _retry?: boolean;
}

// Create axios instance
const http: AxiosInstance = axios.create({
    baseURL: API_BASE,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// Request interceptor — добавляем Bearer токен из централизованного auth сервиса
http.interceptors.request.use(config => {
    const authHeader = authService.getAuthHeader();
    if (authHeader) {
        config.headers.Authorization = authHeader;
    }
    return config;
});

// ==================== 401 RETRY LOGIC ====================
let isRefreshing = false;
let failedQueue: QueueItem[] = [];

function processQueue(error: unknown, token: string | null = null): void {
    failedQueue.forEach(({ resolve, reject }) => {
        if (error) {
            reject(error);
        } else {
            resolve(token!);
        }
    });
    failedQueue = [];
}

// Response interceptor
http.interceptors.response.use(
    (response: AxiosResponse) => {
        const data = response.data;

        // Если API явно вернул success: false — ошибка бизнес-логики
        if (data?.success === false) {
            const error: ApiErrorLike = new Error(data.message || 'API Error');
            error.response = { data };
            error.isApiError = true;
            throw error;
        }

        return data;
    },
    async error => {
        const originalRequest = error.config as RetryAxiosRequestConfig;

        // 401: retry-once с ревалидацией токена
        if (error.response?.status === 401 && !originalRequest._retry) {
            originalRequest._retry = true;

            if (isRefreshing) {
                return new Promise<string>((resolve, reject) => {
                    failedQueue.push({ resolve, reject });
                }).then(token => {
                    originalRequest.headers.Authorization = `Bearer ${token}`;
                    return http(originalRequest);
                });
            }

            isRefreshing = true;

            try {
                const session = authService.getSession();
                if (session?.token) {
                    const checkResponse = await axios.get(`${API_BASE}/auth/check`, {
                        headers: { Authorization: `Bearer ${session.token}` }
                    });

                    if (checkResponse.data?.success) {
                        log.info('Token revalidated, retrying request');
                        processQueue(null, session.token);
                        originalRequest.headers.Authorization = `Bearer ${session.token}`;
                        return http(originalRequest);
                    }
                }

                throw new Error('Token expired');
            } catch (refreshError: any) {
                processQueue(refreshError);
                log.error('Session expired (401), token invalid — logging out');
                authService.clearAuth();
                window.dispatchEvent(new Event('auth:session-expired'));
                return Promise.reject(error);
            } finally {
                isRefreshing = false;
            }
        }

        log.error('API Error', error.response?.data || error.message);
        throw error;
    }
);

// ==================== HELPERS ====================

/** Извлекает массив из ответа { data: [...] } или возвращает как есть */
export const extractArray = <T = unknown>(response: unknown): T[] => {
    if (Array.isArray(response)) return response;
    if (response && typeof response === 'object' && 'data' in response && Array.isArray((response as Record<string, any>).data)) {
        return (response as Record<string, any>).data as T[];
    }
    return (response || []) as T[];
};

/** Извлекает объект из ответа { data: {...} } или возвращает как есть */
export const extractData = <T = unknown>(response: unknown): T => {
    if (response && typeof response === 'object' && 'data' in response) {
        return (response as Record<string, any>).data as T;
    }
    return response as T;
};

export { http, axios, API_BASE };
export default http;
