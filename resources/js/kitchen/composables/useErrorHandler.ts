/**
 * Error Handler Composable
 *
 * Global error handling with user notifications and logging.
 *
 * @module kitchen/composables/useErrorHandler
 */

import { ref, readonly } from 'vue';
import { KitchenApiError } from '../services/api/errors.js';
import { createLogger } from '../../shared/services/logger.js';
import type { ErrorRecord, ErrorHandlerOptions, RetryOptions } from '../types/index.js';

const log = createLogger('ErrorHandler');

export const ERROR_SEVERITY = {
    INFO: 'info',
    WARNING: 'warning',
    ERROR: 'error',
    CRITICAL: 'critical',
} as const;

const lastError = ref<ErrorRecord | null>(null);
const errorHistory = ref<ErrorRecord[]>([]);
const maxHistorySize = 50;

const config = {
    logToConsole: true,
    maxRetries: 3,
    retryDelay: 1000,
};

function logError(error: Error, context: string, severity: string): void {
    if (!config.logToConsole) return;

    const timestamp = new Date().toISOString();
    const prefix = `[${timestamp}] [${severity.toUpperCase()}] [${context}]`;

    if (severity === ERROR_SEVERITY.CRITICAL || severity === ERROR_SEVERITY.ERROR) {
        log.error(prefix, error);
    } else if (severity === ERROR_SEVERITY.WARNING) {
        log.warn(prefix, error.message || error);
    } else {
        log.info(prefix, error.message || error);
    }
}

function addToHistory(errorRecord: ErrorRecord): void {
    errorHistory.value.unshift(errorRecord);
    if (errorHistory.value.length > maxHistorySize) {
        errorHistory.value.pop();
    }
}

function determineSeverity(error: Error): string {
    if (error instanceof KitchenApiError) {
        if (error.isNetworkError()) return ERROR_SEVERITY.WARNING;
        if (error.isAuthError()) return ERROR_SEVERITY.ERROR;
        if (error.isDeviceError()) return ERROR_SEVERITY.ERROR;
        if ((error.status ?? 0) >= 500) return ERROR_SEVERITY.CRITICAL;
    }
    return ERROR_SEVERITY.ERROR;
}

export function useErrorHandler() {
    function handleError(error: Error, options: ErrorHandlerOptions = {}): ErrorRecord {
        const {
            context = 'unknown',
            silent = false,
            rethrow = false,
        } = options;

        const severity = determineSeverity(error);
        const timestamp = Date.now();

        const errorRecord: ErrorRecord = {
            id: `${timestamp}-${Math.random().toString(36).substr(2, 9)}`,
            timestamp,
            context,
            severity,
            message: error instanceof KitchenApiError
                ? error.getUserMessage()
                : error.message || 'Unknown error',
            code: error instanceof KitchenApiError ? error.code : null,
            stack: error.stack,
            retryable: error instanceof KitchenApiError && error.isRetryable(),
        };

        lastError.value = errorRecord;
        addToHistory(errorRecord);
        logError(error, context, severity);

        if (rethrow) {
            throw error;
        }

        return errorRecord;
    }

    async function withErrorHandling<T>(fn: () => Promise<T>, options: ErrorHandlerOptions = {}): Promise<T | null> {
        try {
            return await fn();
        } catch (error: any) {
            handleError(error as Error, options);
            return null;
        }
    }

    async function withRetry<T>(fn: () => Promise<T>, options: RetryOptions = {}): Promise<T> {
        const {
            maxRetries = config.maxRetries,
            delay = config.retryDelay,
            context = 'retry',
        } = options;

        let lastErr: Error | null = null;

        for (let attempt = 1; attempt <= maxRetries; attempt++) {
            try {
                return await fn();
            } catch (error: any) {
                lastErr = error as Error;

                if (error instanceof KitchenApiError && !error.isRetryable()) {
                    throw error;
                }

                if (config.logToConsole && attempt < maxRetries) {
                    log.warn(`[${context}] Attempt ${attempt}/${maxRetries} failed, retrying in ${delay}ms...`);
                }

                if (attempt < maxRetries) {
                    await new Promise(resolve => setTimeout(resolve, delay * attempt));
                }
            }
        }

        handleError(lastErr!, { context: `${context} (after ${maxRetries} retries)` });
        throw lastErr;
    }

    function clearLastError(): void {
        lastError.value = null;
    }

    function clearHistory(): void {
        errorHistory.value = [];
    }

    function getErrorsBySeverity(severity: string): ErrorRecord[] {
        return errorHistory.value.filter((e: any) => e.severity === severity);
    }

    return {
        lastError: readonly(lastError),
        errorHistory: readonly(errorHistory),
        handleError,
        withErrorHandling,
        withRetry,
        clearLastError,
        clearHistory,
        getErrorsBySeverity,
        ERROR_SEVERITY,
    };
}

export function installGlobalErrorHandlers(): void {
    const { handleError } = useErrorHandler();

    window.addEventListener('unhandledrejection', (event) => {
        handleError(event.reason || new Error('Unhandled promise rejection'), {
            context: 'unhandledrejection',
        });
    });

    window.addEventListener('error', (event) => {
        handleError(event.error || new Error(event.message), {
            context: 'global',
        });
    });
}
