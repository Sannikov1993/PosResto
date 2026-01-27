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

    // Constants
    const SESSION_KEY = 'posresto_session';
    const SESSION_TTL = 8 * 60 * 60 * 1000; // 8 hours
    const ACTIVITY_EXTEND = 30 * 60 * 1000; // 30 minutes

    // Computed
    const canCancelOrders = computed(() => {
        const managerRoles = ['super_admin', 'owner', 'admin', 'manager'];
        return user.value && managerRoles.includes(user.value.role);
    });

    const userInitials = computed(() => {
        if (!user.value?.name) return '?';
        const parts = user.value.name.split(' ');
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return user.value.name.substring(0, 2).toUpperCase();
    });

    // Session management
    const saveSession = (userData, authToken) => {
        const session = {
            user: userData,
            token: authToken,
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
    const loginWithPin = async (pin) => {
        try {
            const response = await api.auth.loginWithPin(pin);
            if (response.success) {
                user.value = response.data.user;
                token.value = response.data.token;
                isLoggedIn.value = true;
                saveSession(response.data.user, response.data.token);
                return { success: true };
            }
            return { success: false, message: response.message };
        } catch (error) {
            console.error('[Auth] Login error:', error);
            return { success: false, message: 'Ошибка авторизации' };
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
        clearSession();
    };

    return {
        // State
        user,
        token,
        isLoggedIn,
        // Computed
        canCancelOrders,
        userInitials,
        // Actions
        loginWithPin,
        restoreSession,
        logout,
        extendSession
    };
});
