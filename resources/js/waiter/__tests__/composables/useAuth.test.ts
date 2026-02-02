/**
 * useAuth Composable Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock stores
vi.mock('@/waiter/stores/auth', () => ({
  useAuthStore: vi.fn(() => ({
    user: null,
    restaurant: null,
    currentShift: null,
    isAuthenticated: false,
    isLoading: false,
    error: null,
    userName: '',
    userRole: 'waiter',
    permissions: [],
    loginWithPin: vi.fn(),
    loginWithEmail: vi.fn(),
    logout: vi.fn(),
    hasPermission: vi.fn(),
    checkAuth: vi.fn(),
    clearError: vi.fn(),
  })),
}));

vi.mock('@/waiter/stores/ui', () => ({
  useUiStore: vi.fn(() => ({
    showSuccess: vi.fn(),
    showError: vi.fn(),
    showInfo: vi.fn(),
    closeAllModals: vi.fn(),
    setTab: vi.fn(),
  })),
}));

import { useAuth } from '@/waiter/composables/useAuth';
import { useAuthStore } from '@/waiter/stores/auth';
import { useUiStore } from '@/waiter/stores/ui';

describe('useAuth Composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  describe('loginWithPin', () => {
    it('should login and show success toast', async () => {
      const authStore = useAuthStore();
      const uiStore = useUiStore();

      vi.mocked(authStore.loginWithPin).mockResolvedValue(true);

      const { loginWithPin } = useAuth();
      const result = await loginWithPin('1234');

      expect(result).toBe(true);
      expect(authStore.loginWithPin).toHaveBeenCalledWith('1234');
      expect(uiStore.showSuccess).toHaveBeenCalledWith('Добро пожаловать!');
    });

    it('should show error on failed login', async () => {
      const authStore = useAuthStore();
      const uiStore = useUiStore();

      vi.mocked(authStore.loginWithPin).mockResolvedValue(false);
      Object.defineProperty(authStore, 'error', { value: 'Неверный PIN', writable: true });

      const { loginWithPin } = useAuth();
      const result = await loginWithPin('0000');

      expect(result).toBe(false);
      expect(uiStore.showError).toHaveBeenCalledWith('Неверный PIN');
    });

    it('should show default error when no error message', async () => {
      const authStore = useAuthStore();
      const uiStore = useUiStore();

      vi.mocked(authStore.loginWithPin).mockResolvedValue(false);
      Object.defineProperty(authStore, 'error', { value: null, writable: true });

      const { loginWithPin } = useAuth();
      await loginWithPin('0000');

      expect(uiStore.showError).toHaveBeenCalledWith('Неверный PIN');
    });
  });

  describe('loginWithEmail', () => {
    it('should login with email and show success toast', async () => {
      const authStore = useAuthStore();
      const uiStore = useUiStore();

      vi.mocked(authStore.loginWithEmail).mockResolvedValue(true);

      const { loginWithEmail } = useAuth();
      const result = await loginWithEmail('test@example.com', 'password');

      expect(result).toBe(true);
      expect(authStore.loginWithEmail).toHaveBeenCalledWith('test@example.com', 'password');
      expect(uiStore.showSuccess).toHaveBeenCalledWith('Добро пожаловать!');
    });

    it('should show error on failed email login', async () => {
      const authStore = useAuthStore();
      const uiStore = useUiStore();

      vi.mocked(authStore.loginWithEmail).mockResolvedValue(false);
      Object.defineProperty(authStore, 'error', { value: 'Неверные данные', writable: true });

      const { loginWithEmail } = useAuth();
      const result = await loginWithEmail('test@example.com', 'wrong');

      expect(result).toBe(false);
      expect(uiStore.showError).toHaveBeenCalledWith('Неверные данные');
    });
  });

  describe('logout', () => {
    it('should logout and reset UI', async () => {
      const authStore = useAuthStore();
      const uiStore = useUiStore();

      vi.mocked(authStore.logout).mockResolvedValue(undefined);

      const { logout } = useAuth();
      await logout();

      expect(uiStore.closeAllModals).toHaveBeenCalled();
      expect(authStore.logout).toHaveBeenCalled();
      expect(uiStore.setTab).toHaveBeenCalledWith('tables');
      expect(uiStore.showInfo).toHaveBeenCalledWith('Вы вышли из системы');
    });
  });

  describe('Role Checks', () => {
    it('isAdmin should check admin role', () => {
      const authStore = useAuthStore();
      Object.defineProperty(authStore, 'userRole', { value: 'admin', writable: true });

      const { isAdmin, isManager, isWaiter } = useAuth();

      expect(isAdmin.value).toBe(true);
      expect(isManager.value).toBe(false);
      expect(isWaiter.value).toBe(false);
    });

    it('isManager should check manager role', () => {
      const authStore = useAuthStore();
      Object.defineProperty(authStore, 'userRole', { value: 'manager', writable: true });

      const { isAdmin, isManager, isWaiter } = useAuth();

      expect(isAdmin.value).toBe(false);
      expect(isManager.value).toBe(true);
      expect(isWaiter.value).toBe(false);
    });

    it('isWaiter should check waiter role', () => {
      const authStore = useAuthStore();
      Object.defineProperty(authStore, 'userRole', { value: 'waiter', writable: true });

      const { isAdmin, isManager, isWaiter } = useAuth();

      expect(isAdmin.value).toBe(false);
      expect(isManager.value).toBe(false);
      expect(isWaiter.value).toBe(true);
    });

    it('hasRole should check specific role', () => {
      const authStore = useAuthStore();
      Object.defineProperty(authStore, 'userRole', { value: 'manager', writable: true });

      const { hasRole } = useAuth();

      expect(hasRole('manager')).toBe(true);
      expect(hasRole('admin')).toBe(false);
    });

    it('hasAnyRole should check multiple roles', () => {
      const authStore = useAuthStore();
      Object.defineProperty(authStore, 'userRole', { value: 'manager', writable: true });

      const { hasAnyRole } = useAuth();

      expect(hasAnyRole(['admin', 'manager'])).toBe(true);
      expect(hasAnyRole(['admin', 'waiter'])).toBe(false);
    });
  });

  describe('hasOpenShift', () => {
    it('should return true when shift exists', () => {
      const authStore = useAuthStore();
      Object.defineProperty(authStore, 'currentShift', { value: { id: 1 }, writable: true });

      const { hasOpenShift } = useAuth();

      expect(hasOpenShift.value).toBe(true);
    });

    it('should return false when no shift', () => {
      const authStore = useAuthStore();
      Object.defineProperty(authStore, 'currentShift', { value: null, writable: true });

      const { hasOpenShift } = useAuth();

      expect(hasOpenShift.value).toBe(false);
    });
  });

  describe('userInitials', () => {
    it('should return initials from full name', () => {
      const authStore = useAuthStore();
      Object.defineProperty(authStore, 'user', { value: { name: 'Иван Петров' }, writable: true });

      const { userInitials } = useAuth();

      expect(userInitials.value).toBe('ИП');
    });

    it('should return first two characters for single name', () => {
      const authStore = useAuthStore();
      Object.defineProperty(authStore, 'user', { value: { name: 'Иван' }, writable: true });

      const { userInitials } = useAuth();

      expect(userInitials.value).toBe('ИВ');
    });

    it('should return ?? when no user', () => {
      const authStore = useAuthStore();
      Object.defineProperty(authStore, 'user', { value: null, writable: true });

      const { userInitials } = useAuth();

      expect(userInitials.value).toBe('??');
    });
  });

  describe('hasPermission', () => {
    it('should delegate to store', () => {
      const authStore = useAuthStore();
      vi.mocked(authStore.hasPermission).mockReturnValue(true);

      const { hasPermission } = useAuth();
      const result = hasPermission('orders.create');

      expect(authStore.hasPermission).toHaveBeenCalledWith('orders.create');
      expect(result).toBe(true);
    });
  });

  describe('checkAuth', () => {
    it('should delegate to store', async () => {
      const authStore = useAuthStore();
      vi.mocked(authStore.checkAuth).mockResolvedValue(true);

      const { checkAuth } = useAuth();
      const result = await checkAuth();

      expect(authStore.checkAuth).toHaveBeenCalled();
      expect(result).toBe(true);
    });
  });

  describe('clearError', () => {
    it('should delegate to store', () => {
      const authStore = useAuthStore();

      const { clearError } = useAuth();
      clearError();

      expect(authStore.clearError).toHaveBeenCalled();
    });
  });
});
