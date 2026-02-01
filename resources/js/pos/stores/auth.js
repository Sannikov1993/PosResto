/**
 * Auth Store - Управление авторизацией
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../api';

export const useAuthStore = defineStore('auth', () => {
    // State
    const user = ref(null);
    const token = ref(null);
    const isLoggedIn = ref(false);
    const permissions = ref([]);
    const limits = ref({ max_discount_percent: 0, max_refund_amount: 0, max_cancel_amount: 0 });
    const interfaceAccess = ref({ can_access_pos: false, can_access_backoffice: false, can_access_kitchen: false, can_access_delivery: false });

    // Tenant & Restaurants
    const tenant = ref(null);
    const restaurants = ref([]);
    const currentRestaurantId = ref(localStorage.getItem('pos_restaurant_id') || null);

    // Constants
    const SESSION_KEY = 'menulab_session';
    const SESSION_TTL = 8 * 60 * 60 * 1000; // 8 hours
    const ACTIVITY_EXTEND = 30 * 60 * 1000; // 30 minutes

    // Проверка прав
    const hasPermission = (perm) => {
        if (!user.value) return false;
        const role = user.value.role;
        if (role === 'super_admin' || role === 'owner') return true;
        return permissions.value.includes('*') || permissions.value.includes(perm);
    };

    // Computed
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
        const id = currentRestaurantId.value ? parseInt(currentRestaurantId.value) : null;
        return restaurants.value.find(r => r.id === id) || restaurants.value.find(r => r.is_current) || restaurants.value[0];
    });

    const hasMultipleRestaurants = computed(() => restaurants.value.length > 1);

    // Session management
    const saveSession = (userData, authToken, perms = [], lim = {}, access = {}) => {
        const session = {
            user: userData,
            token: authToken,
            permissions: perms,
            limits: lim,
            interfaceAccess: access,
            loginAt: Date.now(),
            lastActivity: Date.now(),
            expiresAt: Date.now() + SESSION_TTL
        };
        localStorage.setItem(SESSION_KEY, JSON.stringify(session));
    };

    const getStoredSession = () => {
        try {
            const data = localStorage.getItem(SESSION_KEY);
            if (!data) return null;
            return JSON.parse(data);
        } catch {
            return null;
        }
    };

    const clearSession = () => {
        localStorage.removeItem(SESSION_KEY);
    };

    const extendSession = () => {
        const session = getStoredSession();
        if (session) {
            session.lastActivity = Date.now();
            session.expiresAt = Date.now() + ACTIVITY_EXTEND;
            localStorage.setItem(SESSION_KEY, JSON.stringify(session));
        }
    };

    const isSessionExpired = (session) => {
        if (!session) return true;
        return Date.now() > session.expiresAt;
    };

    // Actions
    const loginWithPin = async (pin, userId = null) => {
        try {
            const response = await api.auth.loginWithPin(pin, userId);
            if (response.success) {
                user.value = response.data.user;
                token.value = response.data.token;
                isLoggedIn.value = true;
                permissions.value = response.data.permissions || [];
                limits.value = response.data.limits || { max_discount_percent: 0, max_refund_amount: 0, max_cancel_amount: 0 };
                interfaceAccess.value = response.data.interface_access || {};
                saveSession(response.data.user, response.data.token, permissions.value, limits.value, interfaceAccess.value);
                return { success: true };
            }
            return { success: false, message: response.message };
        } catch (error) {
            console.error('[Auth] Login error:', error);
            const data = error.response?.data;
            return {
                success: false,
                message: data?.message || 'Ошибка авторизации',
                reason: data?.reason,
                require_full_login: data?.require_full_login,
            };
        }
    };

    const restoreSession = async () => {
        const session = getStoredSession();
        if (!session || !session.token || isSessionExpired(session)) {
            clearSession();
            return false;
        }

        try {
            const response = await api.auth.checkAuth(session.token);
            if (response.success) {
                user.value = response.data.user;
                token.value = session.token;
                isLoggedIn.value = true;
                // Обновляем права из ответа сервера (актуальные)
                permissions.value = response.data.permissions || session.permissions || [];
                limits.value = response.data.limits || session.limits || {};
                interfaceAccess.value = response.data.interface_access || session.interfaceAccess || {};
                extendSession();
                return true;
            }
        } catch (error) {
            // Token validation failed - silent fail
        }

        clearSession();
        return false;
    };

    const logout = async () => {
        if (token.value) {
            try {
                await api.auth.logout(token.value);
            } catch (e) {
                // Ignore logout errors
            }
        }
        user.value = null;
        token.value = null;
        isLoggedIn.value = false;
        permissions.value = [];
        limits.value = { max_discount_percent: 0, max_refund_amount: 0, max_cancel_amount: 0 };
        interfaceAccess.value = {};
        clearSession();
    };

    /**
     * Проверить, может ли пользователь применить скидку данного размера
     */
    const canApplyDiscount = (percent) => {
        if (!user.value) return false;
        const role = user.value.role;
        if (role === 'super_admin' || role === 'owner') return true;
        return hasPermission('orders.discount') && limits.value.max_discount_percent >= percent;
    };

    /**
     * Загрузить информацию о тенанте
     */
    const loadTenant = async () => {
        if (!token.value) return;
        try {
            const response = await api.get('/tenant');
            if (response.success) {
                tenant.value = response.data;
            }
        } catch (e) {
            console.error('[Auth] Failed to load tenant:', e);
        }
    };

    /**
     * Загрузить список ресторанов
     */
    const loadRestaurants = async () => {
        if (!token.value) return;
        try {
            const response = await api.get('/tenant/restaurants');
            if (response.success) {
                restaurants.value = response.data || [];
                const current = restaurants.value.find(r => r.is_current);
                if (current) {
                    currentRestaurantId.value = current.id;
                    localStorage.setItem('pos_restaurant_id', current.id);
                }
            }
        } catch (e) {
            console.error('[Auth] Failed to load restaurants:', e);
        }
    };

    /**
     * Переключить ресторан
     */
    const switchRestaurant = async (restaurantId) => {
        if (!token.value) return { success: false };
        try {
            const response = await api.post(`/tenant/restaurants/${restaurantId}/switch`);
            if (response.success) {
                currentRestaurantId.value = restaurantId;
                localStorage.setItem('pos_restaurant_id', restaurantId);
                await loadRestaurants();
                return { success: true, message: response.message };
            }
            return { success: false, message: response.message };
        } catch (e) {
            console.error('[Auth] Failed to switch restaurant:', e);
            return { success: false, message: e.message };
        }
    };

    /**
     * Максимальная скидка для текущего пользователя
     */
    const maxDiscountPercent = computed(() => {
        if (!user.value) return 0;
        const role = user.value.role;
        if (role === 'super_admin' || role === 'owner') return 100;
        return limits.value.max_discount_percent || 0;
    });

    return {
        // State
        user,
        token,
        isLoggedIn,
        permissions,
        limits,
        interfaceAccess,
        tenant,
        restaurants,
        currentRestaurantId,
        // Computed
        canCancelOrders,
        userInitials,
        maxDiscountPercent,
        currentRestaurant,
        hasMultipleRestaurants,
        // Actions
        loginWithPin,
        restoreSession,
        logout,
        extendSession,
        hasPermission,
        canApplyDiscount,
        loadTenant,
        loadRestaurants,
        switchRestaurant,
    };
});
