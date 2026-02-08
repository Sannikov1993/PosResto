import { createHttpClient } from '../shared/services/httpClient.js';
import authService from '../shared/services/auth.js';
import { createLogger } from '../shared/services/logger.js';
import { getRestaurantId } from '@/shared/constants/storage.js';

const { http } = createHttpClient({ module: 'DeviceAuth' });
const log = createLogger('DeviceAuth');

/**
 * Утилита для работы с авторизацией устройств
 */
export default {
    /**
     * Вход с запоминанием устройства
     */
    async login(login, password, rememberDevice = false) {
        const appType = this.getAppType();
        const deviceFingerprint = this.generateFingerprint();

        const response = await http.post('/auth/login-device', {
            login,
            password,
            device_fingerprint: deviceFingerprint,
            device_name: this.getDeviceName(),
            app_type: appType,
            remember_device: rememberDevice,
        });

        const data = response?.data || response;

        if (data?.token) {
            authService.setSession({ token: data.token, user: data.user }, { app: appType });

            if (data.device_token) {
                localStorage.setItem('device_token', data.device_token);
            }
        }

        return response;
    },

    /**
     * Автовход по device_token
     */
    async deviceLogin() {
        const deviceToken = localStorage.getItem('device_token');

        if (!deviceToken) {
            return { success: false, reason: 'no_device_token' };
        }

        try {
            const response = await http.post('/auth/device-login', {
                device_token: deviceToken,
            });

            const data = response?.data || response;
            if (data?.token) {
                authService.setSession({ token: data.token, user: data.user }, { app: this.getAppType() });
            }

            return response;
        } catch (error) {
            if (error.response?.data?.reason === 'invalid_device_token') {
                localStorage.removeItem('device_token');
            }
            throw error;
        }
    },

    /**
     * Вход по PIN (только для авторизованных устройств)
     */
    async loginByPin(pin, restaurantId = null) {
        const deviceToken = localStorage.getItem('device_token');

        const response = await http.post('/auth/login-pin', {
            pin,
            restaurant_id: restaurantId,
            device_token: deviceToken,
        });

        const data = response?.data || response;
        if (data?.token) {
            authService.setSession({ token: data.token, user: data.user }, { app: this.getAppType() });
        }

        return response;
    },

    /**
     * Список юзеров на терминале
     */
    async getDeviceUsers(appType) {
        const deviceFingerprint = this.generateFingerprint();
        const restaurantId = getRestaurantId();
        const deviceToken = localStorage.getItem('device_token');

        const response = await http.get('/auth/device-users', {
            params: {
                device_fingerprint: deviceFingerprint,
                device_token: deviceToken,
                app_type: appType,
                restaurant_id: restaurantId,
            },
        });

        return response;
    },

    /**
     * Clock In (начало смены)
     */
    async clockIn() {
        return http.post('/payroll/my-clock-in');
    },

    /**
     * Clock Out (конец смены)
     */
    async clockOut() {
        return http.post('/payroll/my-clock-out');
    },

    /**
     * Выход
     */
    async logout(forgetDevice = false) {
        const deviceToken = localStorage.getItem('device_token');

        try {
            await http.post('/auth/logout-device', {
                device_token: forgetDevice ? deviceToken : null,
            });
        } catch (error) {
            log.error('Logout error:', error.message);
        }

        authService.clearAuth();

        if (forgetDevice) {
            localStorage.removeItem('device_token');
        }
    },

    /**
     * Проверка авторизации
     */
    async check() {
        if (!authService.getToken()) {
            return { success: false };
        }

        try {
            return await http.get('/auth/check');
        } catch (error) {
            return { success: false };
        }
    },

    /**
     * Генерация fingerprint устройства
     */
    generateFingerprint() {
        const ua = navigator.userAgent;
        const screen = `${window.screen.width}x${window.screen.height}`;
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const language = navigator.language;

        const raw = `${ua}:${screen}:${timezone}:${language}`;
        return btoa(raw).substring(0, 64);
    },

    /**
     * Получить название устройства
     */
    getDeviceName() {
        const ua = navigator.userAgent;

        let browser = 'Unknown';
        if (ua.indexOf('Chrome') > -1) browser = 'Chrome';
        else if (ua.indexOf('Safari') > -1) browser = 'Safari';
        else if (ua.indexOf('Firefox') > -1) browser = 'Firefox';
        else if (ua.indexOf('Edge') > -1) browser = 'Edge';

        let os = 'Unknown';
        if (ua.indexOf('Windows') > -1) os = 'Windows';
        else if (ua.indexOf('Mac') > -1) os = 'macOS';
        else if (ua.indexOf('Linux') > -1) os = 'Linux';
        else if (ua.indexOf('Android') > -1) os = 'Android';
        else if (ua.indexOf('iOS') > -1 || ua.indexOf('iPhone') > -1) os = 'iOS';

        return `${browser} на ${os}`;
    },

    /**
     * Определение типа приложения
     */
    getAppType() {
        const path = window.location.pathname;
        if (path.includes('/pos')) return 'pos';
        if (path.includes('/waiter')) return 'waiter';
        if (path.includes('/courier')) return 'courier';
        if (path.includes('/kitchen')) return 'kitchen';
        if (path.includes('/backoffice')) return 'backoffice';
        return 'unknown';
    },
};
