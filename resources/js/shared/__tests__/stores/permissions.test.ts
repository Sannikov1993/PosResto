/**
 * Permissions Store Unit Tests
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

import { usePermissionsStore } from '@/shared/stores/permissions.js';

describe('Permissions Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        localStorage.clear();
    });

    describe('Initial State', () => {
        it('should have correct initial state', () => {
            const store = usePermissionsStore();

            expect(store.permissions).toEqual([]);
            expect(store.userRole).toBeNull();
            expect(store.initialized).toBe(false);
            expect(store.isAdmin).toBe(false);
            expect(store.limits).toEqual({
                max_discount_percent: 0,
                max_refund_amount: 0,
                max_cancel_amount: 0,
            });
        });
    });

    describe('init', () => {
        it('should initialize with permissions and role', () => {
            const store = usePermissionsStore();

            store.init({
                permissions: ['orders.view', 'orders.create'],
                role: 'waiter',
                limits: { max_discount_percent: 10, max_refund_amount: 500, max_cancel_amount: 1000 },
            });

            expect(store.permissions).toEqual(['orders.view', 'orders.create']);
            expect(store.userRole).toBe('waiter');
            expect(store.initialized).toBe(true);
            expect(store.limits.max_discount_percent).toBe(10);
        });

        it('should initialize with module access', () => {
            const store = usePermissionsStore();

            store.init({
                posModules: ['cash', 'orders'],
                backofficeModules: ['dashboard', 'menu'],
                role: 'cashier',
            });

            expect(store.posModules).toEqual(['cash', 'orders']);
            expect(store.backofficeModules).toEqual(['dashboard', 'menu']);
        });

        it('should use defaults when no data provided', () => {
            const store = usePermissionsStore();
            store.init();

            expect(store.permissions).toEqual([]);
            expect(store.userRole).toBeNull();
            expect(store.initialized).toBe(true);
        });
    });

    describe('can', () => {
        it('should return true for admin roles', () => {
            const store = usePermissionsStore();
            store.init({ role: 'super_admin' });

            expect(store.can('orders.view')).toBe(true);
            expect(store.can('any.permission')).toBe(true);
        });

        it('should return true for owner role', () => {
            const store = usePermissionsStore();
            store.init({ role: 'owner' });

            expect(store.can('orders.delete')).toBe(true);
        });

        it('should return true for admin role', () => {
            const store = usePermissionsStore();
            store.init({ role: 'admin' });

            expect(store.can('finance.view')).toBe(true);
        });

        it('should check permission set for non-admin roles', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'waiter',
                permissions: ['orders.view', 'orders.create'],
            });

            expect(store.can('orders.view')).toBe(true);
            expect(store.can('orders.create')).toBe(true);
            expect(store.can('orders.delete')).toBe(false);
        });

        it('should return true for wildcard permission', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'manager',
                permissions: ['*'],
            });

            expect(store.can('anything')).toBe(true);
        });

        it('should return false for empty permission string', () => {
            const store = usePermissionsStore();
            store.init({ role: 'waiter', permissions: ['orders.view'] });

            expect(store.can('')).toBe(false);
        });
    });

    describe('canAny', () => {
        it('should return true if any permission exists', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'waiter',
                permissions: ['orders.view'],
            });

            expect(store.canAny(['orders.view', 'orders.delete'])).toBe(true);
        });

        it('should return false if no permissions match', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'waiter',
                permissions: ['orders.view'],
            });

            expect(store.canAny(['orders.delete', 'finance.view'])).toBe(false);
        });

        it('should return false for empty array', () => {
            const store = usePermissionsStore();
            store.init({ role: 'waiter' });

            expect(store.canAny([])).toBe(false);
        });
    });

    describe('canAll', () => {
        it('should return true if all permissions exist', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'waiter',
                permissions: ['orders.view', 'orders.create', 'orders.edit'],
            });

            expect(store.canAll(['orders.view', 'orders.create'])).toBe(true);
        });

        it('should return false if any permission is missing', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'waiter',
                permissions: ['orders.view'],
            });

            expect(store.canAll(['orders.view', 'orders.delete'])).toBe(false);
        });
    });

    describe('isAdmin', () => {
        it('should be true for super_admin', () => {
            const store = usePermissionsStore();
            store.init({ role: 'super_admin' });
            expect(store.isAdmin).toBe(true);
        });

        it('should be true for owner', () => {
            const store = usePermissionsStore();
            store.init({ role: 'owner' });
            expect(store.isAdmin).toBe(true);
        });

        it('should be true for admin', () => {
            const store = usePermissionsStore();
            store.init({ role: 'admin' });
            expect(store.isAdmin).toBe(true);
        });

        it('should be false for waiter', () => {
            const store = usePermissionsStore();
            store.init({ role: 'waiter' });
            expect(store.isAdmin).toBe(false);
        });

        it('should be false for cashier', () => {
            const store = usePermissionsStore();
            store.init({ role: 'cashier' });
            expect(store.isAdmin).toBe(false);
        });
    });

    describe('canApplyDiscount', () => {
        it('should return true for admin regardless of limit', () => {
            const store = usePermissionsStore();
            store.init({ role: 'super_admin' });

            expect(store.canApplyDiscount(100)).toBe(true);
        });

        it('should check permission and limit for non-admin', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'cashier',
                permissions: ['orders.discount'],
                limits: { max_discount_percent: 20, max_refund_amount: 0, max_cancel_amount: 0 },
            });

            expect(store.canApplyDiscount(15)).toBe(true);
            expect(store.canApplyDiscount(25)).toBe(false);
        });

        it('should return false without discount permission', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'waiter',
                permissions: ['orders.view'],
                limits: { max_discount_percent: 50, max_refund_amount: 0, max_cancel_amount: 0 },
            });

            expect(store.canApplyDiscount(10)).toBe(false);
        });
    });

    describe('canRefund', () => {
        it('should return true for admin', () => {
            const store = usePermissionsStore();
            store.init({ role: 'owner' });

            expect(store.canRefund(99999)).toBe(true);
        });

        it('should check limit for non-admin', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'cashier',
                permissions: ['orders.refund'],
                limits: { max_discount_percent: 0, max_refund_amount: 1000, max_cancel_amount: 0 },
            });

            expect(store.canRefund(500)).toBe(true);
            expect(store.canRefund(1500)).toBe(false);
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

            expect(store.canCancel(1500)).toBe(true);
            expect(store.canCancel(3000)).toBe(false);
        });
    });

    describe('canAccessInterface', () => {
        it('should return true for admin', () => {
            const store = usePermissionsStore();
            store.init({ role: 'super_admin' });

            expect(store.canAccessInterface('pos')).toBe(true);
            expect(store.canAccessInterface('kitchen')).toBe(true);
        });

        it('should check interface access flags for non-admin', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'waiter',
                interfaceAccess: { can_access_pos: true, can_access_kitchen: false },
            });

            expect(store.canAccessInterface('pos')).toBe(true);
            expect(store.canAccessInterface('kitchen')).toBe(false);
        });
    });

    describe('canAccessPosModule', () => {
        it('should return all modules for admin', () => {
            const store = usePermissionsStore();
            store.init({ role: 'admin' });

            expect(store.canAccessPosModule('cash')).toBe(true);
            expect(store.canAccessPosModule('orders')).toBe(true);
        });

        it('should check module list for non-admin', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'cashier',
                posModules: ['cash', 'orders'],
            });

            expect(store.canAccessPosModule('cash')).toBe(true);
            expect(store.canAccessPosModule('warehouse')).toBe(false);
        });
    });

    describe('getAvailablePosModules', () => {
        it('should return all modules for admin', () => {
            const store = usePermissionsStore();
            store.init({ role: 'super_admin' });

            const modules = store.getAvailablePosModules();
            expect(modules).toContain('cash');
            expect(modules).toContain('orders');
            expect(modules).toContain('delivery');
        });

        it('should return only assigned modules for non-admin', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'cashier',
                posModules: ['cash'],
            });

            expect(store.getAvailablePosModules()).toEqual(['cash']);
        });
    });

    describe('reset', () => {
        it('should reset to default state', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'admin',
                permissions: ['orders.view'],
                limits: { max_discount_percent: 50, max_refund_amount: 0, max_cancel_amount: 0 },
            });

            store.reset();

            expect(store.permissions).toEqual([]);
            expect(store.userRole).toBeNull();
            expect(store.initialized).toBe(false);
            expect(store.limits.max_discount_percent).toBe(0);
        });
    });

    describe('updatePermissions', () => {
        it('should update permissions list', () => {
            const store = usePermissionsStore();
            store.init({ role: 'waiter', permissions: ['old.permission'] });

            store.updatePermissions(['new.permission']);

            expect(store.permissions).toEqual(['new.permission']);
        });
    });

    describe('updateLimits', () => {
        it('should merge new limits with defaults', () => {
            const store = usePermissionsStore();
            store.init({ role: 'cashier' });

            store.updateLimits({ max_discount_percent: 30 });

            expect(store.limits.max_discount_percent).toBe(30);
            expect(store.limits.max_refund_amount).toBe(0);
        });
    });

    describe('maxDiscountPercent computed', () => {
        it('should return 100 for admin', () => {
            const store = usePermissionsStore();
            store.init({ role: 'super_admin' });

            expect(store.maxDiscountPercent).toBe(100);
        });

        it('should return limit value for non-admin', () => {
            const store = usePermissionsStore();
            store.init({
                role: 'cashier',
                limits: { max_discount_percent: 25, max_refund_amount: 0, max_cancel_amount: 0 },
            });

            expect(store.maxDiscountPercent).toBe(25);
        });
    });
});
