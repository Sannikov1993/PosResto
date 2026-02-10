/**
 * POS Auth Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock logger
vi.mock('@/shared/services/logger.js', () => ({
    createLogger: () => ({
        debug: vi.fn(),
        warn: vi.fn(),
        error: vi.fn(),
        info: vi.fn(),
    }),
}));

// Mock auth service
vi.mock('@/shared/services/auth.js', () => ({
    default: {
        getSession: vi.fn(() => null),
        setSession: vi.fn(),
        clearAuth: vi.fn(),
        getToken: vi.fn(() => null),
    },
}));

// Mock storage constants
vi.mock('@/shared/constants/storage.js', () => ({
    getRestaurantId: vi.fn(() => null),
    setRestaurantId: vi.fn(),
    clearRestaurantId: vi.fn(),
    STORAGE_KEYS: {},
    BROADCAST_CHANNELS: {},
}));

// Mock permissions store
vi.mock('@/shared/stores/permissions.js', () => ({
    usePermissionsStore: vi.fn(() => ({
        init: vi.fn(),
        reset: vi.fn(),
        setRestaurantId: vi.fn(),
    })),
}));

// Mock POS API — vi.hoisted ensures mockApi is available when vi.mock is hoisted
const { mockApi } = vi.hoisted(() => ({
    mockApi: {
        auth: {
            loginWithPin: vi.fn(),
            checkAuth: vi.fn(),
            logout: vi.fn(),
        },
        get: vi.fn(),
        post: vi.fn(),
    },
}));

vi.mock('@/pos/api/index.js', () => ({
    default: mockApi,
}));

import { useAuthStore } from '@/pos/stores/auth.js';
import authService from '@/shared/services/auth.js';

describe('POS Auth Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        localStorage.clear();
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    describe('Initial State', () => {
        it('should have logged_out session state', () => {
            const store = useAuthStore();
            expect(store.sessionState).toBe('logged_out');
        });

        it('should have null user', () => {
            const store = useAuthStore();
            expect(store.user).toBeNull();
        });

        it('should have null token', () => {
            const store = useAuthStore();
            expect(store.token).toBeNull();
        });

        it('should have false isLoggedIn', () => {
            const store = useAuthStore();
            expect(store.isLoggedIn).toBe(false);
        });

        it('should have empty permissions', () => {
            const store = useAuthStore();
            expect(store.permissions).toEqual([]);
        });

        it('should have default limits', () => {
            const store = useAuthStore();
            expect(store.limits).toEqual({
                max_discount_percent: 0,
                max_refund_amount: 0,
                max_cancel_amount: 0,
            });
        });
    });

    describe('loginWithPin', () => {
        it('should login successfully with valid PIN', async () => {
            const mockResponse = {
                success: true,
                data: {
                    user: { id: 1, name: 'Кассир', role: 'cashier' },
                    token: 'test-token-123',
                    permissions: ['orders.view', 'orders.create'],
                    limits: { max_discount_percent: 10, max_refund_amount: 500, max_cancel_amount: 1000 },
                },
            };

            mockApi.auth.loginWithPin.mockResolvedValue(mockResponse);

            const store = useAuthStore();
            const result = await store.loginWithPin('1234');

            expect(result.success).toBe(true);
            expect(store.user).toEqual(mockResponse.data.user);
            expect(store.token).toBe('test-token-123');
            expect(store.isLoggedIn).toBe(true);
            expect(store.sessionState).toBe('active');
            expect(store.permissions).toEqual(['orders.view', 'orders.create']);
        });

        it('should handle failed login', async () => {
            mockApi.auth.loginWithPin.mockResolvedValue({
                success: false,
                message: 'Неверный PIN-код',
            });

            const store = useAuthStore();
            const result = await store.loginWithPin('0000');

            expect(result.success).toBe(false);
            expect(result.message).toBe('Неверный PIN-код');
            expect(store.user).toBeNull();
            expect(store.isLoggedIn).toBe(false);
        });

        it('should handle network error', async () => {
            mockApi.auth.loginWithPin.mockRejectedValue(new Error('Network error'));

            const store = useAuthStore();
            const result = await store.loginWithPin('1234');

            expect(result.success).toBe(false);
            expect(result.message).toBeDefined();
        });
    });

    describe('restoreSession', () => {
        it('should return false when no session exists', async () => {
            vi.mocked(authService.getSession).mockReturnValue(null);

            const store = useAuthStore();
            const result = await store.restoreSession();

            expect(result).toBe(false);
        });

        it('should restore session from valid token', async () => {
            vi.mocked(authService.getSession).mockReturnValue({
                token: 'saved-token',
                user: { id: 1, name: 'Saved User' },
                permissions: ['orders.view'],
                limits: {},
                interfaceAccess: {},
                posModules: [],
                backofficeModules: [],
                app: 'pos',
                loginAt: Date.now(),
                version: 2,
            } as any);

            mockApi.auth.checkAuth.mockResolvedValue({
                success: true,
                data: {
                    user: { id: 1, name: 'Saved User', role: 'cashier' },
                    permissions: ['orders.view'],
                },
            });

            const store = useAuthStore();
            const result = await store.restoreSession();

            expect(result).toBe(true);
            expect(store.isLoggedIn).toBe(true);
            expect(store.sessionState).toBe('active');
        });

        it('should clear auth on failed token validation', async () => {
            vi.mocked(authService.getSession).mockReturnValue({
                token: 'expired-token',
                user: null,
                permissions: [],
                limits: {},
                interfaceAccess: {},
                posModules: [],
                backofficeModules: [],
                app: 'pos',
                loginAt: Date.now(),
                version: 2,
            } as any);

            mockApi.auth.checkAuth.mockResolvedValue({ success: false });

            const store = useAuthStore();
            const result = await store.restoreSession();

            expect(result).toBe(false);
            expect(vi.mocked(authService.clearAuth)).toHaveBeenCalled();
        });
    });

    describe('logout', () => {
        it('should clear all auth state', async () => {
            const store = useAuthStore();

            // Setup logged-in state
            mockApi.auth.loginWithPin.mockResolvedValue({
                success: true,
                data: {
                    user: { id: 1, name: 'Test', role: 'cashier' },
                    token: 'token',
                    permissions: ['test'],
                },
            });
            await store.loginWithPin('1234');

            mockApi.auth.logout.mockResolvedValue({});
            await store.logout();

            expect(store.user).toBeNull();
            expect(store.token).toBeNull();
            expect(store.isLoggedIn).toBe(false);
            expect(store.sessionState).toBe('logged_out');
            expect(store.permissions).toEqual([]);
        });

        it('should still clear state even if API logout fails', async () => {
            const store = useAuthStore();
            store.token = 'some-token' as any;

            mockApi.auth.logout.mockRejectedValue(new Error('Network error'));

            await store.logout();

            expect(store.user).toBeNull();
            expect(store.sessionState).toBe('logged_out');
        });
    });

    describe('lockScreen / unlockScreen', () => {
        it('should lock screen from active state', async () => {
            const store = useAuthStore();

            // Login first
            mockApi.auth.loginWithPin.mockResolvedValue({
                success: true,
                data: {
                    user: { id: 1, name: 'Test', role: 'cashier' },
                    token: 'token',
                },
            });
            await store.loginWithPin('1234');

            store.lockScreen();

            expect(store.sessionState).toBe('locked');
            expect(store.lockedByUser).not.toBeNull();
            expect(store.lockedByUser?.name).toBe('Test');
        });

        it('should not lock from logged_out state', () => {
            const store = useAuthStore();

            store.lockScreen();

            expect(store.sessionState).toBe('logged_out');
        });

        it('should unlock screen', async () => {
            const store = useAuthStore();

            mockApi.auth.loginWithPin.mockResolvedValue({
                success: true,
                data: {
                    user: { id: 1, name: 'Test', role: 'cashier' },
                    token: 'token',
                },
            });
            await store.loginWithPin('1234');
            store.lockScreen();

            store.unlockScreen();

            expect(store.sessionState).toBe('active');
            expect(store.lockedByUser).toBeNull();
        });
    });

    describe('hasPermission', () => {
        it('should return true for super_admin', async () => {
            const store = useAuthStore();

            mockApi.auth.loginWithPin.mockResolvedValue({
                success: true,
                data: {
                    user: { id: 1, name: 'Admin', role: 'super_admin' },
                    token: 'token',
                    permissions: [],
                },
            });
            await store.loginWithPin('1234');

            expect(store.hasPermission('any.permission')).toBe(true);
        });

        it('should return true for owner', async () => {
            const store = useAuthStore();

            mockApi.auth.loginWithPin.mockResolvedValue({
                success: true,
                data: {
                    user: { id: 1, name: 'Owner', role: 'owner' },
                    token: 'token',
                    permissions: [],
                },
            });
            await store.loginWithPin('1234');

            expect(store.hasPermission('finance.view')).toBe(true);
        });

        it('should check permission list for regular user', async () => {
            const store = useAuthStore();

            mockApi.auth.loginWithPin.mockResolvedValue({
                success: true,
                data: {
                    user: { id: 1, name: 'Waiter', role: 'waiter' },
                    token: 'token',
                    permissions: ['orders.view', 'orders.create'],
                },
            });
            await store.loginWithPin('1234');

            expect(store.hasPermission('orders.view')).toBe(true);
            expect(store.hasPermission('orders.delete')).toBe(false);
        });

        it('should return false without user', () => {
            const store = useAuthStore();
            expect(store.hasPermission('any')).toBe(false);
        });
    });

    describe('canApplyDiscount', () => {
        it('should return true for admin with any percent', async () => {
            const store = useAuthStore();

            mockApi.auth.loginWithPin.mockResolvedValue({
                success: true,
                data: {
                    user: { id: 1, name: 'Admin', role: 'super_admin' },
                    token: 'token',
                    permissions: [],
                },
            });
            await store.loginWithPin('1234');

            expect(store.canApplyDiscount(100)).toBe(true);
        });

        it('should check limit for regular user', async () => {
            const store = useAuthStore();

            mockApi.auth.loginWithPin.mockResolvedValue({
                success: true,
                data: {
                    user: { id: 1, name: 'Cashier', role: 'cashier' },
                    token: 'token',
                    permissions: ['orders.discount'],
                    limits: { max_discount_percent: 20, max_refund_amount: 0, max_cancel_amount: 0 },
                },
            });
            await store.loginWithPin('1234');

            expect(store.canApplyDiscount(15)).toBe(true);
            expect(store.canApplyDiscount(25)).toBe(false);
        });
    });

    describe('userInitials', () => {
        it('should return initials from two-word name', async () => {
            const store = useAuthStore();

            mockApi.auth.loginWithPin.mockResolvedValue({
                success: true,
                data: {
                    user: { id: 1, name: 'Иван Петров', role: 'cashier' },
                    token: 'token',
                },
            });
            await store.loginWithPin('1234');

            expect(store.userInitials).toBe('ИП');
        });

        it('should return first 2 chars for single name', async () => {
            const store = useAuthStore();

            mockApi.auth.loginWithPin.mockResolvedValue({
                success: true,
                data: {
                    user: { id: 1, name: 'Admin', role: 'admin' },
                    token: 'token',
                },
            });
            await store.loginWithPin('1234');

            expect(store.userInitials).toBe('AD');
        });

        it('should return ? when no user', () => {
            const store = useAuthStore();
            expect(store.userInitials).toBe('?');
        });
    });

    describe('maxDiscountPercent', () => {
        it('should return 100 for admin', async () => {
            const store = useAuthStore();

            mockApi.auth.loginWithPin.mockResolvedValue({
                success: true,
                data: {
                    user: { id: 1, name: 'Admin', role: 'super_admin' },
                    token: 'token',
                },
            });
            await store.loginWithPin('1234');

            expect(store.maxDiscountPercent).toBe(100);
        });

        it('should return limit for regular user', async () => {
            const store = useAuthStore();

            mockApi.auth.loginWithPin.mockResolvedValue({
                success: true,
                data: {
                    user: { id: 1, name: 'Cashier', role: 'cashier' },
                    token: 'token',
                    limits: { max_discount_percent: 15, max_refund_amount: 0, max_cancel_amount: 0 },
                },
            });
            await store.loginWithPin('1234');

            expect(store.maxDiscountPercent).toBe(15);
        });

        it('should return 0 when no user', () => {
            const store = useAuthStore();
            expect(store.maxDiscountPercent).toBe(0);
        });
    });

    describe('handleUnauthorized', () => {
        it('should clear all auth state', async () => {
            const store = useAuthStore();

            mockApi.auth.loginWithPin.mockResolvedValue({
                success: true,
                data: {
                    user: { id: 1, name: 'Test', role: 'cashier' },
                    token: 'token',
                },
            });
            await store.loginWithPin('1234');

            store.handleUnauthorized();

            expect(store.user).toBeNull();
            expect(store.sessionState).toBe('logged_out');
            expect(store.isLoggedIn).toBe(false);
        });
    });
});
