/**
 * LogoutBroadcast - Кросс-табовая синхронизация логаута
 *
 * @module services/LogoutBroadcast
 */

const CHANNEL_NAME = 'menulab_logout';
const STORAGE_KEY = 'menulab_logout_signal';

export class LogoutBroadcast {
    private _channel: BroadcastChannel | null = null;
    private _callback: (() => void) | null = null;
    private _storageHandler: ((event: StorageEvent) => void) | null = null;

    constructor() {
        if (typeof BroadcastChannel !== 'undefined') {
            this._channel = new BroadcastChannel(CHANNEL_NAME);
        }
    }

    notifyLogout(): void {
        if (this._channel) {
            this._channel.postMessage({ type: 'logout', timestamp: Date.now() });
        } else {
            localStorage.setItem(STORAGE_KEY, Date.now().toString());
            localStorage.removeItem(STORAGE_KEY);
        }
    }

    onLogout(callback: () => void): void {
        this._callback = callback;

        if (this._channel) {
            this._channel.onmessage = (event: MessageEvent) => {
                if (event.data?.type === 'logout') {
                    callback();
                }
            };
        } else {
            this._storageHandler = (event: StorageEvent) => {
                if (event.key === STORAGE_KEY && event.newValue) {
                    callback();
                }
            };
            window.addEventListener('storage', this._storageHandler);
        }
    }

    destroy(): void {
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
