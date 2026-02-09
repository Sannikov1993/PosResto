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
import api from '../api';
import authService from '../../shared/services/auth';
import { usePermissionsStore } from '@/shared/stores/permissions.js';
import { getRestaurantId } from '@/shared/constants/storage.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('POS:Auth');

export const useAuthStore = defineStore('auth', () => {
    // ==================== STATE ====================

    /**
     * Состояние сессии: 'logged_out' | 'active' | 'locked'
     *
     * logged_out — экран логина, данные очищены
     * active     — рабочий POS, данные загружены
     * locked     — lock screen overlay поверх POS, данные сохранены
     */
    const sessionState = ref('logged_out');

    /** Пользователь, заблокировавший экран (сохраняется при блокировке) */
    const lockedByUser = ref(null);

    /** Current user data */
    const user = ref(null);

    /** Authentication token */
    const token = ref(null);

    /** Whether user is logged in */
    const isLoggedIn = ref(false);

    /** User permissions */
    const permissions = ref([]);

    /** User limits (discount, refund, cancel) */
    const limits = ref({
        max_discount_percent: 0,
        max_refund_amount: 0,
        max_cancel_amount: 0,
    });

    /** Interface access flags */
    const interfaceAccess = ref({
        can_access_pos: false,
        can_access_backoffice: false,
        can_access_kitchen: false,
        can_access_delivery: false,
    });

    /** Available POS modules */
    const posModules = ref([]);

    /** Available Backoffice modules */
    const backofficeModules = ref([]);

    /** Current tenant */
    const tenant = ref(null);

    /** Available restaurants */
    const restaurants = ref([]);

    /** Current restaurant ID - uses centralized storage with legacy fallback */
    const currentRestaurantId = ref(getRestaurantId());

    // ==================== PRIVATE HELPERS ====================

    /**
     * Применить данные пользователя из API ответа
     * @private
     */
    function applyUserData(data) {
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

        // Сохраняем в localStorage
        authService.setSession({
            token: data.token,
            user: data.user,
            permissions: permissions.value,
            limits: limits.value,
            interfaceAccess: interfaceAccess.value,
            posModules: posModules.value,
            backofficeModules: backofficeModules.value,
        }, { app: 'pos' });

        // Initialize PermissionsStore
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

    /**
     * Очистить состояние авторизации
     * @private
     */
    function clearAuthState() {
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

        // Reset PermissionsStore
        const permissionsStore = usePermissionsStore();
        permissionsStore.reset();
    }

    // ==================== COMPUTED ====================

    /**
     * Check if user has a specific permission
     */
    const hasPermission = (perm) => {
        if (!user.value) return false;
        const role = user.value.role;
        if (role === 'super_admin' || role === 'owner') return true;
        return permissions.value.includes('*') || permissions.value.includes(perm);
    };

    /**
     * Whether user can cancel orders
     */
    const canCancelOrders = computed(() => hasPermission('orders.cancel'));

    /**
     * User initials for avatar
     */
    const userInitials = computed(() => {
        if (!user.value?.name) return '?';
        const parts = user.value.name.split(' ');
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return user.value.name.substring(0, 2).toUpperCase();
    });

    /**
     * Current restaurant object
     */
    const currentRestaurant = computed(() => {
        if (!restaurants.value.length) return null;
        const id = currentRestaurantId.value ? parseInt(currentRestaurantId.value) : null;
        return restaurants.value.find(r => r.id === id) ||
               restaurants.value.find(r => r.is_current) ||
               restaurants.value[0];
    });

    /**
     * Whether user has multiple restaurants
     */
    const hasMultipleRestaurants = computed(() => restaurants.value.length > 1);

    /**
     * Maximum discount percentage for current user
     */
    const maxDiscountPercent = computed(() => {
        if (!user.value) return 0;
        const role = user.value.role;
        if (role === 'super_admin' || role === 'owner') return 100;
        return limits.value.max_discount_percent || 0;
    });

    // ==================== ACTIONS ====================

    /**
     * Login with PIN code
     * @param {string} pin - PIN code
     * @param {number|null} userId - Optional user ID for specific user login
     * @returns {Promise<Object>} Login result
     */
    async function loginWithPin(pin, userId = null) {
        try {
            const response = await api.auth.loginWithPin(pin, userId);

            if (response.success) {
                applyUserData(response.data);
                return { success: true };
            }

            return { success: false, message: response.message };
        } catch (error) {
            log.error('Login error:', error);
            const data = error.response?.data;
            return {
                success: false,
                message: data?.message || 'Ошибка авторизации',
                reason: data?.reason,
                require_full_login: data?.require_full_login,
            };
        }
    }

    /**
     * Login with email/password
     * @param {Object} responseData - Response data from auth.login()
     * @returns {Promise<Object>} Login result
     */
    async function loginWithPassword(responseData) {
        try {
            applyUserData(responseData.data);
            return { success: true };
        } catch (error) {
            log.error('loginWithPassword error:', error);
            return { success: false, message: error.message };
        }
    }

    /**
     * Restore session from localStorage
     * @returns {Promise<boolean>} Whether session was restored
     */
    async function restoreSession() {
        try {
            const session = authService.getSession();
            if (!session?.token) {
                return false;
            }

            // Валидируем токен на сервере
            const response = await api.auth.checkAuth(session.token);

            if (response.success) {
                applyUserData({
                    user: response.data?.user || session.user,
                    token: session.token,
                    permissions: response.data?.permissions || session.permissions || [],
                    limits: response.data?.limits || session.limits || {},
                    interface_access: response.data?.interface_access || session.interfaceAccess || {},
                    pos_modules: response.data?.pos_modules || session.posModules || [],
                    backoffice_modules: response.data?.backoffice_modules || session.backofficeModules || [],
                });
                return true;
            }

            // Сервер отклонил токен
            clearAuthState();
            authService.clearAuth();
            return false;
        } catch (error) {
            log.error('Restore session error:', error);
            clearAuthState();
            authService.clearAuth();
            return false;
        }
    }

    /**
     * Logout - полный выход
     */
    async function logout() {
        try {
            if (token.value) {
                await api.auth.logout(token.value);
            }
        } catch (e) {
            log.error('Logout error:', e);
        }

        // Очищаем localStorage
        authService.clearAuth();
        localStorage.removeItem('api_token');
        localStorage.removeItem('menulab_auth');

        clearAuthState();
    }

    /**
     * ACTIVE → LOCKED: заблокировать экран
     */
    function lockScreen() {
        if (sessionState.value !== 'active') return;
        lockedByUser.value = user.value ? { ...user.value } : null;
        sessionState.value = 'locked';
        log.debug('Screen locked by', lockedByUser.value?.name);
    }

    /**
     * LOCKED → ACTIVE: разблокировка тем же пользователем
     */
    function unlockScreen() {
        if (sessionState.value !== 'locked') return;
        sessionState.value = 'active';
        lockedByUser.value = null;
        log.debug('Screen unlocked');
    }

    /**
     * LOCKED → ACTIVE: переключение на другого пользователя
     * @param {Object} data - Данные нового пользователя из API
     */
    function switchUser(data) {
        if (sessionState.value !== 'locked') return;
        applyUserData(data);
        lockedByUser.value = null;
        log.debug('User switched to', data.user?.name);
    }

    /**
     * Обработка 401 от сервера → полный логаут
     */
    function handleUnauthorized() {
        log.debug('Unauthorized (401) — logging out');
        authService.clearAuth();
        localStorage.removeItem('api_token');
        localStorage.removeItem('menulab_auth');
        clearAuthState();
    }

    /**
     * Check if user can apply a discount of given percentage
     * @param {number} percent - Discount percentage
     * @returns {boolean}
     */
    function canApplyDiscount(percent) {
        if (!user.value) return false;
        const role = user.value.role;
        if (role === 'super_admin' || role === 'owner') return true;
        return hasPermission('orders.discount') && limits.value.max_discount_percent >= percent;
    }

    /**
     * Load tenant information
     */
    async function loadTenant() {
        if (!token.value) return;
        try {
            const response = await api.get('/tenant');
            if (response.success) {
                tenant.value = response.data;
            }
        } catch (e) {
            log.error('Failed to load tenant:', e);
        }
    }

    /**
     * Load restaurants list
     */
    async function loadRestaurants() {
        if (!token.value) return;
        try {
            const response = await api.get('/tenant/restaurants');
            if (response.success) {
                restaurants.value = response.data || [];
                const current = restaurants.value.find(r => r.is_current);
                if (current) {
                    currentRestaurantId.value = current.id;
                    const permissionsStore = usePermissionsStore();
                    permissionsStore.setRestaurantId(current.id);
                }
            }
        } catch (e) {
            log.error('Failed to load restaurants:', e);
        }
    }

    /**
     * Switch current restaurant
     * @param {number} restaurantId - Restaurant ID to switch to
     * @returns {Promise<Object>} Switch result
     */
    async function switchRestaurant(restaurantId) {
        if (!token.value) return { success: false };
        try {
            const response = await api.post(`/tenant/restaurants/${restaurantId}/switch`);
            if (response.success) {
                currentRestaurantId.value = restaurantId;
                // Синхронизируем user.restaurant_id (бэкенд уже обновил в БД)
                if (user.value) {
                    user.value.restaurant_id = restaurantId;
                }
                const permissionsStore = usePermissionsStore();
                permissionsStore.setRestaurantId(restaurantId);
                await loadRestaurants();
                return { success: true, message: response.message };
            }
            return { success: false, message: response.message };
        } catch (e) {
            log.error('Failed to switch restaurant:', e);
            return { success: false, message: e.message };
        }
    }

    // ==================== RETURN ====================

    return {
        // State
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

        // Computed
        canCancelOrders,
        userInitials,
        maxDiscountPercent,
        currentRestaurant,
        hasMultipleRestaurants,

        // Actions
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
