/**
 * Navigation Store Unit Tests
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

import { useNavigationStore } from '@/shared/stores/navigation.js';
import { usePermissionsStore } from '@/shared/stores/permissions.js';

describe('Navigation Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        localStorage.clear();

        // Set up default: replace window.location.hash
        Object.defineProperty(window, 'location', {
            value: {
                hash: '',
                pathname: '/',
                search: '',
            },
            writable: true,
        });

        // Mock history.replaceState
        vi.spyOn(window.history, 'replaceState').mockImplementation(() => {});
    });

    function setupAdminPermissions() {
        const permsStore = usePermissionsStore();
        permsStore.init({
            role: 'super_admin',
            permissions: ['*'],
            posModules: ['cash', 'orders', 'delivery', 'customers', 'warehouse', 'stoplist', 'writeoffs', 'settings'],
            interfaceAccess: {
                can_access_pos: true,
                can_access_kitchen: true,
                can_access_delivery: true,
                can_access_backoffice: true,
            },
        });
    }

    describe('Initial State', () => {
        it('should have correct initial state', () => {
            const store = useNavigationStore();

            expect(store.activeTab).toBeNull();
            expect(store.previousTab).toBeNull();
            expect(store.initialized).toBe(false);
            expect(store.navigationHistory).toEqual([]);
        });

        it('should have canGoBack as false initially', () => {
            const store = useNavigationStore();
            expect(store.canGoBack).toBe(false);
        });
    });

    describe('init', () => {
        it('should initialize with default tab when no context is provided', () => {
            setupAdminPermissions();
            const store = useNavigationStore();

            store.init({ restaurantId: 1 });

            expect(store.initialized).toBe(true);
            expect(store.activeTab).not.toBeNull();
        });

        it('should set initialized to true', () => {
            setupAdminPermissions();
            const store = useNavigationStore();

            store.init({ restaurantId: 1 });

            expect(store.initialized).toBe(true);
        });

        it('should add initial tab to navigation history', () => {
            setupAdminPermissions();
            const store = useNavigationStore();

            store.init({ restaurantId: 1 });

            expect(store.navigationHistory.length).toBeGreaterThanOrEqual(1);
        });

        it('should not re-initialize if already initialized', () => {
            setupAdminPermissions();
            const store = useNavigationStore();

            store.init({ restaurantId: 1 });
            const firstTab = store.activeTab;

            store.init({ restaurantId: 2 });
            // Should update context but not re-run full init
            expect(store.activeTab).toBe(firstTab);
        });

        it('should persist state to localStorage on init', () => {
            setupAdminPermissions();
            const store = useNavigationStore();

            store.init({ restaurantId: 1 });

            const stored = localStorage.getItem('menulab_navigation');
            expect(stored).not.toBeNull();
            const parsed = JSON.parse(stored!);
            expect(parsed.activeTab).toBeTruthy();
        });

        it('should restore persisted state from localStorage', () => {
            setupAdminPermissions();

            const persistedState = {
                activeTab: 'orders',
                history: ['cash', 'orders'],
                timestamp: Date.now(),
            };
            localStorage.setItem('menulab_navigation', JSON.stringify(persistedState));

            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            expect(store.activeTab).toBe('orders');
        });
    });

    describe('navigateTo', () => {
        it('should navigate to a valid tab', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            const result = store.navigateTo('orders');

            expect(result).toBe(true);
            expect(store.activeTab).toBe('orders');
        });

        it('should set previousTab on navigation', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            const firstTab = store.activeTab;
            store.navigateTo('orders');

            expect(store.previousTab).toBe(firstTab);
        });

        it('should return false for unknown tab', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            const result = store.navigateTo('nonexistent');

            expect(result).toBe(false);
        });

        it('should return true when navigating to the current tab (no-op)', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            store.navigateTo('orders');
            const result = store.navigateTo('orders');

            expect(result).toBe(true);
        });

        it('should add tab to navigation history', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            store.navigateTo('orders');
            store.navigateTo('customers');

            expect(store.navigationHistory).toContain('orders');
            expect(store.navigationHistory).toContain('customers');
        });

        it('should replace last history entry when replace option is true', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            store.navigateTo('orders');
            const historyLenBefore = store.navigationHistory.length;
            store.navigateTo('customers', { replace: true });

            expect(store.navigationHistory.length).toBe(historyLenBefore);
            expect(store.navigationHistory[store.navigationHistory.length - 1]).toBe('customers');
        });

        it('should respect access control and deny navigation without permission', () => {
            const permsStore = usePermissionsStore();
            permsStore.init({
                role: 'waiter',
                permissions: ['orders.view'],
                posModules: ['orders'],
            });

            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            // warehouse requires 'inventory.view' which waiter does not have
            const result = store.navigateTo('warehouse');
            expect(result).toBe(false);
        });

        it('should allow navigation with skipValidation', () => {
            const permsStore = usePermissionsStore();
            permsStore.init({
                role: 'waiter',
                permissions: ['orders.view'],
                posModules: ['orders'],
            });

            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            const result = store.navigateTo('warehouse', { skipValidation: true });
            expect(result).toBe(true);
            expect(store.activeTab).toBe('warehouse');
        });
    });

    describe('goBack', () => {
        it('should return false when there is no history to go back to', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            // Only one tab in history
            const result = store.goBack();
            expect(result).toBe(false);
        });

        it('should navigate to previous tab in history', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            store.navigateTo('orders');
            store.navigateTo('customers');

            const result = store.goBack();
            expect(result).toBe(true);
            expect(store.activeTab).toBe('orders');
        });

        it('should update canGoBack computed after going back', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            store.navigateTo('orders');
            expect(store.canGoBack).toBe(true);

            store.goBack();
            expect(store.canGoBack).toBe(false);
        });
    });

    describe('canAccessTab', () => {
        it('should return false for an unknown tab id', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            expect(store.canAccessTab('nonexistent')).toBe(false);
        });

        it('should return true for admin with restaurantId', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            expect(store.canAccessTab('cash')).toBe(true);
            expect(store.canAccessTab('orders')).toBe(true);
        });

        it('should return false for tabs requiring restaurant when no restaurant is set', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({}); // no restaurantId

            // cash requires restaurant
            expect(store.canAccessTab('cash')).toBe(false);
        });
    });

    describe('reset', () => {
        it('should reset all state to defaults', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });
            store.navigateTo('orders');

            store.reset();

            expect(store.activeTab).toBeNull();
            expect(store.previousTab).toBeNull();
            expect(store.initialized).toBe(false);
            expect(store.navigationHistory).toEqual([]);
        });

        it('should clear persisted state from localStorage', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            store.reset();

            expect(localStorage.getItem('menulab_navigation')).toBeNull();
        });
    });

    describe('availableTabs', () => {
        it('should return all tabs for admin with restaurant', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });

            const tabs = store.availableTabs;
            expect(tabs.length).toBeGreaterThan(0);

            const tabIds = tabs.map((t: any) => t.id);
            expect(tabIds).toContain('cash');
            expect(tabIds).toContain('orders');
        });
    });

    describe('getTabDefinitions', () => {
        it('should return all tab definitions', () => {
            setupAdminPermissions();
            const store = useNavigationStore();

            const defs = store.getTabDefinitions();

            expect(defs).toHaveProperty('cash');
            expect(defs).toHaveProperty('orders');
            expect(defs).toHaveProperty('settings');
            expect(defs.cash.id).toBe('cash');
        });
    });

    describe('currentTabDefinition', () => {
        it('should return null when no active tab', () => {
            const store = useNavigationStore();
            expect(store.currentTabDefinition).toBeNull();
        });

        it('should return the definition for the active tab', () => {
            setupAdminPermissions();
            const store = useNavigationStore();
            store.init({ restaurantId: 1 });
            store.navigateTo('orders');

            expect(store.currentTabDefinition).not.toBeNull();
            expect(store.currentTabDefinition!.id).toBe('orders');
        });
    });
});
