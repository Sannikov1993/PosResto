/**
 * IdleService - Детекция бездействия пользователя
 *
 * @module services/IdleService
 */

const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'pointerdown', 'scroll'] as const;

interface IdleServiceOptions {
    idleTimeout?: number;
    onIdle?: () => void;
    onActive?: () => void;
}

export class IdleService {
    private _idleTimeout: number;
    private _onIdle?: () => void;
    private _onActive?: () => void;
    private _timerId: ReturnType<typeof setTimeout> | null = null;
    private _isIdle = false;
    private _rafId: number | null = null;
    private _running = false;
    private _handleActivity: () => void;

    constructor({ idleTimeout = 5 * 60 * 1000, onIdle, onActive }: IdleServiceOptions = {}) {
        this._idleTimeout = idleTimeout;
        this._onIdle = onIdle;
        this._onActive = onActive;

        this._handleActivity = this._onActivity.bind(this);
    }

    start(): void {
        if (this._running) return;
        this._running = true;
        this._isIdle = false;

        ACTIVITY_EVENTS.forEach((event: any) => {
            document.addEventListener(event, this._handleActivity, { passive: true });
        });

        this._startTimer();
    }

    stop(): void {
        if (!this._running) return;
        this._running = false;

        ACTIVITY_EVENTS.forEach((event: any) => {
            document.removeEventListener(event, this._handleActivity);
        });

        this._clearTimer();
        if (this._rafId) {
            cancelAnimationFrame(this._rafId);
            this._rafId = null;
        }
    }

    resetTimer(): void {
        if (!this._running) return;
        this._isIdle = false;
        this._clearTimer();
        this._startTimer();
    }

    private _onActivity(): void {
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

    private _startTimer(): void {
        this._timerId = setTimeout(() => {
            this._isIdle = true;
            this._onIdle?.();
        }, this._idleTimeout);
    }

    private _clearTimer(): void {
        if (this._timerId) {
            clearTimeout(this._timerId);
            this._timerId = null;
        }
    }
}
