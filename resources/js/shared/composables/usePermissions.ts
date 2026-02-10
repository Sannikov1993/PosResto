/**
 * usePermissions - Composable for permission checks in components
 *
 * @module shared/composables/usePermissions
 */

import { computed } from 'vue';
import { usePermissionsStore } from '../stores/permissions.js';

export function usePermissions() {
    const store = usePermissionsStore();

    return {
        permissions: computed(() => store.permissions),
        limits: computed(() => store.limits),
        interfaceAccess: computed(() => store.interfaceAccess),
        posModules: computed(() => store.posModules),
        backofficeModules: computed(() => store.backofficeModules),
        userRole: computed(() => store.userRole),
        isAdmin: computed(() => store.isAdmin),
        initialized: computed(() => store.initialized),
        maxDiscountPercent: computed(() => store.maxDiscountPercent),
        maxRefundAmount: computed(() => store.maxRefundAmount),
        maxCancelAmount: computed(() => store.maxCancelAmount),
        can: (permission: string) => store.can(permission),
        canAny: (perms: string[]) => store.canAny(perms),
        canAll: (perms: string[]) => store.canAll(perms),
        canApplyDiscount: (percent: number) => store.canApplyDiscount(percent),
        canRefund: (amount: number) => store.canRefund(amount),
        canCancel: (amount: number) => store.canCancel(amount),
        canAccessInterface: (interfaceName: string) => store.canAccessInterface(interfaceName),
        canAccessPosModule: (module: string) => store.canAccessPosModule(module),
        canAccessBackofficeModule: (module: string) => store.canAccessBackofficeModule(module),
        getAvailablePosModules: () => store.getAvailablePosModules(),
        getAvailableBackofficeModules: () => store.getAvailableBackofficeModules(),
    };
}
