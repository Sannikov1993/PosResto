/**
 * usePermissions Composable Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
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

// Mock storage constants
vi.mock('@/shared/constants/storage.js', () => ({
    STORAGE_KEYS: {
        TENANT_ID: 'menulab_tenant_id',
        RESTAURANT_ID: 'menulab_restaurant_id',
    },
    BROADCAST_CHANNELS: {
        RESTAURANT_SYNC: 'menulab_restaurant_sync',
    },
    getRestaurantId: vi.fn(() => null),
    setRestaurantId: vi.fn(),
    clearRestaurantId: vi.fn(),
}));

import { usePermissions } from '@/shared/composables/usePermissions.js';
import { usePermissionsStore } from '@/shared/stores/permissions.js';

describe('usePermissions', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        localStorage.clear();
    });

    describe('return values', () => {
        it('should return all expected computed refs and methods', () => {
            const perms = usePermissions();

            expect(perms).toHaveProperty('permissions');
            expect(perms).toHaveProperty('limits');
            expect(perms).toHaveProperty('interfaceAccess');
            expect(perms).toHaveProperty('posModules');
            expect(perms).toHaveProperty('backofficeModules');
            expect(perms).toHaveProperty('userRole');
            expect(perms).toHaveProperty('isAdmin');
            expect(perms).toHaveProperty('initialized');
            expect(perms).toHaveProperty('maxDiscountPercent');
            expect(perms).toHaveProperty('maxRefundAmount');
            expect(perms).toHaveProperty('maxCancelAmount');
            expect(typeof perms.can).toBe('function');
            expect(typeof perms.canAny).toBe('function');
            expect(typeof perms.canAll).toBe('function');
            expect(typeof perms.canApplyDiscount).toBe('function');
            expect(typeof perms.canRefund).toBe('function');
            expect(typeof perms.canCancel).toBe('function');
            expect(typeof perms.canAccessInterface).toBe('function');
            expect(typeof perms.canAccessPosModule).toBe('function');
            expect(typeof perms.canAccessBackofficeModule).toBe('function');
            expect(typeof perms.getAvailablePosModules).toBe('function');
            expect(typeof perms.getAvailableBackofficeModules).toBe('function');
        });
    });

    describe('computed refs reflect store state', () => {
        it('should reflect permissions from the store', () => {
            const store = usePermissionsStore();
            store.init({ permissions: ['orders.view', 'orders.create'], role: 'waiter' });

            const perms = usePermissions();

            expect(perms.permissions.value).toEqual(['orders.view', 'orders.create']);
        });

        it('should reflect userRole from the store', () => {
            const store = usePermissionsStore();
            store.init({ role: 'cashier' });

            const perms = usePermissions();

            expect(perms.userRole.value).toBe('cashier');
        });

        it('should reflect isAdmin correctly for admin role', () => {
            const store = usePermissionsStore();
            store.init({ role: 'super_admin' });

            const perms = usePermissions();

            expect(perms.isAdmin.value).toBe(true);
        });

        it('should reflect isAdmin correctly for non-admin role', () => {
            const store = usePermissionsStore();
            store.init({ role: 'waiter' });

            const perms = usePermissions();

            expect(perms.isAdmin.value).toBe(false);
        });

        it('should reflect initialized state', () => {
            const perms = usePermissions();
            expect(perms.initialized.value).toBe(false);

            const store = usePermissionsStore();
            store.init({ role: 'waiter' });

            expect(perms.initialized.value).toBe(true);
        });

        it('should reflect limits from the store', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'cashier',
                limits: { max_discount_percent: 20, max_refund_amount: 500, max_cancel_amount: 1000 },
            });

            const perms = usePermissions();

            expect(perms.limits.value.max_discount_percent).toBe(20);
            expect(perms.maxDiscountPercent.value).toBe(20);
            expect(perms.maxRefundAmount.value).toBe(500);
            expect(perms.maxCancelAmount.value).toBe(1000);
        });
    });

    describe('can', () => {
        it('should delegate to store.can', () => {
            const store = usePermissionsStore();
            store.init({ role: 'waiter', permissions: ['orders.view'] });

            const perms = usePermissions();

            expect(perms.can('orders.view')).toBe(true);
            expect(perms.can('orders.delete')).toBe(false);
        });

        it('should return true for any permission when admin', () => {
            const store = usePermissionsStore();
            store.init({ role: 'owner' });

            const perms = usePermissions();

            expect(perms.can('anything')).toBe(true);
        });
    });

    describe('canAny', () => {
        it('should return true if any permission matches', () => {
            const store = usePermissionsStore();
            store.init({ role: 'waiter', permissions: ['orders.view'] });

            const perms = usePermissions();

            expect(perms.canAny(['orders.view', 'orders.delete'])).toBe(true);
        });

        it('should return false if no permissions match', () => {
            const store = usePermissionsStore();
            store.init({ role: 'waiter', permissions: ['orders.view'] });

            const perms = usePermissions();

            expect(perms.canAny(['finance.view', 'admin.access'])).toBe(false);
        });
    });

    describe('canAll', () => {
        it('should return true if all permissions match', () => {
            const store = usePermissionsStore();
            store.init({ role: 'waiter', permissions: ['orders.view', 'orders.create'] });

            const perms = usePermissions();

            expect(perms.canAll(['orders.view', 'orders.create'])).toBe(true);
        });

        it('should return false if any permission is missing', () => {
            const store = usePermissionsStore();
            store.init({ role: 'waiter', permissions: ['orders.view'] });

            const perms = usePermissions();

            expect(perms.canAll(['orders.view', 'orders.delete'])).toBe(false);
        });
    });

    describe('canApplyDiscount', () => {
        it('should check discount permission and limit', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'cashier',
                permissions: ['orders.discount'],
                limits: { max_discount_percent: 20, max_refund_amount: 0, max_cancel_amount: 0 },
            });

            const perms = usePermissions();

            expect(perms.canApplyDiscount(15)).toBe(true);
            expect(perms.canApplyDiscount(25)).toBe(false);
        });
    });

    describe('canRefund', () => {
        it('should check refund permission and limit', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'cashier',
                permissions: ['orders.refund'],
                limits: { max_discount_percent: 0, max_refund_amount: 1000, max_cancel_amount: 0 },
            });

            const perms = usePermissions();

            expect(perms.canRefund(500)).toBe(true);
            expect(perms.canRefund(1500)).toBe(false);
        });
    });

    describe('canCancel', () => {
        it('should check cancel permission and limit', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'cashier',
                permissions: ['orders.cancel'],
                limits: { max_discount_percent: 0, max_refund_amount: 0, max_cancel_amount: 2000 },
            });

            const perms = usePermissions();

            expect(perms.canCancel(1500)).toBe(true);
            expect(perms.canCancel(3000)).toBe(false);
        });
    });

    describe('canAccessInterface', () => {
        it('should delegate to store.canAccessInterface', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'waiter',
                interfaceAccess: { can_access_pos: true, can_access_kitchen: false },
            });

            const perms = usePermissions();

            expect(perms.canAccessInterface('pos')).toBe(true);
            expect(perms.canAccessInterface('kitchen')).toBe(false);
        });
    });

    describe('canAccessPosModule / canAccessBackofficeModule', () => {
        it('should check pos module access', () => {
            const store = usePermissionsStore();
            store.init({ role: 'cashier', posModules: ['cash', 'orders'] });

            const perms = usePermissions();

            expect(perms.canAccessPosModule('cash')).toBe(true);
            expect(perms.canAccessPosModule('warehouse')).toBe(false);
        });

        it('should check backoffice module access', () => {
            const store = usePermissionsStore();
            store.init({ role: 'cashier', backofficeModules: ['dashboard'] });

            const perms = usePermissions();

            expect(perms.canAccessBackofficeModule('dashboard')).toBe(true);
            expect(perms.canAccessBackofficeModule('finance')).toBe(false);
        });
    });

    describe('getAvailablePosModules / getAvailableBackofficeModules', () => {
        it('should return assigned pos modules for non-admin', () => {
            const store = usePermissionsStore();
            store.init({ role: 'cashier', posModules: ['cash'] });

            const perms = usePermissions();

            expect(perms.getAvailablePosModules()).toEqual(['cash']);
        });

        it('should return all pos modules for admin', () => {
            const store = usePermissionsStore();
            store.init({ role: 'super_admin' });

            const perms = usePermissions();

            const modules = perms.getAvailablePosModules();
            expect(modules).toContain('cash');
            expect(modules).toContain('orders');
            expect(modules.length).toBeGreaterThan(2);
        });

        it('should return assigned backoffice modules for non-admin', () => {
            const store = usePermissionsStore();
            store.init({ role: 'cashier', backofficeModules: ['dashboard', 'menu'] });

            const perms = usePermissions();

            expect(perms.getAvailableBackofficeModules()).toEqual(['dashboard', 'menu']);
        });

        it('should return all backoffice modules for admin', () => {
            const store = usePermissionsStore();
            store.init({ role: 'admin' });

            const perms = usePermissions();

            const modules = perms.getAvailableBackofficeModules();
            expect(modules).toContain('dashboard');
            expect(modules).toContain('settings');
            expect(modules.length).toBeGreaterThan(5);
        });
    });
});
