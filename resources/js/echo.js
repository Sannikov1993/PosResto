/**
 * Laravel Echo configuration for Reverb WebSocket
 *
 * Этот файл инициализирует Laravel Echo с Reverb для real-time событий.
 * Echo создаётся лениво — только при первом обращении к window.Echo
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Делаем Pusher доступным глобально (требуется для Echo)
window.Pusher = Pusher;

/**
 * Получает токен авторизации из localStorage
 */
function getAuthToken() {
    try {
        // Пробуем разные форматы хранения токена
        const session = JSON.parse(localStorage.getItem('menulab_session'));
        if (session?.token) {
            return session.token;
        }
    } catch {
        // ignore
    }

    // Fallback для других приложений (waiter, courier)
    const apiToken = localStorage.getItem('api_token');
    if (apiToken) {
        return apiToken;
    }

    const courierToken = localStorage.getItem('courier_token');
    if (courierToken) {
        return courierToken;
    }

    return '';
}

/**
 * Создаёт и настраивает Echo instance
 */
function createEcho() {
    const token = getAuthToken();

    // Проверяем наличие конфигурации
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    const host = import.meta.env.VITE_REVERB_HOST || 'localhost';
    const port = import.meta.env.VITE_REVERB_PORT || 8080;
    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';

    if (!key) {
        console.warn('[Echo] VITE_REVERB_APP_KEY not set, skipping Echo initialization');
        return null;
    }

    const echo = new Echo({
        broadcaster: 'reverb',
        key: key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                Authorization: token ? `Bearer ${token}` : '',
            },
        },
    });

    console.log(`[Echo] Initialized with host=${host}:${port}, key=${key}`);

    return echo;
}

// Ленивая инициализация Echo
let echoInstance = null;

/**
 * Получает или создаёт Echo instance
 */
export function getEcho() {
    if (!echoInstance) {
        echoInstance = createEcho();
        window.Echo = echoInstance;
    }
    return echoInstance;
}

// Геттер для window.Echo (ленивая инициализация)
Object.defineProperty(window, 'Echo', {
    get() {
        if (!echoInstance) {
            echoInstance = createEcho();
        }
        return echoInstance;
    },
    set(value) {
        echoInstance = value;
    },
    configurable: true,
});

/**
 * Обновляет токен авторизации для Echo
 * Вызывайте после логина или обновления токена
 */
export function updateEchoToken(newToken) {
    if (echoInstance?.connector?.pusher?.config?.auth?.headers) {
        echoInstance.connector.pusher.config.auth.headers.Authorization = `Bearer ${newToken}`;
    }
}

/**
 * Переподключает Echo с новым токеном
 * Используйте если нужно полное переподключение
 */
export function reconnectEcho() {
    if (echoInstance) {
        // Only disconnect if WebSocket is in OPEN state (readyState === 1)
        // This prevents "WebSocket is closed before the connection is established" error
        const wsState = echoInstance.connector?.pusher?.connection?.socket?.readyState;
        if (wsState === 1) { // WebSocket.OPEN
            echoInstance.disconnect();
        }
    }
    echoInstance = createEcho();
    return echoInstance;
}

export default { getEcho, updateEchoToken, reconnectEcho };
