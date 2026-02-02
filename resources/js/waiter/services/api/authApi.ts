/**
 * Waiter App - Auth API Service
 * Handles authentication and user-related API calls
 */

import { api, setToken, removeToken } from './client';
import type {
  ApiResponse,
  LoginRequest,
  LoginResponse,
  MeResponse,
  User,
} from '@/waiter/types';

// === Device Token Generation ===

function generateDeviceToken(): string {
  const ua = navigator.userAgent;
  const screen = `${window.screen.width}x${window.screen.height}`;
  const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
  const language = navigator.language;
  const timestamp = Date.now().toString(36);

  const raw = `${ua}:${screen}:${timezone}:${language}:${timestamp}`;

  // Simple hash (for unique device identification)
  let hash = 0;
  for (let i = 0; i < raw.length; i++) {
    const char = raw.charCodeAt(i);
    hash = ((hash << 5) - hash) + char;
    hash = hash & hash;
  }

  return `waiter_${Math.abs(hash).toString(36)}_${timestamp}`;
}

// === Get or Create Device Token ===

const DEVICE_TOKEN_KEY = 'device_token';

function getDeviceToken(): string {
  let token = localStorage.getItem(DEVICE_TOKEN_KEY);
  if (!token) {
    token = generateDeviceToken();
    localStorage.setItem(DEVICE_TOKEN_KEY, token);
  }
  return token;
}

// === Auth API ===

export const authApi = {
  /**
   * Login by PIN code
   */
  async loginByPin(pin: string): Promise<ApiResponse<LoginResponse>> {
    const response = await api.post<ApiResponse<LoginResponse>>('/auth/pin', {
      pin,
      device_token: getDeviceToken(),
    });

    if (response.success && response.data.token) {
      setToken(response.data.token);
    }

    return response;
  },

  /**
   * Login by email and password
   */
  async loginByEmail(email: string, password: string): Promise<ApiResponse<LoginResponse>> {
    const response = await api.post<ApiResponse<LoginResponse>>('/auth/login', {
      email,
      password,
      device_token: getDeviceToken(),
    });

    if (response.success && response.data.token) {
      setToken(response.data.token);
    }

    return response;
  },

  /**
   * Universal login (detects PIN vs email)
   */
  async login(credentials: LoginRequest): Promise<ApiResponse<LoginResponse>> {
    if ('pin' in credentials) {
      return this.loginByPin(credentials.pin);
    }
    return this.loginByEmail(credentials.email, credentials.password);
  },

  /**
   * Get current user info
   */
  async me(): Promise<ApiResponse<MeResponse>> {
    return api.get<ApiResponse<MeResponse>>('/auth/me');
  },

  /**
   * Logout
   */
  async logout(): Promise<ApiResponse<void>> {
    try {
      const response = await api.post<ApiResponse<void>>('/auth/logout');
      return response;
    } finally {
      removeToken();
    }
  },

  /**
   * Refresh token
   */
  async refreshToken(): Promise<ApiResponse<{ token: string }>> {
    const response = await api.post<ApiResponse<{ token: string }>>('/auth/refresh');

    if (response.success && response.data.token) {
      setToken(response.data.token);
    }

    return response;
  },

  /**
   * Update profile
   */
  async updateProfile(data: Partial<User>): Promise<ApiResponse<User>> {
    return api.put<ApiResponse<User>>('/auth/profile', data);
  },

  /**
   * Change PIN
   */
  async changePin(currentPin: string, newPin: string): Promise<ApiResponse<void>> {
    return api.post<ApiResponse<void>>('/auth/change-pin', {
      current_pin: currentPin,
      new_pin: newPin,
    });
  },

  /**
   * Check if PIN exists (for switching between PIN/password login)
   */
  async checkPinAvailable(): Promise<ApiResponse<{ available: boolean }>> {
    return api.get<ApiResponse<{ available: boolean }>>('/auth/pin-available');
  },
};
