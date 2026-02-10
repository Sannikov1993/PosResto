/**
 * Auth Store - Device-Session модель (как iiko/Saby Presto)
 *
 * 3 состояния: logged_out → active → locked
 * Устройство привязано к ресторану, сотрудники переключаются по PIN.
 *
 * @module stores/auth
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../api/index.js';
import authService from '../../shared/services/auth.js';
import { usePermissionsStore } from '@/shared/stores/permissions.js';
import { getRestaurantId } from '@/shared/constants/storage.js';
import { createLogger } from '../../shared/services/logger.js';
import type { User, Restaurant } from '@/shared/types';
import type { PosLimits } from '../types/pos.js';

const log = createLogger('POS:Auth');

type SessionState = 'logged_out' | 'active' | 'locked';

interface InterfaceAccess {
    can_access_pos: boolean;
    can_access_backoffice: boolean;
    can_access_kitchen: boolean;
    can_access_delivery: boolean;
}

interface LoginResult {
    success: boolean;
    message?: string;
    reason?: string;
    require_full_login?: boolean;
}

interface AuthResponseData {
    user: User;
    token: string;
    permissions?: string[];
    limits?: PosLimits;
    interfaceAccess?: InterfaceAccess;
    interface_access?: InterfaceAccess;
    posModules?: string[];
    pos_modules?: string[];
    backofficeModules?: string[];
    backoffice_modules?: string[];
    data?: AuthResponseData;
    [key: string]: unknown;
}

interface Tenant {
    id: number;
    name: string;
    [key: string]: unknown;
}

export const useAuthStore = defineStore('auth', () => {
    // ==================== STATE ====================

    const sessionState = ref<SessionState>('logged_out');
    const lockedByUser = ref<User | null>(null);
    const user = ref<User | null>(null);
    const token = ref<string | null>(null);
    const isLoggedIn = ref(false);
    const permissions = ref<string[]>([]);
    const limits = ref<PosLimits>({
        max_discount_percent: 0,
        max_refund_amount: 0,
        max_cancel_amount: 0,
    });
    const interfaceAccess = ref<InterfaceAccess>({
        can_access_pos: false,
        can_access_backoffice: false,
        can_access_kitchen: false,
        can_access_delivery: false,
    });
    const posModules = ref<string[]>([]);
    const backofficeModules = ref<string[]>([]);
    const tenant = ref<Tenant | null>(null);
    const restaurants = ref<Restaurant[]>([]);
    const currentRestaurantId = ref<number | null>(getRestaurantId() as any);

    // ==================== PRIVATE HELPERS ====================

    function applyUserData(data: AuthResponseData): void {
        user.value = data.user;
        token.value = data.token;
        isLoggedIn.value = true;
        permissions.value = data.permissions || [];
        limits.value = data.limits || {
            max_discount_percent: 0,
            max_refund_amount: 0,
            max_cancel_amount: 0,
        };
        interfaceAccess.value = data.interfaceAccess || data.interface_access || {
            can_access_pos: false,
            can_access_backoffice: false,
            can_access_kitchen: false,
            can_access_delivery: false,
        };
        posModules.value = data.posModules || data.pos_modules || [];
        backofficeModules.value = data.backofficeModules || data.backoffice_modules || [];

        authService.setSession({
            token: data.token,
            user: data.user,
            permissions: permissions.value,
            limits: limits.value,
            interfaceAccess: interfaceAccess.value,
            posModules: posModules.value,
            backofficeModules: backofficeModules.value,
        }, { app: 'pos' });

        const permissionsStore = usePermissionsStore();
        permissionsStore.init({
            permissions: permissions.value,
            limits: limits.value,
            interfaceAccess: interfaceAccess.value,
            posModules: posModules.value,
            backofficeModules: backofficeModules.value,
            role: data.user?.role || null,
        });

        sessionState.value = 'active';
    }

    function clearAuthState(): void {
        user.value = null;
        token.value = null;
        isLoggedIn.value = false;
        permissions.value = [];
        limits.value = {
            max_discount_percent: 0,
            max_refund_amount: 0,
            max_cancel_amount: 0,
        };
        interfaceAccess.value = {
            can_access_pos: false,
            can_access_backoffice: false,
            can_access_kitchen: false,
            can_access_delivery: false,
        };
        posModules.value = [];
        backofficeModules.value = [];
        lockedByUser.value = null;
        sessionState.value = 'logged_out';

        const permissionsStore = usePermissionsStore();
        permissionsStore.reset();
    }

    // ==================== COMPUTED ====================

    const hasPermission = (perm: string): boolean => {
        if (!user.value) return false;
        const role = user.value.role;
        if (role === 'super_admin' || role === 'owner') return true;
        return permissions.value.includes('*') || permissions.value.includes(perm);
    };

    const canCancelOrders = computed(() => hasPermission('orders.cancel'));

    const userInitials = computed(() => {
        if (!user.value?.name) return '?';
        const parts = user.value.name.split(' ');
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return user.value.name.substring(0, 2).toUpperCase();
    });

    const currentRestaurant = computed(() => {
        if (!restaurants.value.length) return null;
        const id = currentRestaurantId.value ? Number(currentRestaurantId.value) : null;
        return restaurants.value.find((r: any) => r.id === id) ||
               restaurants.value.find((r: any) => (r as Record<string, any>).is_current) ||
               restaurants.value[0];
    });

    const hasMultipleRestaurants = computed(() => restaurants.value.length > 1);

    const maxDiscountPercent = computed(() => {
        if (!user.value) return 0;
        const role = user.value.role;
        if (role === 'super_admin' || role === 'owner') return 100;
        return limits.value.max_discount_percent || 0;
    });

    // ==================== ACTIONS ====================

    async function loginWithPin(pin: string, userId: number | null = null): Promise<LoginResult> {
        try {
            const response = await api.auth.loginWithPin(pin, userId) as Record<string, any>;

            if (response.success) {
                applyUserData(response.data as AuthResponseData);
                return { success: true };
            }

            return { success: false, message: response.message as string };
        } catch (error: unknown) {
            log.error('Login error:', error);
            const data = (error as Record<string, any>)?.response as Record<string, any> | undefined;
            const responseData = data?.data as Record<string, any> | undefined;
            return {
                success: false,
                message: (responseData?.message as string) || 'Ошибка авторизации',
                reason: responseData?.reason as string,
                require_full_login: responseData?.require_full_login as boolean,
            };
        }
    }

    async function loginWithPassword(responseData: Record<string, any>): Promise<LoginResult> {
        try {
            applyUserData(responseData.data as AuthResponseData);
            return { success: true };
        } catch (error: unknown) {
            log.error('loginWithPassword error:', error);
            return { success: false, message: (error as Error).message };
        }
    }

    async function restoreSession(): Promise<boolean> {
        try {
            const session = authService.getSession();
            if (!session?.token) {
                return false;
            }

            const response = await api.auth.checkAuth(session.token) as Record<string, any>;

            if (response.success) {
                const responseData = response.data as Record<string, any> | undefined;
                applyUserData({
                    user: (responseData?.user || session.user) as User,
                    token: session.token,
                    permissions: (responseData?.permissions || session.permissions || []) as string[],
                    limits: (responseData?.limits || session.limits || {}) as PosLimits,
                    interface_access: (responseData?.interface_access || session.interfaceAccess || {}) as InterfaceAccess,
                    pos_modules: (responseData?.pos_modules || session.posModules || []) as string[],
                    backoffice_modules: (responseData?.backoffice_modules || session.backofficeModules || []) as string[],
                });
                return true;
            }

            clearAuthState();
            authService.clearAuth();
            return false;
        } catch (error: any) {
            log.error('Restore session error:', error);
            clearAuthState();
            authService.clearAuth();
            return false;
        }
    }

    async function logout(): Promise<void> {
        try {
            if (token.value) {
                await api.auth.logout(token.value);
            }
        } catch (e: any) {
            log.error('Logout error:', e);
        }

        authService.clearAuth();
        localStorage.removeItem('api_token');
        localStorage.removeItem('menulab_auth');

        clearAuthState();
    }

    function lockScreen(): void {
        if (sessionState.value !== 'active') return;
        lockedByUser.value = user.value ? { ...user.value } : null;
        sessionState.value = 'locked';
        log.debug('Screen locked by', lockedByUser.value?.name);
    }

    function unlockScreen(): void {
        if (sessionState.value !== 'locked') return;
        sessionState.value = 'active';
        lockedByUser.value = null;
        log.debug('Screen unlocked');
    }

    function switchUser(data: AuthResponseData): void {
        if (sessionState.value !== 'locked') return;
        applyUserData(data);
        lockedByUser.value = null;
        log.debug('User switched to', data.user?.name);
    }

    function handleUnauthorized(): void {
        log.debug('Unauthorized (401) — logging out');
        authService.clearAuth();
        localStorage.removeItem('api_token');
        localStorage.removeItem('menulab_auth');
        clearAuthState();
    }

    function canApplyDiscount(percent: number): boolean {
        if (!user.value) return false;
        const role = user.value.role;
        if (role === 'super_admin' || role === 'owner') return true;
        return hasPermission('orders.discount') && limits.value.max_discount_percent >= percent;
    }

    async function loadTenant(): Promise<void> {
        if (!token.value) return;
        try {
            const response = await api.get('/tenant') as Record<string, any>;
            if (response.success) {
                tenant.value = response.data as Tenant;
            }
        } catch (e: any) {
            log.error('Failed to load tenant:', e);
        }
    }

    async function loadRestaurants(): Promise<void> {
        if (!token.value) return;
        try {
            const response = await api.get('/tenant/restaurants') as Record<string, any>;
            if (response.success) {
                restaurants.value = (response.data || []) as Restaurant[];
                const current = restaurants.value.find((r: any) => (r as Record<string, any>).is_current);
                if (current) {
                    currentRestaurantId.value = current.id;
                    const permissionsStore = usePermissionsStore();
                    permissionsStore.setRestaurantId(current.id);
                }
            }
        } catch (e: any) {
            log.error('Failed to load restaurants:', e);
        }
    }

    async function switchRestaurant(restaurantId: number): Promise<{ success: boolean; message?: string }> {
        if (!token.value) return { success: false };
        try {
            const response = await api.post(`/tenant/restaurants/${restaurantId}/switch`) as Record<string, any>;
            if (response.success) {
                currentRestaurantId.value = restaurantId;
                if (user.value) {
                    (user.value as Record<string, any>).restaurant_id = restaurantId;
                }
                const permissionsStore = usePermissionsStore();
                permissionsStore.setRestaurantId(restaurantId);
                await loadRestaurants();
                return { success: true, message: response.message as string };
            }
            return { success: false, message: response.message as string };
        } catch (e: unknown) {
            log.error('Failed to switch restaurant:', e);
            return { success: false, message: (e as Error).message };
        }
    }

    // ==================== RETURN ====================

    return {
        sessionState,
        lockedByUser,
        user,
        token,
        isLoggedIn,
        permissions,
        limits,
        interfaceAccess,
        posModules,
        backofficeModules,
        tenant,
        restaurants,
        currentRestaurantId,
        canCancelOrders,
        userInitials,
        maxDiscountPercent,
        currentRestaurant,
        hasMultipleRestaurants,
        loginWithPin,
        loginWithPassword,
        restoreSession,
        logout,
        lockScreen,
        unlockScreen,
        switchUser,
        handleUnauthorized,
        hasPermission,
        canApplyDiscount,
        loadTenant,
        loadRestaurants,
        switchRestaurant,
    };
});
