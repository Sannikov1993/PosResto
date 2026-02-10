/**
 * LockScreenTimer - Таймер автологаута с lock screen
 *
 * @module services/LockScreenTimer
 */

export class LockScreenTimer {
    private _timeout: number;
    private _onExpire?: () => void;
    private _timerId: ReturnType<typeof setTimeout> | null = null;
    private _startedAt: number | null = null;

    constructor(timeout = 30 * 60 * 1000, onExpire?: () => void) {
        this._timeout = timeout;
        this._onExpire = onExpire;
    }

    start(): void {
        this.stop();
        this._startedAt = Date.now();
        this._timerId = setTimeout(() => {
            this._onExpire?.();
        }, this._timeout);
    }

    stop(): void {
        if (this._timerId) {
            clearTimeout(this._timerId);
            this._timerId = null;
        }
        this._startedAt = null;
    }

    getRemainingMs(): number {
        if (!this._startedAt) return 0;
        const elapsed = Date.now() - this._startedAt;
        return Math.max(0, this._timeout - elapsed);
    }
}
