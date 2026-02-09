/**
 * Notification Sound Service
 *
 * Централизованный сервис для воспроизведения звуковых уведомлений.
 * Использует Web Audio API для генерации звуков без внешних файлов.
 *
 * @module shared/services/notificationSound
 */

import { createLogger } from './logger.js';

const log = createLogger('Sound');

// Singleton AudioContext instance
let audioContext = null;

/**
 * Получить или создать AudioContext
 * @returns {AudioContext|null}
 */
function getAudioContext() {
    if (typeof window === 'undefined') return null;

    if (!audioContext) {
        try {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                audioContext = new AudioContextClass();
            }
        } catch (e) {
            log.warn('AudioContext not supported:', e);
            return null;
        }
    }

    // Resume if suspended (browser autoplay policy)
    if (audioContext?.state === 'suspended') {
        audioContext.resume().catch(() => {});
    }

    return audioContext;
}

/**
 * Sound presets с частотами и длительностью
 */
const SOUND_PRESETS = {
    // Новый заказ - восходящая мелодия (позитивная)
    newOrder: {
        frequencies: [523, 659, 784], // C5 → E5 → G5
        durations: [0.15, 0.15, 0.2],
        gain: 0.3,
        type: 'sine',
    },
    // Заказ готов - приятный аккорд
    ready: {
        frequencies: [523, 659, 784], // C5 → E5 → G5
        durations: [0.15, 0.15, 0.2],
        gain: 0.25,
        type: 'sine',
    },
    // Предупреждение - нисходящий тон
    alert: {
        frequencies: [440, 349], // A4 → F4
        durations: [0.2, 0.2],
        gain: 0.3,
        type: 'triangle',
    },
    // Отмена - резкий звук
    cancel: {
        frequencies: [349, 294], // F4 → D4
        durations: [0.15, 0.25],
        gain: 0.35,
        type: 'sawtooth',
    },
    // Простой beep
    beep: {
        frequencies: [587], // D5
        durations: [0.2],
        gain: 0.2,
        type: 'sine',
    },
    // Успех - короткий позитивный
    success: {
        frequencies: [659, 784], // E5 → G5
        durations: [0.1, 0.15],
        gain: 0.2,
        type: 'sine',
    },
    // Ошибка - низкий тон
    error: {
        frequencies: [294, 262], // D4 → C4
        durations: [0.2, 0.3],
        gain: 0.3,
        type: 'triangle',
    },
    // Курьер назначен
    courierAssigned: {
        frequencies: [523, 587, 659], // C5 → D5 → E5
        durations: [0.1, 0.1, 0.15],
        gain: 0.25,
        type: 'sine',
    },
    // Кухня - новый заказ
    kitchenNew: {
        frequencies: [440, 523, 659], // A4 → C5 → E5
        durations: [0.12, 0.12, 0.2],
        gain: 0.3,
        type: 'sine',
    },
    // Кухня - заказ готов
    kitchenReady: {
        frequencies: [659, 784, 880], // E5 → G5 → A5
        durations: [0.1, 0.1, 0.25],
        gain: 0.25,
        type: 'sine',
    },
};

/**
 * Воспроизвести звук по пресету
 * @param {keyof SOUND_PRESETS} preset - Название пресета
 * @returns {boolean} - Успешность воспроизведения
 */
export function playSound(preset = 'beep') {
    const ctx = getAudioContext();
    if (!ctx) return false;

    const config = SOUND_PRESETS[preset] || SOUND_PRESETS.beep;

    try {
        let startTime = ctx.currentTime;

        config.frequencies.forEach((freq, index) => {
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
    } catch (e) {
        log.warn('Playback failed:', e);
        return false;
    }
}

/**
 * Воспроизвести звук нового заказа
 */
export function playNewOrderSound() {
    return playSound('newOrder');
}

/**
 * Воспроизвести звук готовности
 */
export function playReadySound() {
    return playSound('ready');
}

/**
 * Воспроизвести звук предупреждения
 */
export function playAlertSound() {
    return playSound('alert');
}

/**
 * Воспроизвести звук отмены
 */
export function playCancelSound() {
    return playSound('cancel');
}

/**
 * Воспроизвести звук успеха
 */
export function playSuccessSound() {
    return playSound('success');
}

/**
 * Воспроизвести звук ошибки
 */
export function playErrorSound() {
    return playSound('error');
}

/**
 * Aliases для обратной совместимости
 */
export const playNotificationSound = playSound;

/**
 * Default export
 */
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
