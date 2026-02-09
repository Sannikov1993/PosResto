/**
 * PermissionsStore - Centralized permission management
 *
 * Enterprise-level RBAC integration for MenuLab applications.
 * Provides unified permission checking across POS, Waiter, and Backoffice apps.
 *
 * @module shared/stores/permissions
 */

import { defineStore } from 'pinia';
import { ref, computed, onUnmounted } from 'vue';
import { createLogger } from '../services/logger.js';

const log = createLogger('Permissions');
import {
    STORAGE_KEYS,
    BROADCAST_CHANNELS,
    getRestaurantId,
    setRestaurantId as storageSetRestaurantId,
    clearRestaurantId as storageClearRestaurantId,
} from '../constants/storage.js';

/**
 * Default limits structure
 * @type {Object}
 */
const DEFAULT_LIMITS = {
    max_discount_percent: 0,
    max_refund_amount: 0,
    max_cancel_amount: 0,
};

/**
 * Default interface access structure
 * @type {Object}
 */
const DEFAULT_INTERFACE_ACCESS = {
    can_access_pos: false,
    can_access_backoffice: false,
    can_access_kitchen: false,
    can_access_delivery: false,
};

/**
 * All available POS modules
 * @type {string[]}
 */
const ALL_POS_MODULES = ['cash', 'orders', 'delivery', 'customers', 'warehouse', 'stoplist', 'writeoffs', 'settings'];

/**
 * All available Backoffice modules
 * @type {string[]}
 */
const ALL_BACKOFFICE_MODULES = ['dashboard', 'menu', 'pricelists', 'hall', 'staff', 'attendance', 'inventory', 'customers', 'loyalty', 'delivery', 'finance', 'analytics', 'integrations', 'settings'];

/**
 * Roles that bypass permission checks (have full access)
 * @type {string[]}
 */
const ADMIN_ROLES = ['super_admin', 'owner', 'admin'];

