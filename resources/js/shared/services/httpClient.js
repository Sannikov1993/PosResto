/**
 * HTTP Client Factory
 *
 * Enterprise-level factory для создания axios-инстансов
 * с централизованной авторизацией, обработкой ошибок и нормализацией ответов.
 *
 * Все модули (admin, reservations, courier, floor-editor и др.)
 * должны использовать этот factory вместо создания своих axios-инстансов.
 *
 * @module shared/services/httpClient
 */

import axios from 'axios';
import authService from './auth.js';
import { createLogger } from './logger.js';

const DEFAULT_BASE_URL = '/api';

/**
 * Создать настроенный HTTP-клиент (axios instance)
 * @param {Object} options
 * @param {string} options.module - Название модуля для логирования
 * @param {string} options.baseURL - Базовый URL (по умолчанию '/api')
 * @returns {Object} { http, extractArray, extractData }
 */
export function createHttpClient(options = {}) {
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

    // Request interceptor — Bearer token из централизованного auth сервиса
    http.interceptors.request.use(config => {
        const authHeader = authService.getAuthHeader();
        if (authHeader) {
            config.headers.Authorization = authHeader;
        }
        return config;
    });

    // Response interceptor — нормализация ответов
    http.interceptors.response.use(
        response => {
            const data = response.data;

            // Если API вернул success: false — ошибка бизнес-логики
            if (data?.success === false) {
                const error = new Error(data.message || 'API Error');
                error.response = { data };
                error.isApiError = true;
                throw error;
            }

            return data;
        },
        error => {
            log.error('Request failed:', error.response?.data?.message || error.message);
            throw error;
        }
    );

    // Helpers для извлечения данных из ответа
    const extractArray = (response) => {
        if (Array.isArray(response)) return response;
        if (response?.data && Array.isArray(response.data)) return response.data;
        return response || [];
    };

    const extractData = (response) => {
        if (response?.data !== undefined) return response.data;
        return response;
    };

    return { http, extractArray, extractData };
}

export default createHttpClient;
