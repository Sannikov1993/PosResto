/**
 * Auth Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '@/waiter/stores/auth';

// Mock the auth API
vi.mock('@/waiter/services', () => ({
  authApi: {
    loginWithPin: vi.fn(),
    loginWithEmail: vi.fn(),
    logout: vi.fn(),
    me: vi.fn(),
  },
}));

import { authApi } from '@/waiter/services';

describe('Auth Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    localStorage.clear();
  });

  afterEach(() => {
    localStorage.clear();
  });

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const store = useAuthStore();

      expect(store.user).toBeNull();
      expect(store.restaurant).toBeNull();
      expect(store.isAuthenticated).toBe(false);
      expect(store.isLoading).toBe(false);
      expect(store.error).toBeNull();
    });

    it('should restore token from localStorage', () => {
      localStorage.setItem('waiter_token', 'test-token');
      const store = useAuthStore();

      expect(store.token).toBe('test-token');
    });
  });

  describe('loginWithPin', () => {
    it('should login successfully with valid PIN', async () => {
      const mockResponse = {
        success: true,
        data: {
          user: { id: 1, name: 'Test User', role: 'waiter' },
          restaurant: { id: 1, name: 'Test Restaurant' },
          token: 'new-token',
        },
      };

      vi.mocked(authApi.loginWithPin).mockResolvedValue(mockResponse);

      const store = useAuthStore();
      const result = await store.loginWithPin('1234');

      expect(result).toBe(true);
      expect(store.user).toEqual(mockResponse.data.user);
      expect(store.restaurant).toEqual(mockResponse.data.restaurant);
      expect(store.token).toBe('new-token');
      expect(store.isAuthenticated).toBe(true);
      expect(localStorage.getItem('waiter_token')).toBe('new-token');
    });

    it('should handle invalid PIN', async () => {
      const mockResponse = {
        success: false,
        message: 'Неверный PIN-код',
      };

      vi.mocked(authApi.loginWithPin).mockResolvedValue(mockResponse);

      const store = useAuthStore();
      const result = await store.loginWithPin('0000');

      expect(result).toBe(false);
      expect(store.error).toBe('Неверный PIN-код');
      expect(store.isAuthenticated).toBe(false);
    });

    it('should handle network error', async () => {
      vi.mocked(authApi.loginWithPin).mockRejectedValue(new Error('Network error'));

      const store = useAuthStore();
      const result = await store.loginWithPin('1234');

      expect(result).toBe(false);
      expect(store.error).toBe('Network error');
    });

    it('should set loading state during login', async () => {
      vi.mocked(authApi.loginWithPin).mockImplementation(() =>
        new Promise(resolve => setTimeout(() => resolve({ success: true, data: {} }), 100))
      );

      const store = useAuthStore();
      const promise = store.loginWithPin('1234');

      expect(store.isLoading).toBe(true);
      await promise;
      expect(store.isLoading).toBe(false);
    });
  });

  describe('logout', () => {
    it('should clear user data on logout', async () => {
      const store = useAuthStore();

      // Set up authenticated state
      store.user = { id: 1, name: 'Test', role: 'waiter' } as any;
      store.token = 'test-token';
      store.restaurant = { id: 1, name: 'Test' } as any;
      localStorage.setItem('waiter_token', 'test-token');

      vi.mocked(authApi.logout).mockResolvedValue({ success: true });

      await store.logout();

      expect(store.user).toBeNull();
      expect(store.token).toBeNull();
      expect(store.restaurant).toBeNull();
      expect(store.isAuthenticated).toBe(false);
      expect(localStorage.getItem('waiter_token')).toBeNull();
    });

    it('should clear data even if logout API fails', async () => {
      const store = useAuthStore();
      store.user = { id: 1, name: 'Test', role: 'waiter' } as any;
      store.token = 'test-token';

      vi.mocked(authApi.logout).mockRejectedValue(new Error('API Error'));

      await store.logout();

      expect(store.user).toBeNull();
      expect(store.token).toBeNull();
    });
  });

  describe('checkAuth', () => {
    it('should return false if no token', async () => {
      const store = useAuthStore();
      const result = await store.checkAuth();

      expect(result).toBe(false);
    });

    it('should validate token and set user', async () => {
      localStorage.setItem('waiter_token', 'valid-token');

      const mockResponse = {
        success: true,
        data: {
          user: { id: 1, name: 'Test User', role: 'waiter' },
          restaurant: { id: 1, name: 'Test Restaurant' },
        },
      };

      vi.mocked(authApi.me).mockResolvedValue(mockResponse);

      const store = useAuthStore();
      const result = await store.checkAuth();

      expect(result).toBe(true);
      expect(store.user).toEqual(mockResponse.data.user);
    });

    it('should clear token if validation fails', async () => {
      localStorage.setItem('waiter_token', 'invalid-token');

      vi.mocked(authApi.me).mockRejectedValue(new Error('Unauthorized'));

      const store = useAuthStore();
      const result = await store.checkAuth();

      expect(result).toBe(false);
      expect(store.token).toBeNull();
      expect(localStorage.getItem('waiter_token')).toBeNull();
    });
  });

  describe('Getters', () => {
    it('isAuthenticated should be true when user and token exist', () => {
      const store = useAuthStore();
      store.user = { id: 1, name: 'Test', role: 'waiter' } as any;
      store.token = 'test-token';

      expect(store.isAuthenticated).toBe(true);
    });

    it('userName should return user name', () => {
      const store = useAuthStore();
      store.user = { id: 1, name: 'John Doe', role: 'waiter' } as any;

      expect(store.userName).toBe('John Doe');
    });

    it('userRole should return user role', () => {
      const store = useAuthStore();
      store.user = { id: 1, name: 'Test', role: 'manager' } as any;

      expect(store.userRole).toBe('manager');
    });

    it('hasPermission should check permissions array', () => {
      const store = useAuthStore();
      store.permissions = ['orders.view', 'orders.create'];

      expect(store.hasPermission('orders.view')).toBe(true);
      expect(store.hasPermission('orders.delete')).toBe(false);
    });
  });

  describe('$reset', () => {
    it('should reset store to initial state', () => {
      const store = useAuthStore();

      store.user = { id: 1, name: 'Test', role: 'waiter' } as any;
      store.token = 'test-token';
      store.error = 'Some error';

      store.$reset();

      expect(store.user).toBeNull();
      expect(store.token).toBeNull();
      expect(store.error).toBeNull();
    });
  });
});
