/**
 * Sound Synthesizer
 *
 * Web Audio API synthesizer for generating notification sounds.
 * Supports various sound types with configurable parameters.
 *
 * @module kitchen/services/audio/SoundSynthesizer
 */

import { SOUND_TYPE, SOUND_CONFIG } from '../../constants/sounds.js';
import { createLogger } from '../../../shared/services/logger.js';

const log = createLogger('SoundSynthesizer');

/**
 * Sound synthesizer using Web Audio API
 */
export class SoundSynthesizer {
    /**
     * @param {AudioContext} context - Web Audio API context
     */
    constructor(context) {
        this.context = context;
    }

    /**
     * Get current time from audio context
     * @returns {number}
     */
    get now() {
        return this.context.currentTime;
    }

    /**
     * Create and configure an oscillator
     * @param {string} type - Oscillator type (sine, triangle, square, sawtooth)
     * @param {number} frequency - Frequency in Hz
     * @returns {OscillatorNode}
     */
    createOscillator(type, frequency) {
        const osc = this.context.createOscillator();
        osc.type = type;
        osc.frequency.value = frequency;
        return osc;
    }

    /**
     * Create and configure a gain node
     * @param {number} [initialGain=0] - Initial gain value
     * @returns {GainNode}
     */
    createGain(initialGain = 0) {
        const gain = this.context.createGain();
        gain.gain.value = initialGain;
        return gain;
    }

    /**
     * Synthesize a harmonic sound (like a bell)
     * @param {Object} config - Sound configuration
     */
    synthesizeHarmonic(config) {
        const { fundamental, harmonics, duration } = config;
        const now = this.now;

        harmonics.forEach((harmonic, i) => {
            const osc = this.createOscillator('sine', fundamental * harmonic);
            const gain = this.createGain();

            const volume = 0.3 / (i + 1);
            gain.gain.setValueAtTime(volume, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + duration);

            osc.connect(gain);
            gain.connect(this.context.destination);

            osc.start(now);
            osc.stop(now + duration);
        });
    }

    /**
     * Synthesize a sequence of notes (like wind chimes)
     * @param {Object} config - Sound configuration
     */
    synthesizeSequence(config) {
        const { notes, noteDelay, duration } = config;
        const now = this.now;

        notes.forEach((freq, i) => {
            const osc = this.createOscillator('sine', freq);
            const gain = this.createGain();
            const startTime = now + i * noteDelay;

            gain.gain.setValueAtTime(0, startTime);
            gain.gain.linearRampToValueAtTime(0.25, startTime + 0.05);
            gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);

            osc.connect(gain);
            gain.connect(this.context.destination);

            osc.start(startTime);
            osc.stop(startTime + duration);
        });
    }

    /**
     * Synthesize multi-frequency sound (like a ding)
     * @param {Object} config - Sound configuration
     */
    synthesizeMulti(config) {
        const { frequencies, durations, volumes } = config;
        const now = this.now;

        frequencies.forEach((freq, i) => {
            const osc = this.createOscillator('sine', freq);
            const gain = this.createGain();

            gain.gain.setValueAtTime(volumes[i], now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + durations[i]);

            osc.connect(gain);
            gain.connect(this.context.destination);

            osc.start(now);
            osc.stop(now + durations[i]);
        });
    }

    /**
     * Synthesize double sound (like kitchen bell)
     * @param {Object} config - Sound configuration
     */
    synthesizeDouble(config) {
        const { frequencies, delays, duration } = config;
        const now = this.now;

        delays.forEach((delay) => {
            const startTime = now + delay;
            const gain = this.createGain();

            frequencies.forEach((freq) => {
                const osc = this.createOscillator('sine', freq);
                osc.connect(gain);
                osc.start(startTime);
                osc.stop(startTime + duration);
            });

            gain.gain.setValueAtTime(0.35, startTime);
            gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
            gain.connect(this.context.destination);
        });
    }

    /**
     * Synthesize rising alert sound
     * @param {Object} config - Sound configuration
     */
    synthesizeRising(config) {
        const { frequencies, delay, duration } = config;
        const now = this.now;

        frequencies.forEach((freq, i) => {
            const osc = this.createOscillator('sine', freq);
            const gain = this.createGain();
            const startTime = now + i * delay;

            gain.gain.setValueAtTime(0, startTime);
            gain.gain.linearRampToValueAtTime(0.3, startTime + 0.05);
            gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);

            osc.connect(gain);
            gain.connect(this.context.destination);

            osc.start(startTime);
            osc.stop(startTime + duration);
        });
    }

    /**
     * Synthesize gong sound with long decay
     * @param {Object} config - Sound configuration
     */
    synthesizeGong(config) {
        const { fundamental, harmonics, duration } = config;
        const now = this.now;

        harmonics.forEach((harmonic, i) => {
            const osc = this.createOscillator(i === 0 ? 'sine' : 'triangle', fundamental * harmonic);
            const gain = this.createGain();

            const volume = 0.25 / (i + 1);
            gain.gain.setValueAtTime(volume, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + duration);

            osc.connect(gain);
            gain.connect(this.context.destination);

            osc.start(now);
            osc.stop(now + duration);
        });
    }

    /**
     * Synthesize urgent warning sound
     * @param {Object} config - Sound configuration
     */
    synthesizeUrgent(config) {
        const { tones, duration } = config;
        const now = this.now;

        tones.forEach(({ freq1, freq2, delay }) => {
            const startTime = now + delay;
            const osc1 = this.createOscillator('sine', freq1);
            const osc2 = this.createOscillator('sine', freq2);
            const gain = this.createGain();

            gain.gain.setValueAtTime(0.25, startTime);
            gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);

            osc1.connect(gain);
            osc2.connect(gain);
            gain.connect(this.context.destination);

            osc1.start(startTime);
            osc2.start(startTime);
            osc1.stop(startTime + duration);
            osc2.stop(startTime + duration);
        });
    }

    /**
     * Synthesize a sound by type
     * @param {string} soundType - Sound type from SOUND_TYPE enum
     */
    synthesize(soundType) {
        const config = SOUND_CONFIG[soundType];
        if (!config) {
            log.warn(`Unknown sound type: ${soundType}`);
            // Fall back to bell
            this.synthesize(SOUND_TYPE.BELL);
            return;
        }

        switch (config.type) {
            case 'harmonic':
                this.synthesizeHarmonic(config);
                break;
            case 'sequence':
                this.synthesizeSequence(config);
                break;
            case 'multi':
                this.synthesizeMulti(config);
                break;
            case 'double':
                this.synthesizeDouble(config);
                break;
            case 'rising':
                this.synthesizeRising(config);
                break;
            case 'gong':
                this.synthesizeGong(config);
                break;
            case 'urgent':
                this.synthesizeUrgent(config);
                break;
            default:
                log.warn(`Unknown synthesis type: ${config.type}`);
                this.synthesizeHarmonic(SOUND_CONFIG[SOUND_TYPE.BELL]);
        }
    }
}
