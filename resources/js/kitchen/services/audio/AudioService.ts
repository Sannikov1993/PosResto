/**
 * Audio Service
 *
 * High-level service for managing audio notifications
 * in the kitchen display system.
 *
 * @module kitchen/services/audio/AudioService
 */

import { SoundSynthesizer } from './SoundSynthesizer.js';
import { SOUND_TYPE, DEFAULT_SOUNDS } from '../../constants/sounds.js';
import { createLogger } from '../../../shared/services/logger.js';

declare global {
    interface Window {
        webkitAudioContext?: typeof AudioContext;
    }
}

const log = createLogger('AudioService');

export class AudioService {
    private _context: AudioContext | null = null;
    private _synthesizer: SoundSynthesizer | null = null;
    private _enabled = true;
    private _initialized = false;
    private _volume = 1.0;
    private _userInteracted = false;
    private _pendingSounds: string[] = [];
    private _onUserInteraction: () => void;

    constructor() {
        this._onUserInteraction = this._handleUserInteraction.bind(this);
    }

    private _getContext(): AudioContext {
        if (!this._context) {
            this._context = new (window.AudioContext || window.webkitAudioContext!)();
            this._synthesizer = new SoundSynthesizer(this._context);
            this._initialized = true;
        }

        if (this._context.state === 'suspended') {
            this._context.resume();
        }

        return this._context;
    }

    private _handleUserInteraction(): void {
        if (this._userInteracted) return;

        this._userInteracted = true;

        try {
            this._getContext();

            this._pendingSounds.forEach((sound: any) => {
                this.play(sound);
            });
            this._pendingSounds = [];
        } catch (e: any) {
            log.error('Failed to initialize:', e);
        }

        document.removeEventListener('click', this._onUserInteraction);
        document.removeEventListener('touchstart', this._onUserInteraction);
        document.removeEventListener('keydown', this._onUserInteraction);
    }

    initialize(): void {
        if (this._initialized) return;

        document.addEventListener('click', this._onUserInteraction);
        document.addEventListener('touchstart', this._onUserInteraction);
        document.addEventListener('keydown', this._onUserInteraction);
    }

    get enabled(): boolean {
        return this._enabled;
    }

    set enabled(enabled: boolean) {
        this._enabled = enabled;
    }

    get volume(): number {
        return this._volume;
    }

    set volume(volume: number) {
        this._volume = Math.max(0, Math.min(1, volume));
    }

    toggle(): boolean {
        this._enabled = !this._enabled;
        return this._enabled;
    }

    play(soundType: string): void {
        if (!this._enabled) return;

        if (!this._userInteracted) {
            this._pendingSounds.push(soundType);
            return;
        }

        try {
            const context = this._getContext();
            if (!this._synthesizer) {
                this._synthesizer = new SoundSynthesizer(context);
            }
            this._synthesizer.synthesize(soundType);
        } catch (e: any) {
            log.error('Failed to play sound:', e);
        }
    }

    playNewOrder(customSound?: string): void {
        this.play(customSound || DEFAULT_SOUNDS.NEW_ORDER);
    }

    playOrderReady(customSound?: string): void {
        this.play(customSound || DEFAULT_SOUNDS.ORDER_READY);
    }

    playOverdueWarning(): void {
        this.play(DEFAULT_SOUNDS.OVERDUE_WARNING);
    }

    playCancellation(): void {
        this.play(DEFAULT_SOUNDS.CANCELLATION);
    }

    playStopList(): void {
        this.play(DEFAULT_SOUNDS.STOP_LIST);
    }

    playWaiterCall(): void {
        this.play(DEFAULT_SOUNDS.WAITER_CALL);
    }

    playStationNotification(stationSound: string | null | undefined): void {
        this.play(stationSound || DEFAULT_SOUNDS.NEW_ORDER);
    }

    destroy(): void {
        document.removeEventListener('click', this._onUserInteraction);
        document.removeEventListener('touchstart', this._onUserInteraction);
        document.removeEventListener('keydown', this._onUserInteraction);

        if (this._context) {
            this._context.close();
            this._context = null;
        }

        this._synthesizer = null;
        this._initialized = false;
        this._userInteracted = false;
        this._pendingSounds = [];
    }
}

export const audioService = new AudioService();
