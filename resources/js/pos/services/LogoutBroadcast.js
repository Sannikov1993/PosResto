/**
 * LogoutBroadcast - Кросс-табовая синхронизация логаута
 *
 * Использует BroadcastChannel API для уведомления других вкладок
 * о логауте. Fallback на localStorage event для старых браузеров.
 *
 * @module services/LogoutBroadcast
 */

const CHANNEL_NAME = 'menulab_logout';
const STORAGE_KEY = 'menulab_logout_signal';

export class LogoutBroadcast {
    constructor() {
        this._channel = null;
        this._callback = null;
        this._storageHandler = null;

        // BroadcastChannel с fallback на localStorage
        if (typeof BroadcastChannel !== 'undefined') {
            this._channel = new BroadcastChannel(CHANNEL_NAME);
        }
    }

    /** Уведомить другие вкладки о логауте */
    notifyLogout() {
        if (this._channel) {
            this._channel.postMessage({ type: 'logout', timestamp: Date.now() });
        } else {
            // Fallback: localStorage event (срабатывает в других вкладках)
            localStorage.setItem(STORAGE_KEY, Date.now().toString());
            localStorage.removeItem(STORAGE_KEY);
        }
    }

    /**
     * Подписаться на логаут из другой вкладки
     * @param {Function} callback
     */
    onLogout(callback) {
        this._callback = callback;

        if (this._channel) {
            this._channel.onmessage = (event) => {
                if (event.data?.type === 'logout') {
                    callback();
                }
            };
        } else {
            // Fallback: слушаем storage event
            this._storageHandler = (event) => {
                if (event.key === STORAGE_KEY && event.newValue) {
                    callback();
                }
            };
            window.addEventListener('storage', this._storageHandler);
        }
    }

    /** Очистить ресурсы */
    destroy() {
        if (this._channel) {
            this._channel.close();
            this._channel = null;
        }
        if (this._storageHandler) {
            window.removeEventListener('storage', this._storageHandler);
            this._storageHandler = null;
        }
        this._callback = null;
    }
}
