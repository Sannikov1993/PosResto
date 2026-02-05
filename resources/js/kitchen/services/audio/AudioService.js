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

const log = createLogger('AudioService');

/**
 * Audio notification service
 * Manages Web Audio API context and sound playback
 */
export class AudioService {
    constructor() {
        /** @type {AudioContext|null} */
        this._context = null;

        /** @type {SoundSynthesizer|null} */
        this._synthesizer = null;

        /** @type {boolean} */
        this._enabled = true;

        /** @type {boolean} */
        this._initialized = false;

        /** @type {number} */
        this._volume = 1.0;

        // Track user interaction for autoplay policy
        this._userInteracted = false;
        this._pendingSounds = [];

        // Bind methods
        this._onUserInteraction = this._onUserInteraction.bind(this);
    }

    /**
     * Initialize audio context (must be called after user interaction)
     * @returns {AudioContext}
     */
    _getContext() {
        if (!this._context) {
            this._context = new (window.AudioContext || window.webkitAudioContext)();
            this._synthesizer = new SoundSynthesizer(this._context);
            this._initialized = true;
        }

        // Resume if suspended (due to autoplay policy)
        if (this._context.state === 'suspended') {
            this._context.resume();
        }

        return this._context;
    }

    /**
     * Handle user interaction to enable audio
     * @private
     */
    _onUserInteraction() {
        if (this._userInteracted) return;

        this._userInteracted = true;

        // Initialize context on first interaction
        try {
            this._getContext();

            // Play any pending sounds
            this._pendingSounds.forEach(sound => {
                this.play(sound);
            });
            this._pendingSounds = [];
        } catch (e) {
            log.error('Failed to initialize:', e);
        }

        // Remove listeners
        document.removeEventListener('click', this._onUserInteraction);
        document.removeEventListener('touchstart', this._onUserInteraction);
        document.removeEventListener('keydown', this._onUserInteraction);
    }

    /**
     * Initialize service and set up user interaction listeners
     */
    initialize() {
        if (this._initialized) return;

        // Listen for user interaction to enable audio
        document.addEventListener('click', this._onUserInteraction);
        document.addEventListener('touchstart', this._onUserInteraction);
        document.addEventListener('keydown', this._onUserInteraction);
    }

    /**
     * Check if audio is enabled
     * @returns {boolean}
     */
    get enabled() {
        return this._enabled;
    }

    /**
     * Enable or disable audio
     * @param {boolean} enabled
     */
    set enabled(enabled) {
        this._enabled = enabled;
    }

    /**
     * Get current volume (0-1)
     * @returns {number}
     */
    get volume() {
        return this._volume;
    }

    /**
     * Set volume (0-1)
     * @param {number} volume
     */
    set volume(volume) {
        this._volume = Math.max(0, Math.min(1, volume));
    }

    /**
     * Toggle audio enabled state
     * @returns {boolean} New enabled state
     */
    toggle() {
        this._enabled = !this._enabled;
        return this._enabled;
    }

    /**
     * Play a notification sound
     * @param {string} soundType - Sound type from SOUND_TYPE enum
     */
    play(soundType) {
        if (!this._enabled) return;

        // Queue sound if not yet interacted
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
        } catch (e) {
            log.error('Failed to play sound:', e);
        }
    }

    /**
     * Play new order notification
     * @param {string} [customSound] - Custom sound type override
     */
    playNewOrder(customSound) {
        this.play(customSound || DEFAULT_SOUNDS.NEW_ORDER);
    }

    /**
     * Play order ready notification
     * @param {string} [customSound] - Custom sound type override
     */
    playOrderReady(customSound) {
        this.play(customSound || DEFAULT_SOUNDS.ORDER_READY);
    }

    /**
     * Play overdue warning
     */
    playOverdueWarning() {
        this.play(DEFAULT_SOUNDS.OVERDUE_WARNING);
    }

    /**
     * Play cancellation alert
     */
    playCancellation() {
        this.play(DEFAULT_SOUNDS.CANCELLATION);
    }

    /**
     * Play stop list notification
     */
    playStopList() {
        this.play(DEFAULT_SOUNDS.STOP_LIST);
    }

    /**
     * Play waiter call confirmation
     */
    playWaiterCall() {
        this.play(DEFAULT_SOUNDS.WAITER_CALL);
    }

    /**
     * Play station-specific notification
     * @param {string|null} stationSound - Station's configured sound
     */
    playStationNotification(stationSound) {
        this.play(stationSound || DEFAULT_SOUNDS.NEW_ORDER);
    }

    /**
     * Destroy the service and clean up resources
     */
    destroy() {
        // Remove event listeners
        document.removeEventListener('click', this._onUserInteraction);
        document.removeEventListener('touchstart', this._onUserInteraction);
        document.removeEventListener('keydown', this._onUserInteraction);

        // Close audio context
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

// Singleton instance
export const audioService = new AudioService();
