/**
 * Sound Configuration Constants
 *
 * Defines sound types and their synthesis parameters
 * for the Web Audio API notification system.
 *
 * @module kitchen/constants/sounds
 */

/**
 * Available notification sound types
 * @readonly
 * @enum {string}
 */
export const SOUND_TYPE = Object.freeze({
    /** Classic service bell with harmonics */
    BELL: 'bell',
    /** Melodic wind chime (3 notes) */
    CHIME: 'chime',
    /** Bright single ding */
    DING: 'ding',
    /** Double kitchen bell (ding-ding) */
    KITCHEN: 'kitchen',
    /** Two-tone pleasant alert */
    ALERT: 'alert',
    /** Deep gong with long decay */
    GONG: 'gong',
    /** Urgent overdue warning */
    OVERDUE: 'overdue',
});

/**
 * Default sound for different event types
 * @readonly
 */
export const DEFAULT_SOUNDS = Object.freeze({
    /** New order notification */
    NEW_ORDER: SOUND_TYPE.BELL,
    /** Order ready notification */
    ORDER_READY: SOUND_TYPE.CHIME,
    /** Overdue order warning */
    OVERDUE_WARNING: SOUND_TYPE.OVERDUE,
    /** Cancellation alert */
    CANCELLATION: SOUND_TYPE.ALERT,
    /** Stop list update */
    STOP_LIST: SOUND_TYPE.DING,
    /** Waiter call confirmation */
    WAITER_CALL: SOUND_TYPE.KITCHEN,
});

/**
 * Sound synthesis configurations
 * Each configuration defines how to generate the sound using Web Audio API
 * @readonly
 */
export const SOUND_CONFIG = Object.freeze({
    [SOUND_TYPE.BELL]: {
        name: 'Service Bell',
        description: 'Classic service bell with harmonics',
        fundamental: 880,
        harmonics: [1, 2, 3, 4.2, 5.4],
        duration: 1.5,
        type: 'harmonic',
    },

    [SOUND_TYPE.CHIME]: {
        name: 'Wind Chime',
        description: 'Melodic 3-note chord',
        notes: [1047, 1319, 1568], // C6, E6, G6 - major chord
        noteDelay: 0.15,
        duration: 1.2,
        type: 'sequence',
    },

    [SOUND_TYPE.DING]: {
        name: 'Bright Ding',
        description: 'Single bright tone',
        frequencies: [1200, 2400],
        durations: [0.8, 0.5],
        volumes: [0.4, 0.15],
        type: 'multi',
    },

    [SOUND_TYPE.KITCHEN]: {
        name: 'Kitchen Bell',
        description: 'Double ding-ding',
        frequencies: [1000, 2000],
        delays: [0, 0.25],
        duration: 0.3,
        type: 'double',
    },

    [SOUND_TYPE.ALERT]: {
        name: 'Alert Tone',
        description: 'Two-tone rising alert',
        frequencies: [880, 1100],
        delay: 0.2,
        duration: 0.4,
        type: 'rising',
    },

    [SOUND_TYPE.GONG]: {
        name: 'Deep Gong',
        description: 'Low gong with long decay',
        fundamental: 150,
        harmonics: [1, 1.5, 2, 2.5, 3, 4],
        duration: 3,
        type: 'gong',
    },

    [SOUND_TYPE.OVERDUE]: {
        name: 'Overdue Warning',
        description: 'Urgent three-tone alert',
        tones: [
            { freq1: 440, freq2: 554, delay: 0 },
            { freq1: 554, freq2: 698, delay: 0.5 },
            { freq1: 698, freq2: 880, delay: 1.0 },
        ],
        duration: 0.4,
        type: 'urgent',
    },
});

/**
 * Sound display options for settings UI
 * @type {Array<{value: string, label: string, description: string}>}
 */
export const SOUND_OPTIONS = Object.freeze(
    Object.entries(SOUND_CONFIG).map(([key, config]) => ({
        value: key,
        label: config.name,
        description: config.description,
    }))
);
