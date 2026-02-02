/**
 * Waiter App - Auth Store
 * Manages authentication state and user session
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { authApi, hasToken, removeToken } from '@/waiter/services';
import type { User, Restaurant, Shift, LoginRequest } from '@/waiter/types';

export const useAuthStore = defineStore('waiter-auth', () => {
  // === State ===
  const user = ref<User | null>(null);
  const restaurant = ref<Restaurant | null>(null);
  const permissions = ref<string[]>([]);
  const currentShift = ref<Shift | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  // === Getters ===
  const isAuthenticated = computed(() => !!user.value && hasToken());
  const userName = computed(() => user.value?.name || '');
  const userRole = computed(() => user.value?.role || '');
  const userId = computed(() => user.value?.id || 0);
  const restaurantName = computed(() => restaurant.value?.name || '');
  const restaurantId = computed(() => restaurant.value?.id || 0);
  const hasShift = computed(() => !!currentShift.value);
  const isShiftOpen = computed(() => currentShift.value?.status === 'open');

  /**
   * Check if user has a specific permission
   */
  const hasPermission = (permission: string): boolean => {
    return permissions.value.includes(permission);
  };

  /**
   * Check if user has any of the specified permissions
   */
  const hasAnyPermission = (perms: string[]): boolean => {
    return perms.some(p => permissions.value.includes(p));
  };

  /**
   * Check if user has all of the specified permissions
   */
  const hasAllPermissions = (perms: string[]): boolean => {
    return perms.every(p => permissions.value.includes(p));
  };

  // === Actions ===

  /**
   * Login with PIN or email/password
   */
  async function login(credentials: LoginRequest): Promise<boolean> {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await authApi.login(credentials);

      if (response.success && response.data) {
        user.value = response.data.user;
        restaurant.value = response.data.restaurant;
        permissions.value = response.data.permissions || [];
        return true;
      }

      error.value = response.message || 'Ошибка входа';
      return false;
    } catch (e: any) {
      error.value = e.message || 'Ошибка входа';
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Login with PIN code
   */
  async function loginWithPin(pin: string): Promise<boolean> {
    return login({ pin, device_token: '' });
  }

  /**
   * Login with email and password
   */
  async function loginWithEmail(email: string, password: string): Promise<boolean> {
    return login({ email, password, device_token: '' });
  }

  /**
   * Logout
   */
  async function logout(): Promise<void> {
    isLoading.value = true;

    try {
      await authApi.logout();
    } catch {
      // Ignore errors, clear state anyway
    } finally {
      // Clear all state
      user.value = null;
      restaurant.value = null;
      permissions.value = [];
      currentShift.value = null;
      error.value = null;
      isLoading.value = false;
      removeToken();
    }
  }

  /**
   * Check authentication status and load user data
   */
  async function checkAuth(): Promise<boolean> {
    if (!hasToken()) {
      return false;
    }

    isLoading.value = true;

    try {
      const response = await authApi.me();

      if (response.success && response.data) {
        user.value = response.data.user;
        restaurant.value = response.data.restaurant;
        permissions.value = response.data.permissions || [];
        currentShift.value = response.data.shift || null;
        return true;
      }

      // Token invalid, clear it
      removeToken();
      return false;
    } catch {
      removeToken();
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Update current shift
   */
  function setShift(shift: Shift | null): void {
    currentShift.value = shift;
  }

  /**
   * Clear error
   */
  function clearError(): void {
    error.value = null;
  }

  /**
   * Reset store to initial state
   */
  function $reset(): void {
    user.value = null;
    restaurant.value = null;
    permissions.value = [];
    currentShift.value = null;
    isLoading.value = false;
    error.value = null;
  }

  return {
    // State
    user,
    restaurant,
    permissions,
    currentShift,
    isLoading,
    error,

    // Getters
    isAuthenticated,
    userName,
    userRole,
    userId,
    restaurantName,
    restaurantId,
    hasShift,
    isShiftOpen,

    // Permission helpers
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,

    // Actions
    login,
    loginWithPin,
    loginWithEmail,
    logout,
    checkAuth,
    setShift,
    clearError,
    $reset,
  };
});
