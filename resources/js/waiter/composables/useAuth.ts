/**
 * Waiter App - useAuth Composable
 * Authentication logic and helpers
 */

import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/waiter/stores/auth';
import { useUiStore } from '@/waiter/stores/ui';
import type { UserRole } from '@/waiter/types';

export function useAuth() {
  const authStore = useAuthStore();
  const uiStore = useUiStore();

  const {
    user,
    restaurant,
    currentShift,
    isAuthenticated,
    isLoading,
    error,
    userName,
    userRole,
    permissions,
  } = storeToRefs(authStore);

  // === Computed ===

  /**
   * Check if user has admin role
   */
  const isAdmin = computed((): boolean => userRole.value === 'admin');

  /**
   * Check if user has manager role
   */
  const isManager = computed((): boolean => userRole.value === 'manager');

  /**
   * Check if user is waiter
   */
  const isWaiter = computed((): boolean => userRole.value === 'waiter');

  /**
   * Check if shift is open
   */
  const hasOpenShift = computed((): boolean => !!currentShift.value);

  /**
   * User initials for avatar
   */
  const userInitials = computed((): string => {
    if (!user.value?.name) return '??';
    const parts = user.value.name.split(' ');
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return user.value.name.substring(0, 2).toUpperCase();
  });

  // === Methods ===

  /**
   * Login with PIN
   */
  async function loginWithPin(pin: string): Promise<boolean> {
    const success = await authStore.loginWithPin(pin);

    if (success) {
      uiStore.showSuccess('Добро пожаловать!');
    } else {
      uiStore.showError(error.value || 'Неверный PIN');
    }

    return success;
  }

  /**
   * Login with email
   */
  async function loginWithEmail(email: string, password: string): Promise<boolean> {
    const success = await authStore.loginWithEmail(email, password);

    if (success) {
      uiStore.showSuccess('Добро пожаловать!');
    } else {
      uiStore.showError(error.value || 'Неверные данные');
    }

    return success;
  }

  /**
   * Logout
   */
  async function logout(): Promise<void> {
    uiStore.closeAllModals();
    await authStore.logout();
    uiStore.setTab('tables');
    uiStore.showInfo('Вы вышли из системы');
  }

  /**
   * Check if user has specific role
   */
  function hasRole(role: UserRole): boolean {
    return userRole.value === role;
  }

  /**
   * Check if user has any of specified roles
   */
  function hasAnyRole(roles: UserRole[]): boolean {
    return roles.includes(userRole.value as UserRole);
  }

  /**
   * Check if user has specific permission
   */
  function hasPermission(permission: string): boolean {
    return authStore.hasPermission(permission);
  }

  /**
   * Check authentication status
   */
  async function checkAuth(): Promise<boolean> {
    return authStore.checkAuth();
  }

  /**
   * Clear error message
   */
  function clearError(): void {
    authStore.clearError();
  }

  return {
    // State
    user,
    restaurant,
    currentShift,
    isAuthenticated,
    isLoading,
    error,
    userName,
    userRole,
    permissions,

    // Computed
    isAdmin,
    isManager,
    isWaiter,
    hasOpenShift,
    userInitials,

    // Methods
    loginWithPin,
    loginWithEmail,
    logout,
    hasRole,
    hasAnyRole,
    hasPermission,
    checkAuth,
    clearError,
  };
}
