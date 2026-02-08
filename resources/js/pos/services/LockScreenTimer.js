/**
 * LockScreenTimer - Таймер автологаута с lock screen
 *
 * После блокировки экрана запускается таймер.
 * По истечении timeout вызывается onExpire → полный логаут.
 *
 * @module services/LockScreenTimer
 */

export class LockScreenTimer {
    /**
     * @param {number} timeout - Таймаут в мс (по умолчанию 30 мин)
     * @param {Function} onExpire - Вызывается при истечении таймера
     */
    constructor(timeout = 30 * 60 * 1000, onExpire) {
        this._timeout = timeout;
        this._onExpire = onExpire;
        this._timerId = null;
        this._startedAt = null;
    }

    /** Запустить таймер */
    start() {
        this.stop();
        this._startedAt = Date.now();
        this._timerId = setTimeout(() => {
            this._onExpire?.();
        }, this._timeout);
    }

    /** Остановить таймер */
    stop() {
        if (this._timerId) {
            clearTimeout(this._timerId);
            this._timerId = null;
        }
        this._startedAt = null;
    }

    /** Получить оставшееся время в мс */
    getRemainingMs() {
        if (!this._startedAt) return 0;
        const elapsed = Date.now() - this._startedAt;
        return Math.max(0, this._timeout - elapsed);
    }
}
