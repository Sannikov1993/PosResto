/**
 * usePermissions - Composable for permission checks in components
 *
 * Provides reactive access to permission checks and limits.
 *
 * @module shared/composables/usePermissions
 *
 * @example
 * const { can, canApplyDiscount, maxDiscountPercent } = usePermissions();
 *
 * // In computed
 * const showButton = computed(() => can('orders.discount'));
 *
 * // Check limit
 * if (!canApplyDiscount(15)) {
 *     alert(`Max discount: ${maxDiscountPercent.value}%`);
 * }
 */

import { computed } from 'vue';
import { usePermissionsStore } from '../stores/permissions.js';

/**
 * Composable for permission checks
 * @returns {Object} Permission utilities
 */
export function usePermissions() {
    const store = usePermissionsStore();

    return {
        // ─────────────────────────────────────────────────────────────────────────
        // Reactive state
        // ─────────────────────────────────────────────────────────────────────────

        /** All permissions array */
        permissions: computed(() => store.permissions),

        /** User limits */
        limits: computed(() => store.limits),

        /** Interface access flags */
        interfaceAccess: computed(() => store.interfaceAccess),

        /** Available POS modules */
        posModules: computed(() => store.posModules),

        /** Available Backoffice modules */
        backofficeModules: computed(() => store.backofficeModules),

        /** User role */
        userRole: computed(() => store.userRole),

        /** Whether user is admin */
        isAdmin: computed(() => store.isAdmin),

        /** Whether store is initialized */
        initialized: computed(() => store.initialized),

        // ─────────────────────────────────────────────────────────────────────────
        // Limit values
        // ─────────────────────────────────────────────────────────────────────────

        /** Maximum discount percentage */
        maxDiscountPercent: computed(() => store.maxDiscountPercent),

        /** Maximum refund amount */
        maxRefundAmount: computed(() => store.maxRefundAmount),

        /** Maximum cancel amount */
        maxCancelAmount: computed(() => store.maxCancelAmount),

        // ─────────────────────────────────────────────────────────────────────────
        // Permission check methods
        // ─────────────────────────────────────────────────────────────────────────

        /**
         * Check if user has a specific permission
         * @param {string} permission - Permission to check
         * @returns {boolean}
         */
        can: (permission) => store.can(permission),

        /**
         * Check if user has ANY of the specified permissions
         * @param {string[]} perms - Permissions to check
         * @returns {boolean}
         */
        canAny: (perms) => store.canAny(perms),

        /**
         * Check if user has ALL of the specified permissions
         * @param {string[]} perms - Permissions to check
         * @returns {boolean}
         */
        canAll: (perms) => store.canAll(perms),

        // ─────────────────────────────────────────────────────────────────────────
        // Limit check methods
        // ─────────────────────────────────────────────────────────────────────────

        /**
         * Check if user can apply a discount of given percentage
         * @param {number} percent - Discount percentage
         * @returns {boolean}
         */
        canApplyDiscount: (percent) => store.canApplyDiscount(percent),

        /**
         * Check if user can perform a refund of given amount
         * @param {number} amount - Refund amount
         * @returns {boolean}
         */
        canRefund: (amount) => store.canRefund(amount),

        /**
         * Check if user can cancel an order of given amount
         * @param {number} amount - Order amount
         * @returns {boolean}
         */
        canCancel: (amount) => store.canCancel(amount),

        // ─────────────────────────────────────────────────────────────────────────
        // Interface access
        // ─────────────────────────────────────────────────────────────────────────

        /**
         * Check if user can access a specific interface
         * @param {string} interfaceName - Interface name
         * @returns {boolean}
         */
        canAccessInterface: (interfaceName) => store.canAccessInterface(interfaceName),

        // ─────────────────────────────────────────────────────────────────────────
        // Module access (Level 2)
        // ─────────────────────────────────────────────────────────────────────────

        /**
         * Check if user can access a specific POS module/tab
         * @param {string} module - Module name ('cash', 'orders', 'delivery', etc.)
         * @returns {boolean}
         */
        canAccessPosModule: (module) => store.canAccessPosModule(module),

        /**
         * Check if user can access a specific Backoffice module/tab
         * @param {string} module - Module name ('dashboard', 'menu', 'staff', etc.)
         * @returns {boolean}
         */
        canAccessBackofficeModule: (module) => store.canAccessBackofficeModule(module),

        /**
         * Get available POS modules for current user
         * @returns {string[]}
         */
        getAvailablePosModules: () => store.getAvailablePosModules(),

        /**
         * Get available Backoffice modules for current user
         * @returns {string[]}
         */
        getAvailableBackofficeModules: () => store.getAvailableBackofficeModules(),
    };
}
