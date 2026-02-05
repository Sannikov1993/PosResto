/**
 * Centralized Logger Service
 *
 * Enterprise-level logging with environment-aware output.
 * In production, only warnings and errors are logged.
 * In development, all levels are active.
 *
 * @module shared/services/logger
 */

const isDev = import.meta.env?.DEV ?? (typeof process !== 'undefined' && process.env?.NODE_ENV !== 'production');

const LOG_LEVELS = { debug: 0, info: 1, warn: 2, error: 3, silent: 4 };

// В проде только warn+error, в dev — всё
const currentLevel = isDev ? LOG_LEVELS.debug : LOG_LEVELS.warn;

/**
 * Создать логгер с префиксом модуля
 * @param {string} module - Название модуля (например, 'POS', 'Kitchen', 'Courier')
 * @returns {Object} Logger instance
 */
export function createLogger(module) {
    const prefix = `[${module}]`;

    return {
        debug(...args) {
            if (currentLevel <= LOG_LEVELS.debug) {
                console.log(prefix, ...args);
            }
        },

        info(...args) {
            if (currentLevel <= LOG_LEVELS.info) {
                console.log(prefix, ...args);
            }
        },

        warn(...args) {
            if (currentLevel <= LOG_LEVELS.warn) {
                console.warn(prefix, ...args);
            }
        },

        error(...args) {
            if (currentLevel <= LOG_LEVELS.error) {
                console.error(prefix, ...args);
            }
        },
    };
}

// Дефолтный логгер для общего использования
const logger = createLogger('App');

export default logger;
