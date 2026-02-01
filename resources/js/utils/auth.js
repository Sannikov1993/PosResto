import axios from 'axios'

/**
 * Утилита для работы с авторизацией
 */
export default {
    /**
     * Вход с запоминанием устройства
     */
    async login(login, password, rememberDevice = false) {
        const appType = this.getAppType()
        const deviceFingerprint = this.generateFingerprint()

        const response = await axios.post('/api/auth/login-device', {
            login,
            password,
            device_fingerprint: deviceFingerprint,
            device_name: this.getDeviceName(),
            app_type: appType,
            remember_device: rememberDevice,
        })

        if (response.data.success) {
            // Сохраняем api_token
            localStorage.setItem('api_token', response.data.data.token)

            // Сохраняем device_token если запомнили
            if (response.data.data.device_token) {
                localStorage.setItem('device_token', response.data.data.device_token)
            }
        }

        return response.data
    },

    /**
     * Автовход по device_token
     */
    async deviceLogin() {
        const deviceToken = localStorage.getItem('device_token')

        if (!deviceToken) {
            return { success: false, reason: 'no_device_token' }
        }

        try {
            const response = await axios.post('/api/auth/device-login', {
                device_token: deviceToken,
            })

            if (response.data.success) {
                localStorage.setItem('api_token', response.data.data.token)
            }

            return response.data
        } catch (error) {
            // Если токен невалиден - удаляем
            if (error.response?.data?.reason === 'invalid_device_token') {
                localStorage.removeItem('device_token')
            }

            throw error
        }
    },

    /**
     * Вход по PIN (только для авторизованных устройств)
     */
    async loginByPin(pin, restaurantId = null) {
        const deviceToken = localStorage.getItem('device_token')

        const response = await axios.post('/api/auth/login-pin', {
            pin,
            restaurant_id: restaurantId,
            device_token: deviceToken, // ✅ Передаем device_token для проверки
        })

        if (response.data.success) {
            localStorage.setItem('api_token', response.data.data.token)
        }

        return response.data
    },

    /**
     * Список юзеров на терминале
     */
    async getDeviceUsers(appType) {
        const deviceFingerprint = this.generateFingerprint()

        const response = await axios.get('/api/auth/device-users', {
            params: {
                device_fingerprint: deviceFingerprint,
                app_type: appType,
            },
        })

        return response.data
    },

    /**
     * Clock In (начало смены)
     */
    async clockIn() {
        const response = await axios.post(
            '/api/payroll/my-clock-in',
            {},
            {
                headers: {
                    Authorization: `Bearer ${localStorage.getItem('api_token')}`,
                },
            }
        )
        return response.data
    },

    /**
     * Clock Out (конец смены)
     */
    async clockOut() {
        const response = await axios.post(
            '/api/payroll/my-clock-out',
            {},
            {
                headers: {
                    Authorization: `Bearer ${localStorage.getItem('api_token')}`,
                },
            }
        )
        return response.data
    },

    /**
     * Выход
     */
    async logout(forgetDevice = false) {
        const apiToken = localStorage.getItem('api_token')
        const deviceToken = localStorage.getItem('device_token')

        try {
            await axios.post(
                '/api/auth/logout-device',
                {
                    device_token: forgetDevice ? deviceToken : null,
                },
                {
                    headers: {
                        Authorization: `Bearer ${apiToken}`,
                    },
                }
            )
        } catch (error) {
            console.error('Logout error:', error)
        }

        localStorage.removeItem('api_token')

        if (forgetDevice) {
            localStorage.removeItem('device_token')
        }
    },

    /**
     * Проверка авторизации
     */
    async check() {
        const token = localStorage.getItem('api_token')
        if (!token) {
            return { success: false }
        }

        try {
            const response = await axios.get('/api/auth/check', {
                headers: {
                    Authorization: `Bearer ${token}`,
                },
            })
            return response.data
        } catch (error) {
            return { success: false }
        }
    },

    /**
     * Генерация fingerprint устройства
     */
    generateFingerprint() {
        const ua = navigator.userAgent
        const screen = `${window.screen.width}x${window.screen.height}`
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone
        const language = navigator.language

        const raw = `${ua}:${screen}:${timezone}:${language}`
        return btoa(raw).substring(0, 64)
    },

    /**
     * Получить название устройства
     */
    getDeviceName() {
        const ua = navigator.userAgent

        // Определяем браузер
        let browser = 'Unknown'
        if (ua.indexOf('Chrome') > -1) browser = 'Chrome'
        else if (ua.indexOf('Safari') > -1) browser = 'Safari'
        else if (ua.indexOf('Firefox') > -1) browser = 'Firefox'
        else if (ua.indexOf('Edge') > -1) browser = 'Edge'

        // Определяем ОС
        let os = 'Unknown'
        if (ua.indexOf('Windows') > -1) os = 'Windows'
        else if (ua.indexOf('Mac') > -1) os = 'macOS'
        else if (ua.indexOf('Linux') > -1) os = 'Linux'
        else if (ua.indexOf('Android') > -1) os = 'Android'
        else if (ua.indexOf('iOS') > -1 || ua.indexOf('iPhone') > -1) os = 'iOS'

        return `${browser} на ${os}`
    },

    /**
     * Определение типа приложения
     */
    getAppType() {
        const path = window.location.pathname
        if (path.includes('/pos')) return 'pos'
        if (path.includes('/waiter')) return 'waiter'
        if (path.includes('/courier')) return 'courier'
        if (path.includes('/kitchen')) return 'kitchen'
        if (path.includes('/backoffice')) return 'backoffice'
        return 'unknown'
    },

    /**
     * Установить API токен в заголовки Axios по умолчанию
     */
    setAxiosToken() {
        const token = localStorage.getItem('api_token')
        if (token) {
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
        }
    },

    /**
     * Инициализация (вызвать при старте приложения)
     */
    init() {
        // Защита от повторной инициализации
        if (this._initialized) {
            return
        }
        this._initialized = true

        this.setAxiosToken()

        // Обновляем токен в заголовках при каждом запросе
        axios.interceptors.request.use(config => {
            const token = localStorage.getItem('api_token')
            if (token) {
                config.headers['Authorization'] = `Bearer ${token}`
            }
            return config
        })

        // Обработка 401/403 ошибок
        axios.interceptors.response.use(
            response => response,
            error => {
                if (error.response?.status === 401 || error.response?.status === 403) {
                    const reason = error.response?.data?.reason

                    // Если токен невалиден - очищаем
                    if (reason === 'invalid_device_token' || reason === 'user_deactivated') {
                        localStorage.removeItem('api_token')
                        localStorage.removeItem('device_token')
                    }
                }
                return Promise.reject(error)
            }
        )
    },

    _initialized: false,
}
