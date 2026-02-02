/**
 * Waiter App - API Client
 * Axios instance with interceptors for auth, error handling, and retries
 */

import axios, {
  AxiosInstance,
  AxiosError,
  AxiosResponse,
  InternalAxiosRequestConfig,
} from 'axios';
import type { ApiError, ApiErrorResponse } from '@/waiter/types';

// === Configuration ===

const API_BASE_URL = '/api';
const REQUEST_TIMEOUT = 30000; // 30 seconds
const MAX_RETRIES = 2;
const RETRY_DELAY = 1000; // 1 second

// === Token Management ===

const TOKEN_KEY = 'api_token';

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token);
}

export function removeToken(): void {
  localStorage.removeItem(TOKEN_KEY);
}

export function hasToken(): boolean {
  return !!getToken();
}

// === CSRF Token ===

function getCsrfToken(): string | null {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta?.getAttribute('content') || null;
}

// === Error Parsing ===

function parseApiError(error: AxiosError<ApiErrorResponse>): ApiError {
  // Network error (no response)
  if (!error.response) {
    return {
      type: 'network',
      message: 'Нет соединения с сервером',
      originalError: error,
    };
  }

  const { status, data } = error.response;

  // Authentication error
  if (status === 401) {
    return {
      type: 'auth',
      message: data?.message || 'Сессия истекла. Войдите снова.',
      status,
      originalError: error,
    };
  }

  // Validation error
  if (status === 422) {
    return {
      type: 'validation',
      message: data?.message || 'Ошибка валидации',
      status,
      errors: data?.errors,
      originalError: error,
    };
  }

  // Forbidden
  if (status === 403) {
    return {
      type: 'auth',
      message: data?.message || 'Недостаточно прав',
      status,
      originalError: error,
    };
  }

  // Not found
  if (status === 404) {
    return {
      type: 'server',
      message: data?.message || 'Ресурс не найден',
      status,
      originalError: error,
    };
  }

  // Server error
  if (status >= 500) {
    return {
      type: 'server',
      message: 'Ошибка сервера. Попробуйте позже.',
      status,
      originalError: error,
    };
  }

  // Other errors
  return {
    type: 'unknown',
    message: data?.message || 'Произошла ошибка',
    status,
    originalError: error,
  };
}

// === Retry Logic ===

function shouldRetry(error: AxiosError, retryCount: number): boolean {
  // Don't retry if max retries reached
  if (retryCount >= MAX_RETRIES) return false;

  // Don't retry on client errors (4xx) except timeout
  if (error.response && error.response.status < 500 && error.response.status !== 408) {
    return false;
  }

  // Retry on network errors or server errors
  return !error.response || error.response.status >= 500;
}

function delay(ms: number): Promise<void> {
  return new Promise(resolve => setTimeout(resolve, ms));
}

// === Create Client ===

function createApiClient(): AxiosInstance {
  const client = axios.create({
    baseURL: API_BASE_URL,
    timeout: REQUEST_TIMEOUT,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  // === Request Interceptor ===
  client.interceptors.request.use(
    (config: InternalAxiosRequestConfig) => {
      // Add auth token
      const token = getToken();
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }

      // Add CSRF token
      const csrfToken = getCsrfToken();
      if (csrfToken) {
        config.headers['X-CSRF-TOKEN'] = csrfToken;
      }

      // Add retry count to config
      (config as any).__retryCount = (config as any).__retryCount || 0;

      return config;
    },
    (error) => Promise.reject(error)
  );

  // === Response Interceptor ===
  client.interceptors.response.use(
    (response: AxiosResponse) => response,
    async (error: AxiosError<ApiErrorResponse>) => {
      const config = error.config as InternalAxiosRequestConfig & { __retryCount?: number };

      // Handle 401 - clear token and redirect
      if (error.response?.status === 401) {
        removeToken();
        // Emit event for app to handle
        window.dispatchEvent(new CustomEvent('auth:logout', {
          detail: { reason: 'session_expired' }
        }));
      }

      // Retry logic
      const retryCount = config?.__retryCount || 0;
      if (config && shouldRetry(error, retryCount)) {
        config.__retryCount = retryCount + 1;
        await delay(RETRY_DELAY * (retryCount + 1)); // Exponential backoff
        return client(config);
      }

      // Parse and reject with structured error
      const apiError = parseApiError(error);
      return Promise.reject(apiError);
    }
  );

  return client;
}

// === Export Client Instance ===

export const apiClient = createApiClient();

// === Typed API Methods ===

export const api = {
  /**
   * GET request
   */
  get: <T>(url: string, params?: Record<string, any>): Promise<T> =>
    apiClient.get<T>(url, { params }).then(res => res.data),

  /**
   * POST request
   */
  post: <T>(url: string, data?: Record<string, any>): Promise<T> =>
    apiClient.post<T>(url, data).then(res => res.data),

  /**
   * PUT request
   */
  put: <T>(url: string, data?: Record<string, any>): Promise<T> =>
    apiClient.put<T>(url, data).then(res => res.data),

  /**
   * PATCH request
   */
  patch: <T>(url: string, data?: Record<string, any>): Promise<T> =>
    apiClient.patch<T>(url, data).then(res => res.data),

  /**
   * DELETE request
   */
  delete: <T>(url: string): Promise<T> =>
    apiClient.delete<T>(url).then(res => res.data),
};

// === Utility Exports ===

export { parseApiError };
