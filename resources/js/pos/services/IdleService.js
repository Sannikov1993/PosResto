/**
 * IdleService - Детекция бездействия пользователя
 *
 * Отслеживает DOM-события активности (mousemove, keydown, pointerdown, scroll).
 * При бездействии в течение idleTimeout вызывает onIdle callback.
 *
 * @module services/IdleService
 */

const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'pointerdown', 'scroll'];

export class IdleService {
    /**
     * @param {Object} options
     * @param {number} options.idleTimeout - Таймаут бездействия в мс (по умолчанию 5 мин)
     * @param {Function} options.onIdle - Вызывается при бездействии
     * @param {Function} [options.onActive] - Вызывается при возврате активности
     */
    constructor({ idleTimeout = 5 * 60 * 1000, onIdle, onActive } = {}) {
        this._idleTimeout = idleTimeout;
        this._onIdle = onIdle;
        this._onActive = onActive;
        this._timerId = null;
        this._isIdle = false;
        this._rafId = null;
        this._running = false;

        // Привязываем обработчик для корректного removeEventListener
        this._handleActivity = this._handleActivity.bind(this);
    }

    /** Начать слушать события активности */
    start() {
        if (this._running) return;
        this._running = true;
        this._isIdle = false;

        ACTIVITY_EVENTS.forEach(event => {
            document.addEventListener(event, this._handleActivity, { passive: true });
        });

        this._startTimer();
    }

    /** Остановить слушатели и очистить таймеры */
    stop() {
        if (!this._running) return;
        this._running = false;

        ACTIVITY_EVENTS.forEach(event => {
            document.removeEventListener(event, this._handleActivity);
        });

        this._clearTimer();
        if (this._rafId) {
            cancelAnimationFrame(this._rafId);
            this._rafId = null;
        }
    }

    /** Сбросить таймер (например, после разблокировки) */
    resetTimer() {
        if (!this._running) return;
        this._isIdle = false;
        this._clearTimer();
        this._startTimer();
    }

    /** @private */
    _handleActivity() {
        // Дебаунс через requestAnimationFrame — не больше 1 сброса за кадр
        if (this._rafId) return;

        this._rafId = requestAnimationFrame(() => {
            this._rafId = null;

            if (this._isIdle) {
                this._isIdle = false;
                this._onActive?.();
            }

            this._clearTimer();
            this._startTimer();
        });
    }

    /** @private */
    _startTimer() {
        this._timerId = setTimeout(() => {
            this._isIdle = true;
            this._onIdle?.();
        }, this._idleTimeout);
    }

    /** @private */
    _clearTimer() {
        if (this._timerId) {
            clearTimeout(this._timerId);
            this._timerId = null;
        }
    }
}
