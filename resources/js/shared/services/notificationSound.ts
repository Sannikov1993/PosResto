/**
 * Notification Sound Service
 *
 * Uses Web Audio API for generating sounds without external files.
 *
 * @module shared/services/notificationSound
 */

import { createLogger } from './logger.js';

const log = createLogger('Sound');

type OscillatorWaveType = 'sine' | 'triangle' | 'sawtooth' | 'square';

interface SoundPreset {
    frequencies: number[];
    durations: number[];
    gain: number;
    type: OscillatorWaveType;
}

let audioContext: AudioContext | null = null;

function getAudioContext(): AudioContext | null {
    if (typeof window === 'undefined') return null;

    if (!audioContext) {
        try {
            const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
            if (AudioContextClass) {
                audioContext = new AudioContextClass();
            }
        } catch (e: any) {
            log.warn('AudioContext not supported:', e);
            return null;
        }
    }

    if (audioContext?.state === 'suspended') {
        audioContext.resume().catch(() => {});
    }

    return audioContext;
}

const SOUND_PRESETS: Record<string, SoundPreset> = {
    newOrder: {
        frequencies: [523, 659, 784],
        durations: [0.15, 0.15, 0.2],
        gain: 0.3,
        type: 'sine',
    },
    ready: {
        frequencies: [523, 659, 784],
        durations: [0.15, 0.15, 0.2],
        gain: 0.25,
        type: 'sine',
    },
    alert: {
        frequencies: [440, 349],
        durations: [0.2, 0.2],
        gain: 0.3,
        type: 'triangle',
    },
    cancel: {
        frequencies: [349, 294],
        durations: [0.15, 0.25],
        gain: 0.35,
        type: 'sawtooth',
    },
    beep: {
        frequencies: [587],
        durations: [0.2],
        gain: 0.2,
        type: 'sine',
    },
    success: {
        frequencies: [659, 784],
        durations: [0.1, 0.15],
        gain: 0.2,
        type: 'sine',
    },
    error: {
        frequencies: [294, 262],
        durations: [0.2, 0.3],
        gain: 0.3,
        type: 'triangle',
    },
    courierAssigned: {
        frequencies: [523, 587, 659],
        durations: [0.1, 0.1, 0.15],
        gain: 0.25,
        type: 'sine',
    },
    kitchenNew: {
        frequencies: [440, 523, 659],
        durations: [0.12, 0.12, 0.2],
        gain: 0.3,
        type: 'sine',
    },
    kitchenReady: {
        frequencies: [659, 784, 880],
        durations: [0.1, 0.1, 0.25],
        gain: 0.25,
        type: 'sine',
    },
};

export function playSound(preset: string = 'beep'): boolean {
    const ctx = getAudioContext();
    if (!ctx) return false;

    const config = SOUND_PRESETS[preset] || SOUND_PRESETS.beep;

    try {
        let startTime = ctx.currentTime;

        config.frequencies.forEach((freq: any, index: any) => {
            const oscillator = ctx.createOscillator();
            const gainNode = ctx.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(ctx.destination);

            oscillator.type = config.type;
            oscillator.frequency.setValueAtTime(freq, startTime);

            const duration = config.durations[index] || 0.15;
            gainNode.gain.setValueAtTime(config.gain, startTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, startTime + duration);

            oscillator.start(startTime);
            oscillator.stop(startTime + duration + 0.05);

            startTime += duration;
        });

        return true;
    } catch (e: any) {
        log.warn('Playback failed:', e);
        return false;
    }
}

export function playNewOrderSound(): boolean {
    return playSound('newOrder');
}

export function playReadySound(): boolean {
    return playSound('ready');
}

export function playAlertSound(): boolean {
    return playSound('alert');
}

export function playCancelSound(): boolean {
    return playSound('cancel');
}

export function playSuccessSound(): boolean {
    return playSound('success');
}

export function playErrorSound(): boolean {
    return playSound('error');
}

export const playNotificationSound = playSound;

export default {
    playSound,
    playNewOrderSound,
    playReadySound,
    playAlertSound,
    playCancelSound,
    playSuccessSound,
    playErrorSound,
    SOUND_PRESETS,
};
