/**
 * PosLab POS - Authentication Module
 * Авторизация, сессии и управление доступом
 */

const PosAuth = {
    // Ключи хранения
    SESSION_KEY: 'poslab_session',
    TAB_KEY: 'poslab_active_tab',
    SESSION_TTL: 8 * 60 * 60 * 1000, // 8 часов
    ACTIVITY_EXTEND: 30 * 60 * 1000, // 30 минут продления

    // Роли с правами отмены заказов
    managerRoles: ['super_admin', 'owner', 'admin', 'manager'],

    // ==================== СЕССИЯ ====================

    /**
     * Сохранить сессию в localStorage
     */
    saveSession(userData, token) {
        const session = {
            user: userData,
            token: token,
            loginAt: Date.now(),
            lastActivity: Date.now(),
            expiresAt: Date.now() + this.SESSION_TTL
        };
        localStorage.setItem(this.SESSION_KEY, JSON.stringify(session));
    },

    /**
     * Получить сохранённую сессию
     */
    getStoredSession() {
        try {
            const data = localStorage.getItem(this.SESSION_KEY);
            if (!data) return null;
            return JSON.parse(data);
        } catch {
            return null;
        }
    },

    /**
     * Очистить сессию
     */
    clearSession() {
        localStorage.removeItem(this.SESSION_KEY);
    },

    /**
     * Продлить сессию при активности
     */
    extendSession() {
        const session = this.getStoredSession();
        if (session) {
            session.lastActivity = Date.now();
            session.expiresAt = Date.now() + this.ACTIVITY_EXTEND;
            localStorage.setItem(this.SESSION_KEY, JSON.stringify(session));
        }
    },

    /**
     * Проверить не истекла ли сессия
     */
    isSessionExpired(session) {
        if (!session) return true;
        return Date.now() > session.expiresAt;
    },

    /**
     * Проверить наличие валидной сессии (синхронно, для инициализации)
     */
    hasValidStoredSession() {
        const session = this.getStoredSession();
        return session && !this.isSessionExpired(session);
    },

    /**
     * Получить данные из валидной сессии
     */
    getValidSessionData() {
        const session = this.getStoredSession();
        if (session && !this.isSessionExpired(session)) {
            return {
                user: session.user,
                token: session.token
            };
        }
        return null;
    },

    // ==================== ВКЛАДКИ ====================

    /**
     * Сохранить активную вкладку
     */
    saveActiveTab(tabId) {
        localStorage.setItem(this.TAB_KEY, tabId);
    },

    /**
     * Получить сохранённую вкладку
     */
    getSavedTab() {
        return localStorage.getItem(this.TAB_KEY);
    },

    /**
     * Получить валидную вкладку из localStorage или дефолт
     */
    getValidTab(validTabs = ['cash', 'orders', 'delivery', 'catalog', 'kitchen', 'stoplist', 'customers', 'writeoffs', 'settings']) {
        const savedTab = this.getSavedTab();
        return validTabs.includes(savedTab) ? savedTab : 'cash';
    },

    // ==================== АВТОРИЗАЦИЯ ====================

    /**
     * Вход по PIN-коду
     */
    async loginWithPin(pin) {
        const response = await PosAPI.loginWithPin(pin);
        if (response.success) {
            this.saveSession(response.data.user, response.data.token);
            console.log('[PosAuth] Сессия сохранена для:', response.data.user.name);
        }
        return response;
    },

    /**
     * Восстановление сессии (проверка на сервере)
     */
    async restoreSession() {
        const session = this.getStoredSession();
        if (!session || !session.token) {
            this.clearSession();
            return { success: false, reason: 'no_session' };
        }

        if (this.isSessionExpired(session)) {
            console.log('[PosAuth] Сессия истекла');
            this.clearSession();
            return { success: false, reason: 'expired' };
        }

        try {
            const response = await PosAPI.checkAuth(session.token);
            if (response.success) {
                this.extendSession();
                console.log('[PosAuth] Сессия подтверждена для:', response.data.user.name);
                return {
                    success: true,
                    user: response.data.user,
                    token: session.token
                };
            }
        } catch (err) {
            console.log('[PosAuth] Токен недействителен');
        }

        this.clearSession();
        return { success: false, reason: 'invalid_token' };
    },

    /**
     * Выход
     */
    async logout(token) {
        if (token) {
            try {
                await PosAPI.logout(token);
            } catch (e) {
                // Игнорируем ошибки при выходе
            }
        }
        this.clearSession();
    },

    // ==================== ПРАВА ДОСТУПА ====================

    /**
     * Проверить право на отмену заказов
     */
    canCancelOrders(user) {
        return user && this.managerRoles.includes(user.role);
    },

    /**
     * Проверить право на определённое действие
     */
    hasPermission(user, permission) {
        if (!user) return false;

        // Супер админ и владелец имеют все права
        if (['super_admin', 'owner'].includes(user.role)) return true;

        // Проверяем конкретные права
        const rolePermissions = {
            admin: ['cancel_orders', 'apply_discount', 'refund', 'manage_staff', 'view_reports'],
            manager: ['cancel_orders', 'apply_discount', 'refund', 'view_reports'],
            cashier: ['apply_discount'],
            waiter: []
        };

        const permissions = rolePermissions[user.role] || [];
        return permissions.includes(permission);
    },

    /**
     * Получить лейбл роли
     */
    getRoleLabel(role) {
        const labels = {
            super_admin: 'Супер администратор',
            owner: 'Владелец',
            admin: 'Администратор',
            manager: 'Менеджер',
            cashier: 'Кассир',
            waiter: 'Официант',
            cook: 'Повар',
            courier: 'Курьер'
        };
        return labels[role] || role;
    }
};

// Export for global usage
window.PosAuth = PosAuth;
