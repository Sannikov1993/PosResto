/**
 * Auth Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock the services with inline functions
vi.mock('@/waiter/services', () => {
  return {
    authApi: {
      login: vi.fn(),
      logout: vi.fn(),
      me: vi.fn(),
    },
    hasToken: vi.fn(() => false),
    removeToken: vi.fn(),
  };
});

// Import after mocking
import { useAuthStore } from '@/waiter/stores/auth';
import { authApi, hasToken, removeToken } from '@/waiter/services';

describe('Auth Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    vi.mocked(hasToken).mockReturnValue(false);
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const store = useAuthStore();

      expect(store.user).toBeNull();
      expect(store.restaurant).toBeNull();
      expect(store.permissions).toEqual([]);
      expect(store.currentShift).toBeNull();
      expect(store.isLoading).toBe(false);
      expect(store.error).toBeNull();
    });

    it('should have isAuthenticated false initially', () => {
      const store = useAuthStore();
      expect(store.isAuthenticated).toBe(false);
    });
  });

  describe('loginWithPin', () => {
    it('should login successfully with valid PIN', async () => {
      const mockResponse = {
        success: true,
        data: {
          user: { id: 1, name: 'Test User', role: 'waiter' },
          restaurant: { id: 1, name: 'Test Restaurant' },
          permissions: ['orders.view', 'orders.create'],
        },
      };

      vi.mocked(authApi.login).mockResolvedValue(mockResponse as any);

      const store = useAuthStore();
      const result = await store.loginWithPin('1234');

      expect(result).toBe(true);
      expect(store.user).toEqual(mockResponse.data.user);
      expect(store.restaurant).toEqual(mockResponse.data.restaurant);
      expect(store.permissions).toEqual(['orders.view', 'orders.create']);
      expect(store.error).toBeNull();
    });

    it('should handle invalid PIN', async () => {
      const mockResponse = {
        success: false,
        message: 'Неверный PIN-код',
      };

      vi.mocked(authApi.login).mockResolvedValue(mockResponse as any);

      const store = useAuthStore();
      const result = await store.loginWithPin('0000');

      expect(result).toBe(false);
      expect(store.error).toBe('Неверный PIN-код');
      expect(store.user).toBeNull();
    });

    it('should handle network error', async () => {
      vi.mocked(authApi.login).mockRejectedValue(new Error('Network error'));

      const store = useAuthStore();
      const result = await store.loginWithPin('1234');

      expect(result).toBe(false);
      expect(store.error).toBe('Network error');
    });

    it('should set loading state during login', async () => {
      vi.mocked(authApi.login).mockImplementation(() =>
        new Promise(resolve => setTimeout(() => resolve({ success: true, data: {} } as any), 100))
      );

      const store = useAuthStore();
      const promise = store.loginWithPin('1234');

      expect(store.isLoading).toBe(true);
      await promise;
      expect(store.isLoading).toBe(false);
    });
  });

  describe('loginWithEmail', () => {
    it('should login with email and password', async () => {
      const mockResponse = {
        success: true,
        data: {
          user: { id: 1, name: 'Test User', role: 'admin' },
          restaurant: { id: 1, name: 'Test Restaurant' },
          permissions: [],
        },
      };

      vi.mocked(authApi.login).mockResolvedValue(mockResponse as any);

      const store = useAuthStore();
      const result = await store.loginWithEmail('test@example.com', 'password');

      expect(result).toBe(true);
      expect(store.user).toEqual(mockResponse.data.user);
      expect(vi.mocked(authApi.login)).toHaveBeenCalledWith({
        email: 'test@example.com',
        password: 'password',
        device_token: '',
      });
    });
  });

  describe('logout', () => {
    it('should clear user data on logout', async () => {
      const store = useAuthStore();

      // Set up authenticated state
      store.user = { id: 1, name: 'Test', role: 'waiter' } as any;
      store.restaurant = { id: 1, name: 'Test' } as any;
      store.permissions = ['test.permission'];

      vi.mocked(authApi.logout).mockResolvedValue({ success: true } as any);

      await store.logout();

      expect(store.user).toBeNull();
      expect(store.restaurant).toBeNull();
      expect(store.permissions).toEqual([]);
      expect(vi.mocked(removeToken)).toHaveBeenCalled();
    });

    it('should clear data even if logout API fails', async () => {
      const store = useAuthStore();
      store.user = { id: 1, name: 'Test', role: 'waiter' } as any;

      vi.mocked(authApi.logout).mockRejectedValue(new Error('API Error'));

      await store.logout();

      expect(store.user).toBeNull();
      expect(vi.mocked(removeToken)).toHaveBeenCalled();
    });
  });

  describe('checkAuth', () => {
    it('should return false if no token', async () => {
      vi.mocked(hasToken).mockReturnValue(false);

      const store = useAuthStore();
      const result = await store.checkAuth();

      expect(result).toBe(false);
      expect(vi.mocked(authApi.me)).not.toHaveBeenCalled();
    });

    it('should validate token and set user', async () => {
      vi.mocked(hasToken).mockReturnValue(true);

      const mockResponse = {
        success: true,
        data: {
          user: { id: 1, name: 'Test User', role: 'waiter' },
          restaurant: { id: 1, name: 'Test Restaurant' },
          permissions: ['orders.view'],
          shift: { id: 1, status: 'open' },
        },
      };

      vi.mocked(authApi.me).mockResolvedValue(mockResponse as any);

      const store = useAuthStore();
      const result = await store.checkAuth();

      expect(result).toBe(true);
      expect(store.user).toEqual(mockResponse.data.user);
      expect(store.currentShift).toEqual(mockResponse.data.shift);
    });

    it('should clear token if validation fails', async () => {
      vi.mocked(hasToken).mockReturnValue(true);
      vi.mocked(authApi.me).mockRejectedValue(new Error('Unauthorized'));

      const store = useAuthStore();
      const result = await store.checkAuth();

      expect(result).toBe(false);
      expect(vi.mocked(removeToken)).toHaveBeenCalled();
    });

    it('should clear token if response is not successful', async () => {
      vi.mocked(hasToken).mockReturnValue(true);
      vi.mocked(authApi.me).mockResolvedValue({ success: false, message: 'Invalid token' } as any);

      const store = useAuthStore();
      const result = await store.checkAuth();

      expect(result).toBe(false);
      expect(vi.mocked(removeToken)).toHaveBeenCalled();
    });
  });

  describe('Getters', () => {
    it('isAuthenticated should be true when user exists and hasToken', () => {
      vi.mocked(hasToken).mockReturnValue(true);

      const store = useAuthStore();
      store.user = { id: 1, name: 'Test', role: 'waiter' } as any;

      expect(store.isAuthenticated).toBe(true);
    });

    it('isAuthenticated should be false without user', () => {
      vi.mocked(hasToken).mockReturnValue(true);

      const store = useAuthStore();
      expect(store.isAuthenticated).toBe(false);
    });

    it('isAuthenticated should be false without token', () => {
      vi.mocked(hasToken).mockReturnValue(false);

      const store = useAuthStore();
      store.user = { id: 1, name: 'Test', role: 'waiter' } as any;

      expect(store.isAuthenticated).toBe(false);
    });

    it('userName should return user name', () => {
      const store = useAuthStore();
      store.user = { id: 1, name: 'John Doe', role: 'waiter' } as any;

      expect(store.userName).toBe('John Doe');
    });

    it('userName should return empty string if no user', () => {
      const store = useAuthStore();
      expect(store.userName).toBe('');
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

    it('hasAnyPermission should check if any permission exists', () => {
      const store = useAuthStore();
      store.permissions = ['orders.view', 'orders.create'];

      expect(store.hasAnyPermission(['orders.view', 'orders.delete'])).toBe(true);
      expect(store.hasAnyPermission(['orders.delete', 'orders.update'])).toBe(false);
    });

    it('hasAllPermissions should check if all permissions exist', () => {
      const store = useAuthStore();
      store.permissions = ['orders.view', 'orders.create', 'orders.update'];

      expect(store.hasAllPermissions(['orders.view', 'orders.create'])).toBe(true);
      expect(store.hasAllPermissions(['orders.view', 'orders.delete'])).toBe(false);
    });

    it('hasShift should be true when currentShift exists', () => {
      const store = useAuthStore();
      expect(store.hasShift).toBe(false);

      store.currentShift = { id: 1, status: 'open' } as any;
      expect(store.hasShift).toBe(true);
    });

    it('isShiftOpen should check shift status', () => {
      const store = useAuthStore();
      expect(store.isShiftOpen).toBe(false);

      store.currentShift = { id: 1, status: 'open' } as any;
      expect(store.isShiftOpen).toBe(true);

      store.currentShift = { id: 1, status: 'closed' } as any;
      expect(store.isShiftOpen).toBe(false);
    });
  });

  describe('setShift', () => {
    it('should update current shift', () => {
      const store = useAuthStore();
      const shift = { id: 1, status: 'open', opened_at: '2024-01-15T10:00:00Z' } as any;

      store.setShift(shift);

      expect(store.currentShift).toEqual(shift);
    });

    it('should allow setting shift to null', () => {
      const store = useAuthStore();
      store.currentShift = { id: 1 } as any;

      store.setShift(null);

      expect(store.currentShift).toBeNull();
    });
  });

  describe('clearError', () => {
    it('should clear error', () => {
      const store = useAuthStore();
      store.error = 'Some error';

      store.clearError();

      expect(store.error).toBeNull();
    });
  });

  describe('$reset', () => {
    it('should reset store to initial state', () => {
      const store = useAuthStore();

      store.user = { id: 1, name: 'Test', role: 'waiter' } as any;
      store.restaurant = { id: 1, name: 'Test' } as any;
      store.permissions = ['test'];
      store.currentShift = { id: 1 } as any;
      store.error = 'Some error';
      store.isLoading = true;

      store.$reset();

      expect(store.user).toBeNull();
      expect(store.restaurant).toBeNull();
      expect(store.permissions).toEqual([]);
      expect(store.currentShift).toBeNull();
      expect(store.error).toBeNull();
      expect(store.isLoading).toBe(false);
    });
  });
});
