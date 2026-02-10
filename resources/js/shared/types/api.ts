/**
 * Shared API Types
 *
 * Generic API request/response types used across all MenuLab modules.
 */

// ============================================================
// BASE RESPONSE TYPES
// ============================================================

export interface ApiResponse<T> {
    success: boolean;
    data: T;
    message?: string;
}

export interface ApiErrorResponse {
    success: false;
    message: string;
    errors?: Record<string, string[]>;
    code?: string;
}

export interface PaginatedResponse<T> {
    success: boolean;
    data: T[];
    meta: PaginationMeta;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

// ============================================================
// AUTH API
// ============================================================

import type { User, Restaurant, CashShift } from './models';

export interface LoginByPinRequest {
    pin: string;
    device_token?: string;
}

export interface LoginByEmailRequest {
    email: string;
    password: string;
    device_token?: string;
}

export type LoginRequest = LoginByPinRequest | LoginByEmailRequest;

export interface LoginResponse {
    user: User;
    token: string;
    restaurant: Restaurant;
    permissions: string[];
    limits?: Record<string, number>;
    interface_access?: Record<string, boolean>;
    pos_modules?: string[];
    backoffice_modules?: string[];
}

export interface MeResponse {
    user: User;
    restaurant: Restaurant;
    permissions: string[];
    shift?: CashShift;
}

// ============================================================
// UTILITY TYPES
// ============================================================

export type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface RequestConfig {
    method?: ApiMethod;
    params?: Record<string, unknown>;
    data?: Record<string, unknown>;
    headers?: Record<string, string>;
    timeout?: number;
}

export interface ApiError {
    type: 'validation' | 'network' | 'auth' | 'server' | 'unknown';
    message: string;
    status?: number;
    errors?: Record<string, string[]>;
    originalError?: unknown;
}
