/**
 * PermissionsStore - Centralized permission management
 *
 * @module shared/stores/permissions
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { createLogger } from '../services/logger.js';
import {
    STORAGE_KEYS,
    BROADCAST_CHANNELS,
    getRestaurantId,
    setRestaurantId as storageSetRestaurantId,
    clearRestaurantId as storageClearRestaurantId,
} from '../constants/storage.js';

const log = createLogger('Permissions');

interface UserLimits {
    max_discount_percent: number;
    max_refund_amount: number;
    max_cancel_amount: number;
}

interface InterfaceAccessFlags {
    can_access_pos: boolean;
    can_access_backoffice: boolean;
    can_access_kitchen: boolean;
    can_access_delivery: boolean;
    [key: string]: boolean;
}

export interface PermissionsInitData {
    permissions?: string[];
    limits?: Partial<UserLimits>;
    interfaceAccess?: Partial<InterfaceAccessFlags>;
    posModules?: string[];
    backofficeModules?: string[];
    role?: string | null;
}

const DEFAULT_LIMITS: UserLimits = {
    max_discount_percent: 0,
    max_refund_amount: 0,
    max_cancel_amount: 0,
};

const DEFAULT_INTERFACE_ACCESS: InterfaceAccessFlags = {
    can_access_pos: false,
    can_access_backoffice: false,
    can_access_kitchen: false,
    can_access_delivery: false,
};

const ALL_POS_MODULES = ['cash', 'orders', 'delivery', 'customers', 'warehouse', 'stoplist', 'writeoffs', 'settings'];
const ALL_BACKOFFICE_MODULES = ['dashboard', 'menu', 'pricelists', 'hall', 'staff', 'attendance', 'inventory', 'customers', 'loyalty', 'delivery', 'finance', 'analytics', 'integrations', 'settings'];
const ADMIN_ROLES = ['super_admin', 'owner', 'admin'];

export const usePermissionsStore = defineStore('permissions', () => {
    const permissions = ref<string[]>([]);
    const limits = ref<UserLimits>({ ...DEFAULT_LIMITS });
    const interfaceAccess = ref<InterfaceAccessFlags>({ ...DEFAULT_INTERFACE_ACCESS });
    const userRole = ref<string | null>(null);
    const posModules = ref<string[]>([]);
    const backofficeModules = ref<string[]>([]);
    const initialized = ref(false);
    const restaurantId = ref<string | null>(getRestaurantId());
    const tenantId = ref<string | null>(localStorage.getItem(STORAGE_KEYS.TENANT_ID) || null);
    let restaurantSyncChannel: BroadcastChannel | null = null;

    const maxDiscountPercent = computed(() => {
        if (isAdmin.value) return 100;
        return limits.value.max_discount_percent || 0;
    });

    const maxRefundAmount = computed(() => {
        if (isAdmin.value) return Infinity;
        return limits.value.max_refund_amount || 0;
    });

    const maxCancelAmount = computed(() => {
        if (isAdmin.value) return Infinity;
        return limits.value.max_cancel_amount || 0;
    });

    const isAdmin = computed(() => {
        return ADMIN_ROLES.includes(userRole.value as string);
    });

    const permissionSet = computed(() => new Set(permissions.value));
    const posModuleSet = computed(() => new Set(posModules.value));
    const backofficeModuleSet = computed(() => new Set(backofficeModules.value));

    function can(permission: string): boolean {
        if (!permission) return false;
        if (isAdmin.value) return true;
        if (permissionSet.value.has('*')) return true;
        return permissionSet.value.has(permission);
    }

    function canAny(perms: string[]): boolean {
        if (!Array.isArray(perms) || perms.length === 0) return false;
        if (isAdmin.value) return true;
        if (permissionSet.value.has('*')) return true;
        return perms.some((p: any) => permissionSet.value.has(p));
    }

    function canAll(perms: string[]): boolean {
        if (!Array.isArray(perms) || perms.length === 0) return false;
        if (isAdmin.value) return true;
        if (permissionSet.value.has('*')) return true;
        return perms.every((p: any) => permissionSet.value.has(p));
    }

    function canApplyDiscount(percent: number): boolean {
        if (!can('orders.discount')) return false;
        return maxDiscountPercent.value >= percent;
    }

    function canRefund(amount: number): boolean {
        if (!can('orders.refund')) return false;
        return maxRefundAmount.value >= amount;
    }

    function canCancel(amount: number): boolean {
        if (!can('orders.cancel')) return false;
        return maxCancelAmount.value >= amount;
    }

    function canAccessInterface(interfaceName: string): boolean {
        if (isAdmin.value) return true;
        const key = `can_access_${interfaceName}`;
        return interfaceAccess.value[key] === true;
    }

    function canAccessPosModule(module: string): boolean {
        if (isAdmin.value) return true;
        return posModuleSet.value.has(module);
    }

    function canAccessBackofficeModule(module: string): boolean {
        if (isAdmin.value) return true;
        return backofficeModuleSet.value.has(module);
    }

    function getAvailablePosModules(): string[] {
        if (isAdmin.value) return ALL_POS_MODULES;
        return [...posModules.value];
    }

    function getAvailableBackofficeModules(): string[] {
        if (isAdmin.value) return ALL_BACKOFFICE_MODULES;
        return [...backofficeModules.value];
    }

    function init(data: PermissionsInitData = {}): void {
        permissions.value = data.permissions || [];
        limits.value = { ...DEFAULT_LIMITS, ...(data.limits || {}) };
        interfaceAccess.value = { ...DEFAULT_INTERFACE_ACCESS, ...(data.interfaceAccess || {}) } as InterfaceAccessFlags;
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

    function reset(): void {
        permissions.value = [];
        limits.value = { ...DEFAULT_LIMITS };
        interfaceAccess.value = { ...DEFAULT_INTERFACE_ACCESS };
        posModules.value = [];
        backofficeModules.value = [];
        userRole.value = null;
        initialized.value = false;
        log.debug('Reset (restaurant context preserved)');
    }

    function updatePermissions(newPermissions: string[]): void {
        permissions.value = newPermissions || [];
    }

    function updateLimits(newLimits: Partial<UserLimits>): void {
        limits.value = { ...DEFAULT_LIMITS, ...(newLimits || {}) };
    }

    function setRestaurantId(id: string | number): void {
        if (!id) return;
        const value = String(id);
        restaurantId.value = value;
        storageSetRestaurantId(value);
        log.debug('Restaurant ID set:', value);
    }

    function setTenantId(id: string | number): void {
        if (!id) return;
        const value = String(id);
        tenantId.value = value;
        localStorage.setItem(STORAGE_KEYS.TENANT_ID, value);
    }

    function clearRestaurantContext(): void {
        restaurantId.value = null;
        tenantId.value = null;
        storageClearRestaurantId();
        localStorage.removeItem(STORAGE_KEYS.TENANT_ID);
    }

    function initRestaurantSync(): void {
        if (restaurantSyncChannel) return;

        try {
            restaurantSyncChannel = new BroadcastChannel(BROADCAST_CHANNELS.RESTAURANT_SYNC);
            restaurantSyncChannel.onmessage = (event: MessageEvent) => {
                if (event.data?.type === 'restaurant_change' && event.data?.restaurantId) {
                    log.debug('Restaurant changed from another tab:', event.data.restaurantId);
                    restaurantId.value = event.data.restaurantId;
                }
            };
        } catch {
            // BroadcastChannel not supported
        }
    }

    function destroyRestaurantSync(): void {
        if (restaurantSyncChannel) {
            restaurantSyncChannel.close();
            restaurantSyncChannel = null;
        }
    }

    function getPermissions(): string[] {
        return [...permissions.value];
    }

    return {
        permissions,
        limits,
        interfaceAccess,
        posModules,
        backofficeModules,
        userRole,
        initialized,
        maxDiscountPercent,
        maxRefundAmount,
        maxCancelAmount,
        isAdmin,
        can,
        canAny,
        canAll,
        canApplyDiscount,
        canRefund,
        canCancel,
        canAccessInterface,
        canAccessPosModule,
        canAccessBackofficeModule,
        getAvailablePosModules,
        getAvailableBackofficeModules,
        init,
        reset,
        updatePermissions,
        updateLimits,
        getPermissions,
        restaurantId,
        tenantId,
        setRestaurantId,
        setTenantId,
        clearRestaurantContext,
        initRestaurantSync,
        destroyRestaurantSync,
    };
});
