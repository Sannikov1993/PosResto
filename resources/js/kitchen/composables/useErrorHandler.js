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

const log = createLogger('ErrorHandler');

/**
 * Error severity levels
 */
export const ERROR_SEVERITY = {
    INFO: 'info',
    WARNING: 'warning',
    ERROR: 'error',
    CRITICAL: 'critical',
};

/**
 * Global error state
 */
const lastError = ref(null);
const errorHistory = ref([]);
const maxHistorySize = 50;

/**
 * Error handler configuration
 */
const config = {
    logToConsole: true,
    maxRetries: 3,
    retryDelay: 1000,
};

/**
 * Log error to console
 * @param {Error} error - Error to log
 * @param {string} context - Error context
 * @param {string} severity - Error severity
 */
function logError(error, context, severity) {
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

/**
 * Add error to history
 * @param {Object} errorRecord - Error record
 */
function addToHistory(errorRecord) {
    errorHistory.value.unshift(errorRecord);
    if (errorHistory.value.length > maxHistorySize) {
        errorHistory.value.pop();
    }
}

/**
 * Determine error severity
 * @param {Error} error - Error to analyze
 * @returns {string} Severity level
 */
function determineSeverity(error) {
    if (error instanceof KitchenApiError) {
        if (error.isNetworkError()) return ERROR_SEVERITY.WARNING;
        if (error.isAuthError()) return ERROR_SEVERITY.ERROR;
        if (error.isDeviceError()) return ERROR_SEVERITY.ERROR;
        if (error.status >= 500) return ERROR_SEVERITY.CRITICAL;
    }
    return ERROR_SEVERITY.ERROR;
}

/**
 * Global error handler composable
 * @returns {Object} Error handler API
 */
export function useErrorHandler() {
    /**
     * Handle an error
     * @param {Error} error - Error to handle
     * @param {Object} options - Handler options
     * @param {string} [options.context='unknown'] - Error context
     * @param {boolean} [options.silent=false] - Suppress UI notification
     * @param {boolean} [options.rethrow=false] - Re-throw after handling
     * @returns {Object} Error info
     */
    function handleError(error, options = {}) {
        const {
            context = 'unknown',
            silent = false,
            rethrow = false,
        } = options;

        const severity = determineSeverity(error);
        const timestamp = Date.now();

        // Create error record
        const errorRecord = {
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

        // Update state
        lastError.value = errorRecord;
        addToHistory(errorRecord);

        // Log error
        logError(error, context, severity);

        // Re-throw if requested
        if (rethrow) {
            throw error;
        }

        return errorRecord;
    }

    /**
     * Execute async function with error handling
     * @param {Function} fn - Async function to execute
     * @param {Object} options - Handler options
     * @returns {Promise<*>} Function result or null on error
     */
    async function withErrorHandling(fn, options = {}) {
        try {
            return await fn();
        } catch (error) {
            handleError(error, options);
            return null;
        }
    }

    /**
     * Execute async function with retry logic
     * @param {Function} fn - Async function to execute
     * @param {Object} options - Retry options
     * @param {number} [options.maxRetries=3] - Max retry attempts
     * @param {number} [options.delay=1000] - Delay between retries in ms
     * @param {string} [options.context='retry'] - Error context
     * @returns {Promise<*>} Function result
     */
    async function withRetry(fn, options = {}) {
        const {
            maxRetries = config.maxRetries,
            delay = config.retryDelay,
            context = 'retry',
        } = options;

        let lastError = null;

        for (let attempt = 1; attempt <= maxRetries; attempt++) {
            try {
                return await fn();
            } catch (error) {
                lastError = error;

                // Don't retry non-retryable errors
                if (error instanceof KitchenApiError && !error.isRetryable()) {
                    throw error;
                }

                // Log retry attempt
                if (config.logToConsole && attempt < maxRetries) {
                    log.warn(`[${context}] Attempt ${attempt}/${maxRetries} failed, retrying in ${delay}ms...`);
                }

                // Wait before retry
                if (attempt < maxRetries) {
                    await new Promise(resolve => setTimeout(resolve, delay * attempt));
                }
            }
        }

        // All retries failed
        handleError(lastError, { context: `${context} (after ${maxRetries} retries)` });
        throw lastError;
    }

    /**
     * Clear last error
     */
    function clearLastError() {
        lastError.value = null;
    }

    /**
     * Clear error history
     */
    function clearHistory() {
        errorHistory.value = [];
    }

    /**
     * Get errors by severity
     * @param {string} severity - Severity level
     * @returns {Array} Filtered errors
     */
    function getErrorsBySeverity(severity) {
        return errorHistory.value.filter(e => e.severity === severity);
    }

    return {
        // State
        lastError: readonly(lastError),
        errorHistory: readonly(errorHistory),

        // Methods
        handleError,
        withErrorHandling,
        withRetry,
        clearLastError,
        clearHistory,
        getErrorsBySeverity,

        // Constants
        ERROR_SEVERITY,
    };
}

/**
 * Install global error handlers (call once in app setup)
 */
export function installGlobalErrorHandlers() {
    const { handleError } = useErrorHandler();

    // Handle unhandled promise rejections
    window.addEventListener('unhandledrejection', (event) => {
        handleError(event.reason || new Error('Unhandled promise rejection'), {
            context: 'unhandledrejection',
        });
    });

    // Handle global errors
    window.addEventListener('error', (event) => {
        handleError(event.error || new Error(event.message), {
            context: 'global',
        });
    });
}
