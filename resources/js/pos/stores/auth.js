/**
 * Auth Store - Enterprise-grade authentication management
 *
 * Integrates with SessionManager for robust session handling:
 * - Automatic session persistence and restoration
 * - Cross-tab synchronization
 * - Network resilience with retry logic
 * - Expiration warnings and handling
 * - Activity-based session extension
 *
 * @module stores/auth
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../api';
import {
    getSessionManager,
    SESSION_EVENTS,
    SESSION_STATES,
} from '../services/session';
import { usePermissionsStore } from '@/shared/stores/permissions.js';
import { getRestaurantId } from '@/shared/constants/storage.js';

export const useAuthStore = defineStore('auth', () => {
    // ==================== STATE ====================

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

    /** Session manager instance */
    let sessionManager = null;

    /** Session expiration warning state */
    const expirationWarning = ref(null);

    // ==================== SESSION MANAGER ====================

    /**
     * Initialize session manager with callbacks
     * @private
     */
    function initializeSessionManager() {
        if (sessionManager) {
            return sessionManager;
        }

        sessionManager = getSessionManager({
            debug: import.meta.env.DEV,
            onStateChange: (newState, oldState) => {
                console.log(`[Auth] Session state: ${oldState} -> ${newState}`);

                // Handle state transitions
                if (newState === SESSION_STATES.NONE ||
                    newState === SESSION_STATES.EXPIRED ||
                    newState === SESSION_STATES.INVALID) {
                    clearAuthState();
                }
            },
            onSessionExpired: () => {
                console.log('[Auth] Session expired');
                clearAuthState();
                expirationWarning.value = null;
            },
            onSessionWarning: ({ timeUntilExpiry, critical }) => {
                const minutes = Math.ceil(timeUntilExpiry / 60000);
                expirationWarning.value = {
                    minutes,
                    critical,
                    message: critical
                        ? `Сессия истекает через ${minutes} мин. Сохраните работу!`
                        : `Сессия истекает через ${minutes} мин.`,
                };
            },
        });

        // Subscribe to session events
        sessionManager.on(SESSION_EVENTS.TAB_SYNCED, (data) => {
            // Sync state from another tab
            syncFromSession(sessionManager.getSession());
        });

        sessionManager.on(SESSION_EVENTS.EXTENDED, () => {
            // Clear expiration warning
            expirationWarning.value = null;
        });

        return sessionManager;
    }

    /**
     * Sync auth state from session data
     * @private
     */
    function syncFromSession(session) {
        if (!session) {
            clearAuthState();
            return;
        }

        user.value = session.user;
        token.value = session.token;
        isLoggedIn.value = true;
        permissions.value = session.permissions || [];
        limits.value = session.limits || {
            max_discount_percent: 0,
            max_refund_amount: 0,
            max_cancel_amount: 0,
        };
        interfaceAccess.value = session.interfaceAccess || {
            can_access_pos: false,
            can_access_backoffice: false,
            can_access_kitchen: false,
            can_access_delivery: false,
        };
        posModules.value = session.posModules || [];
        backofficeModules.value = session.backofficeModules || [];

        // Initialize PermissionsStore with all access levels
        const permissionsStore = usePermissionsStore();
        permissionsStore.init({
            permissions: permissions.value,
            limits: limits.value,
            interfaceAccess: interfaceAccess.value,
            posModules: posModules.value,
            backofficeModules: backofficeModules.value,
            role: session.user?.role || null,
        });
    }

    /**
     * Clear auth state
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
        expirationWarning.value = null;

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
                const manager = initializeSessionManager();

                // Create session with all access levels
                const sessionCreated = manager.createSession({
                    user: response.data.user,
                    token: response.data.token,
                    permissions: response.data.permissions || [],
                    limits: response.data.limits || {},
                    interface_access: response.data.interface_access || {},
                    pos_modules: response.data.pos_modules || [],
                    backoffice_modules: response.data.backoffice_modules || [],
                });

                if (sessionCreated) {
                    syncFromSession(manager.getSession());
                    return { success: true };
                }

                return { success: false, message: 'Failed to create session' };
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
    }

    /**
     * Restore session from storage
     * @returns {Promise<boolean>} Whether session was restored
     */
    async function restoreSession() {
        const manager = initializeSessionManager();

        try {
            const session = await manager.restoreSession();

            if (session) {
                syncFromSession(session);
                return true;
            }

            return false;
        } catch (error) {
            console.error('[Auth] Restore session error:', error);
            clearAuthState();
            return false;
        }
    }

    /**
     * Logout
     */
    async function logout() {
        const manager = initializeSessionManager();

        try {
            await manager.logout({ notifyServer: true, reason: 'user_logout' });
        } catch (e) {
            // Ignore logout errors
            console.error('[Auth] Logout error:', e);
        }

        // Очищаем стухшие токены из localStorage (НО сохраняем device_token для PIN-авторизации)
        localStorage.removeItem('api_token');
        localStorage.removeItem('menulab_auth');

        clearAuthState();
    }

    /**
     * Extend session manually (e.g., when user clicks "Stay logged in")
     */
    function extendSession() {
        if (sessionManager) {
            sessionManager.extend();
            expirationWarning.value = null;
        }
    }

    /**
     * Dismiss expiration warning
     */
    function dismissExpirationWarning() {
        expirationWarning.value = null;
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
            console.error('[Auth] Failed to load tenant:', e);
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
                    // Use centralized store (syncs to all apps automatically)
                    const permissionsStore = usePermissionsStore();
                    permissionsStore.setRestaurantId(current.id);
                }
            }
        } catch (e) {
            console.error('[Auth] Failed to load restaurants:', e);
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
                // Use centralized store (syncs to all apps automatically)
                const permissionsStore = usePermissionsStore();
                permissionsStore.setRestaurantId(restaurantId);
                await loadRestaurants();
                return { success: true, message: response.message };
            }
            return { success: false, message: response.message };
        } catch (e) {
            console.error('[Auth] Failed to switch restaurant:', e);
            return { success: false, message: e.message };
        }
    }

    /**
     * Get session status for debugging
     * @returns {Object} Session status
     */
    function getSessionStatus() {
        if (!sessionManager) {
            return { initialized: false };
        }
        return {
            initialized: true,
            ...sessionManager.getStatus(),
        };
    }

    /**
     * Login with email/password (creates session through SessionManager)
     * @param {Object} responseData - Response data from auth.login()
     * @returns {Promise<Object>} Login result
     */
    async function loginWithPassword(responseData) {
        try {
            const manager = initializeSessionManager();

            // Создаём сессию через SessionManager (как и loginWithPin)
            const sessionCreated = manager.createSession({
                user: responseData.data.user,
                token: responseData.data.token,
                permissions: responseData.data.permissions || [],
                limits: responseData.data.limits || {},
                interface_access: responseData.data.interface_access || {},
                pos_modules: responseData.data.pos_modules || [],
                backoffice_modules: responseData.data.backoffice_modules || [],
            });

            if (sessionCreated) {
                syncFromSession(manager.getSession());
                return { success: true };
            }

            return { success: false, message: 'Failed to create session' };
        } catch (error) {
            console.error('[Auth] loginWithPassword error:', error);
            return { success: false, message: error.message };
        }
    }

    // ==================== RETURN ====================

    return {
        // State
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
        expirationWarning,

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
        extendSession,
        dismissExpirationWarning,
        hasPermission,
        canApplyDiscount,
        loadTenant,
        loadRestaurants,
        switchRestaurant,
        getSessionStatus,
    };
});