export const usePermissionsStore = defineStore('permissions', () => {
    // ═══════════════════════════════════════════════════════════════════════════
    // STATE
    // ═══════════════════════════════════════════════════════════════════════════

    /** User permissions array */
    const permissions = ref([]);

    /** User limits (discount, refund, cancel) */
    const limits = ref({ ...DEFAULT_LIMITS });

    /** Interface access flags */
    const interfaceAccess = ref({ ...DEFAULT_INTERFACE_ACCESS });

    /** User role */
    const userRole = ref(null);

    /** Available POS modules for user */
    const posModules = ref([]);

    /** Available Backoffice modules for user */
    const backofficeModules = ref([]);

    /** Whether store is initialized */
    const initialized = ref(false);

    // ═══════════════════════════════════════════════════════════════════════════
    // RESTAURANT CONTEXT (centralized for all apps)
    // ═══════════════════════════════════════════════════════════════════════════

    /** Current restaurant ID - единый источник правды */
    const restaurantId = ref(getRestaurantId());

    /** Current tenant ID */
    const tenantId = ref(localStorage.getItem(STORAGE_KEYS.TENANT_ID) || null);

    /** BroadcastChannel for cross-tab sync */
    let restaurantSyncChannel = null;

    // ═══════════════════════════════════════════════════════════════════════════
    // GETTERS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Maximum discount percentage
     */
    const maxDiscountPercent = computed(() => {
        if (isAdmin.value) return 100;
        return limits.value.max_discount_percent || 0;
    });

    /**
     * Maximum refund amount
     */
    const maxRefundAmount = computed(() => {
        if (isAdmin.value) return Infinity;
        return limits.value.max_refund_amount || 0;
    });

    /**
     * Maximum cancel amount
     */
    const maxCancelAmount = computed(() => {
        if (isAdmin.value) return Infinity;
        return limits.value.max_cancel_amount || 0;
    });

    /**
     * Whether current user is an admin (bypasses permission checks)
     */
    const isAdmin = computed(() => {
        return ADMIN_ROLES.includes(userRole.value);
    });

    /**
     * All permissions as a Set for O(1) lookups
     */
    const permissionSet = computed(() => new Set(permissions.value));

    /**
     * POS modules as a Set for O(1) lookups
     */
    const posModuleSet = computed(() => new Set(posModules.value));

    /**
     * Backoffice modules as a Set for O(1) lookups
     */
    const backofficeModuleSet = computed(() => new Set(backofficeModules.value));

    // ═══════════════════════════════════════════════════════════════════════════
    // PERMISSION CHECKS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Check if user has a specific permission
     * @param {string} permission - Permission to check (e.g., 'orders.create')
     * @returns {boolean}
     */
    function can(permission) {
        if (!permission) return false;
        if (isAdmin.value) return true;
        if (permissionSet.value.has('*')) return true;
        return permissionSet.value.has(permission);
    }

    /**
     * Check if user has ANY of the specified permissions
     * @param {string[]} perms - Array of permissions to check
     * @returns {boolean}
     */
    function canAny(perms) {
        if (!Array.isArray(perms) || perms.length === 0) return false;
        if (isAdmin.value) return true;
        if (permissionSet.value.has('*')) return true;
        return perms.some(p => permissionSet.value.has(p));
    }

    /**
     * Check if user has ALL of the specified permissions
     * @param {string[]} perms - Array of permissions to check
     * @returns {boolean}
     */
    function canAll(perms) {
        if (!Array.isArray(perms) || perms.length === 0) return false;
        if (isAdmin.value) return true;
        if (permissionSet.value.has('*')) return true;
        return perms.every(p => permissionSet.value.has(p));
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // LIMIT CHECKS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Check if user can apply a discount of given percentage
     * @param {number} percent - Discount percentage
     * @returns {boolean}
     */
    function canApplyDiscount(percent) {
        if (!can('orders.discount')) return false;
        return maxDiscountPercent.value >= percent;
    }

    /**
     * Check if user can perform a refund of given amount
     * @param {number} amount - Refund amount
     * @returns {boolean}
     */
    function canRefund(amount) {
        if (!can('orders.refund')) return false;
        return maxRefundAmount.value >= amount;
    }

    /**
     * Check if user can cancel an order of given amount
     * @param {number} amount - Order amount
     * @returns {boolean}
     */
    function canCancel(amount) {
        if (!can('orders.cancel')) return false;
        return maxCancelAmount.value >= amount;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // INTERFACE ACCESS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Check if user can access a specific interface
     * @param {string} interfaceName - Interface name ('pos', 'backoffice', 'kitchen', 'delivery')
     * @returns {boolean}
     */
    function canAccessInterface(interfaceName) {
        if (isAdmin.value) return true;
        const key = `can_access_${interfaceName}`;
        return interfaceAccess.value[key] === true;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // MODULE ACCESS (Level 2 - Tab/Module level)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Check if user can access a specific POS module/tab
     * @param {string} module - Module name ('cash', 'orders', 'delivery', etc.)
     * @returns {boolean}
     */
    function canAccessPosModule(module) {
        if (isAdmin.value) return true;
        return posModuleSet.value.has(module);
    }

    /**
     * Check if user can access a specific Backoffice module/tab
     * @param {string} module - Module name ('dashboard', 'menu', 'staff', etc.)
     * @returns {boolean}
     */
    function canAccessBackofficeModule(module) {
        if (isAdmin.value) return true;
        return backofficeModuleSet.value.has(module);
    }

    /**
     * Get available POS modules for current user
     * @returns {string[]}
     */
    function getAvailablePosModules() {
        if (isAdmin.value) return ALL_POS_MODULES;
        return [...posModules.value];
    }

    /**
     * Get available Backoffice modules for current user
     * @returns {string[]}
     */
    function getAvailableBackofficeModules() {
        if (isAdmin.value) return ALL_BACKOFFICE_MODULES;
        return [...backofficeModules.value];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ACTIONS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Initialize permissions store with auth data
     * @param {Object} data - Permissions data from auth response
     * @param {string[]} data.permissions - User permissions
     * @param {Object} data.limits - User limits
     * @param {Object} data.interfaceAccess - Interface access flags
     * @param {string[]} data.posModules - Available POS modules
     * @param {string[]} data.backofficeModules - Available Backoffice modules
     * @param {string} data.role - User role
     */
    function init(data = {}) {
        permissions.value = data.permissions || [];
        limits.value = { ...DEFAULT_LIMITS, ...(data.limits || {}) };
        interfaceAccess.value = { ...DEFAULT_INTERFACE_ACCESS, ...(data.interfaceAccess || {}) };
        posModules.value = data.posModules || [];
        backofficeModules.value = data.backofficeModules || [];
        userRole.value = data.role || null;
        initialized.value = true;

        log.debug('Initialized:', {
            permissions: permissions.value.length,
            role: userRole.value,
            limits: limits.value,
            posModules: posModules.value,
            backofficeModules: backofficeModules.value,
        });
    }

    /**
     * Reset permissions store (on logout)
     * Note: Does NOT clear restaurant context - terminal stays bound to restaurant
     * Use clearRestaurantContext() explicitly if needed (e.g., switching tenants)
     */
    function reset() {
        permissions.value = [];
        limits.value = { ...DEFAULT_LIMITS };
        interfaceAccess.value = { ...DEFAULT_INTERFACE_ACCESS };
        posModules.value = [];
        backofficeModules.value = [];
        userRole.value = null;
        initialized.value = false;

        // NOTE: Intentionally NOT clearing restaurant context
        // Restaurant is a terminal/browser context, not a user session property
        // POS terminal should stay bound to restaurant after employee logout

        log.debug('Reset (restaurant context preserved)');
    }

    /**
     * Update permissions (e.g., when role changes)
     * @param {string[]} newPermissions - New permissions array
     */
    function updatePermissions(newPermissions) {
        permissions.value = newPermissions || [];
    }

    /**
     * Update limits
     * @param {Object} newLimits - New limits object
     */
    function updateLimits(newLimits) {
        limits.value = { ...DEFAULT_LIMITS, ...(newLimits || {}) };
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // RESTAURANT CONTEXT MANAGEMENT
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Set current restaurant ID
     * Automatically syncs to localStorage and broadcasts to other tabs
     * @param {string|number} id - Restaurant ID
     */
    function setRestaurantId(id) {
        if (!id) return;
        const value = String(id);
        restaurantId.value = value;
        storageSetRestaurantId(value);
        log.debug('Restaurant ID set:', value);
    }

    /**
     * Set current tenant ID
     * @param {string|number} id - Tenant ID
     */
    function setTenantId(id) {
        if (!id) return;
        const value = String(id);
        tenantId.value = value;
        localStorage.setItem(STORAGE_KEYS.TENANT_ID, value);
    }

    /**
     * Clear restaurant context (on logout)
     */
    function clearRestaurantContext() {
        restaurantId.value = null;
        tenantId.value = null;
        storageClearRestaurantId();
        localStorage.removeItem(STORAGE_KEYS.TENANT_ID);
    }

    /**
     * Initialize cross-tab restaurant sync
     * Call this once when app initializes
     */
    function initRestaurantSync() {
        if (restaurantSyncChannel) return;

        try {
            restaurantSyncChannel = new BroadcastChannel(BROADCAST_CHANNELS.RESTAURANT_SYNC);
            restaurantSyncChannel.onmessage = (event) => {
                if (event.data?.type === 'restaurant_change' && event.data?.restaurantId) {
                    log.debug('Restaurant changed from another tab:', event.data.restaurantId);
                    restaurantId.value = event.data.restaurantId;
                }
            };
        } catch (e) {
            // BroadcastChannel not supported
        }
    }

    /**
     * Cleanup restaurant sync channel
     */
    function destroyRestaurantSync() {
        if (restaurantSyncChannel) {
            restaurantSyncChannel.close();
            restaurantSyncChannel = null;
        }
    }

    /**
     * Get all permissions (for debugging)
     * @returns {string[]}
     */
    function getPermissions() {
        return [...permissions.value];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // RETURN
    // ═══════════════════════════════════════════════════════════════════════════

    return {
        // State
        permissions,
        limits,
        interfaceAccess,
        posModules,
        backofficeModules,
        userRole,
        initialized,

        // Getters
        maxDiscountPercent,
        maxRefundAmount,
        maxCancelAmount,
        isAdmin,

        // Permission checks
        can,
        canAny,
        canAll,

        // Limit checks
        canApplyDiscount,
        canRefund,
        canCancel,

        // Interface access
        canAccessInterface,

        // Module access (Level 2)
        canAccessPosModule,
        canAccessBackofficeModule,
        getAvailablePosModules,
        getAvailableBackofficeModules,

        // Actions
        init,
        reset,
        updatePermissions,
        updateLimits,
        getPermissions,

        // Restaurant context
        restaurantId,
        tenantId,
        setRestaurantId,
        setTenantId,
        clearRestaurantContext,
        initRestaurantSync,
        destroyRestaurantSync,
    };
});
