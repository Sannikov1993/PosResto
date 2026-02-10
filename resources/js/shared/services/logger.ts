/**
 * Centralized Logger Service
 *
 * Enterprise-level logging with environment-aware output.
 * In production, only warnings and errors are logged.
 * In development, all levels are active.
 *
 * @module shared/services/logger
 */

export interface Logger {
    debug(...args: unknown[]): void;
    info(...args: unknown[]): void;
    warn(...args: unknown[]): void;
    error(...args: unknown[]): void;
}

const LOG_LEVELS = { debug: 0, info: 1, warn: 2, error: 3, silent: 4 } as const;

const isDev = (import.meta as any).env?.DEV ?? (typeof process !== 'undefined' && process.env?.NODE_ENV !== 'production');

const currentLevel: number = isDev ? LOG_LEVELS.debug : LOG_LEVELS.warn;

/**
 * Create a logger with module prefix
 */
export function createLogger(module: string): Logger {
    const prefix = `[${module}]`;

    return {
        debug(...args: unknown[]) {
            if (currentLevel <= LOG_LEVELS.debug) {
                console.log(prefix, ...args);
            }
        },

        info(...args: unknown[]) {
            if (currentLevel <= LOG_LEVELS.info) {
                console.log(prefix, ...args);
            }
        },

        warn(...args: unknown[]) {
            if (currentLevel <= LOG_LEVELS.warn) {
                console.warn(prefix, ...args);
            }
        },

        error(...args: unknown[]) {
            if (currentLevel <= LOG_LEVELS.error) {
                console.error(prefix, ...args);
            }
        },
    };
}

const logger = createLogger('App');

export default logger;
