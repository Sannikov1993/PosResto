/**
 * Sound Synthesizer
 *
 * Web Audio API synthesizer for generating notification sounds.
 *
 * @module kitchen/services/audio/SoundSynthesizer
 */

import { SOUND_TYPE, SOUND_CONFIG } from '../../constants/sounds.js';
import { createLogger } from '../../../shared/services/logger.js';
import type {
    SoundConfig,
    SoundConfigHarmonic,
    SoundConfigSequence,
    SoundConfigMulti,
    SoundConfigDouble,
    SoundConfigRising,
    SoundConfigGong,
    SoundConfigUrgent,
} from '../../types/index.js';

const log = createLogger('SoundSynthesizer');

export class SoundSynthesizer {
    context: AudioContext;

    constructor(context: AudioContext) {
        this.context = context;
    }

    get now(): number {
        return this.context.currentTime;
    }

    createOscillator(type: OscillatorType, frequency: number): OscillatorNode {
        const osc = this.context.createOscillator();
        osc.type = type;
        osc.frequency.value = frequency;
        return osc;
    }

    createGain(initialGain = 0): GainNode {
        const gain = this.context.createGain();
        gain.gain.value = initialGain;
        return gain;
    }

    synthesizeHarmonic(config: SoundConfigHarmonic): void {
        const { fundamental, harmonics, duration } = config;
        const now = this.now;

        harmonics.forEach((harmonic: any, i: any) => {
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

    synthesizeSequence(config: SoundConfigSequence): void {
        const { notes, noteDelay, duration } = config;
        const now = this.now;

        notes.forEach((freq: any, i: any) => {
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

    synthesizeMulti(config: SoundConfigMulti): void {
        const { frequencies, durations, volumes } = config;
        const now = this.now;

        frequencies.forEach((freq: any, i: any) => {
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

    synthesizeDouble(config: SoundConfigDouble): void {
        const { frequencies, delays, duration } = config;
        const now = this.now;

        delays.forEach((delay: any) => {
            const startTime = now + delay;
            const gain = this.createGain();

            frequencies.forEach((freq: any) => {
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

    synthesizeRising(config: SoundConfigRising): void {
        const { frequencies, delay, duration } = config;
        const now = this.now;

        frequencies.forEach((freq: any, i: any) => {
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

    synthesizeGong(config: SoundConfigGong): void {
        const { fundamental, harmonics, duration } = config;
        const now = this.now;

        harmonics.forEach((harmonic: any, i: any) => {
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

    synthesizeUrgent(config: SoundConfigUrgent): void {
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

    synthesize(soundType: string): void {
        const config = SOUND_CONFIG[soundType];
        if (!config) {
            log.warn(`Unknown sound type: ${soundType}`);
            this.synthesize(SOUND_TYPE.BELL);
            return;
        }

        switch (config.type) {
            case 'harmonic':
                this.synthesizeHarmonic(config as SoundConfigHarmonic);
                break;
            case 'sequence':
                this.synthesizeSequence(config as SoundConfigSequence);
                break;
            case 'multi':
                this.synthesizeMulti(config as SoundConfigMulti);
                break;
            case 'double':
                this.synthesizeDouble(config as SoundConfigDouble);
                break;
            case 'rising':
                this.synthesizeRising(config as SoundConfigRising);
                break;
            case 'gong':
                this.synthesizeGong(config as SoundConfigGong);
                break;
            case 'urgent':
                this.synthesizeUrgent(config as SoundConfigUrgent);
                break;
            default:
                log.warn(`Unknown synthesis type: ${(config as any).type}`);
                this.synthesizeHarmonic(SOUND_CONFIG[SOUND_TYPE.BELL] as SoundConfigHarmonic);
        }
    }
}
