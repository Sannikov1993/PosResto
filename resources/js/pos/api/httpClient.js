/**
 * POS HTTP Client — axios instance с interceptors и 401 retry logic
 */

import axios from 'axios';
import authService from '../../shared/services/auth';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('POS:HTTP');

const API_BASE = '/api';

// Create axios instance
const http = axios.create({
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
let failedQueue = [];

function processQueue(error, token = null) {
    failedQueue.forEach(({ resolve, reject }) => {
        if (error) {
            reject(error);
        } else {
            resolve(token);
        }
    });
    failedQueue = [];
}

// Response interceptor
http.interceptors.response.use(
    response => {
        const data = response.data;

        // Если API явно вернул success: false — ошибка бизнес-логики
        if (data?.success === false) {
            const error = new Error(data.message || 'API Error');
            error.response = { data };
            error.isApiError = true;
            throw error;
        }

        return data;
    },
    async error => {
        const originalRequest = error.config;

        // 401: retry-once с ревалидацией токена
        if (error.response?.status === 401 && !originalRequest._retry) {
            // Помечаем ДО проверки isRefreshing — предотвращает повторный refresh
            // при retry запросов из очереди
            originalRequest._retry = true;

            if (isRefreshing) {
                return new Promise((resolve, reject) => {
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
            } catch (refreshError) {
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
export const extractArray = (response) => {
    if (Array.isArray(response)) return response;
    if (response?.data && Array.isArray(response.data)) return response.data;
    return response || [];
};

/** Извлекает объект из ответа { data: {...} } или возвращает как есть */
export const extractData = (response) => {
    if (response?.data !== undefined) return response.data;
    return response;
};

export { http, axios, API_BASE };
export default http;
