/**
 * SessionManager - Enterprise-grade session management
 *
 * Orchestrates all session-related functionality:
 * - Token storage and persistence
 * - Session lifecycle management
 * - Network resilience with retry logic
 * - Cross-tab synchronization
 * - Activity tracking and session extension
 * - Expiration warnings and handling
 *
 * This is the main entry point for session management.
 *
 * @module services/session/SessionManager
 */

import { TokenStorage } from './TokenStorage.js';
import { NetworkRetry, NetworkRetryError } from './NetworkRetry.js';
import { TabSync } from './TabSync.js';
import { EventEmitter, getSessionEventEmitter } from './EventEmitter.js';
import {
    SESSION_TIMING,
    SESSION_STATES,
    SESSION_EVENTS,
    VALIDATION_ERRORS,
    STORAGE_KEYS,
} from './constants.js';
import axios from 'axios';

/**
 * API endpoints
 */
const API_ENDPOINTS = {
    CHECK_AUTH: '/api/auth/check',
    LOGOUT: '/api/auth/logout',
};

/**
 * SessionManager class - Main session orchestrator
 */
export class SessionManager {
    /**
     * Creates a new SessionManager instance
     * @param {Object} options - Configuration options
     */
    constructor(options = {}) {
        this._debug = options.debug || false;

        // Initialize components
        this._storage = new TokenStorage({ debug: this._debug });
        this._networkRetry = new NetworkRetry({ debug: this._debug });
        this._tabSync = new TabSync({ debug: this._debug });
        this._events = options.eventEmitter || getSessionEventEmitter();

        // State
        this._state = SESSION_STATES.NONE;
        this._validating = false;
        this._lastValidation = null;
        this._lastExtension = null;
        this._lastActivity = null;

        // Timers
        this._validationIntervalId = null;
        this._expirationCheckIntervalId = null;
        this._activityDebounceTimer = null;

        // Callbacks (for Pinia store integration)
        this._onStateChange = options.onStateChange || null;
        this._onSessionExpired = options.onSessionExpired || null;
        this._onSessionWarning = options.onSessionWarning || null;

        // Initialize
        this._initialize();
    }

    /**
     * Initialize the session manager
     * @private
     */
    _initialize() {
        // Setup tab sync handlers
        this._setupTabSyncHandlers();

        // Setup activity tracking
        this._setupActivityTracking();

        // Check initial state
        if (this._storage.hasSession()) {
            if (this._storage.isExpired()) {
                this._setState(SESSION_STATES.EXPIRED);
            } else {
                this._setState(SESSION_STATES.ACTIVE);
            }
        } else {
            this._setState(SESSION_STATES.NONE);
        }

        this._log('SessionManager initialized', { state: this._state });
    }

    /**
     * Setup tab synchronization handlers
     * @private
     */
    _setupTabSyncHandlers() {
        // Handle session updates from other tabs
        this._tabSync.on('sessionUpdate', (data) => {
            this._log('Session update from another tab');
            this._storage.syncFromStorage();
            this._events.emit(SESSION_EVENTS.TAB_SYNCED, data);
        });

        // Handle logout from other tabs
        this._tabSync.on('logout', (data) => {
            this._log('Logout from another tab', data);
            this._handleRemoteLogout(data);
        });

        // Handle token refresh from leader tab
        this._tabSync.on('tokenRefresh', (data) => {
            this._log('Token refresh from leader tab');
            this._storage.syncFromStorage();
        });

        // Handle activity from other tabs
        this._tabSync.on('activity', () => {
            // Update last activity in storage
            this._storage.syncFromStorage();
        });

        // Handle tab becoming visible — sync session from localStorage
        this._tabSync.on('visible', () => {
            this._log('Tab became visible, syncing from storage');
            const synced = this._storage.syncFromStorage();
            if (synced) {
                // Re-evaluate session state after sync
                if (this._storage.hasSession()) {
                    if (this._storage.isExpired()) {
                        this._handleExpiration();
                    } else {
                        this._setState(SESSION_STATES.ACTIVE);
                    }
                } else {
                    this._handleRemoteLogout({ reason: 'storage_cleared' });
                }
            }
        });

        // Handle becoming leader
        this._tabSync.on('leader', () => {
            this._log('This tab is now the leader');
            // Leader handles token refresh
            this._startValidationInterval();
        });

        // Handle losing leadership
        this._tabSync.on('leaderLost', () => {
            this._log('Lost leadership to another tab');
            this._stopValidationInterval();
        });
    }

    /**
     * Setup activity tracking
     * @private
     */
    _setupActivityTracking() {
        if (typeof window === 'undefined') {
            return;
        }

        const activityEvents = ['mousedown', 'keydown', 'touchstart', 'scroll'];

        const handleActivity = () => {
            // Debounce activity recording
            if (this._activityDebounceTimer) {
                return;
            }

            this._activityDebounceTimer = setTimeout(() => {
                this._activityDebounceTimer = null;
            }, SESSION_TIMING.ACTIVITY_DEBOUNCE);

            this._recordActivity();
        };

        activityEvents.forEach(event => {
            window.addEventListener(event, handleActivity, { passive: true });
        });

        // Start expiration check interval
        this._startExpirationCheck();
    }

    /**
     * Start periodic validation (leader only)
     * @private
     */
    _startValidationInterval() {
        if (this._validationIntervalId) {
            return;
        }

        this._validationIntervalId = setInterval(() => {
            if (this._state === SESSION_STATES.ACTIVE && this._tabSync.isLeader()) {
                this._validateSession(true); // Silent validation
            }
        }, SESSION_TIMING.VALIDATION_INTERVAL);
    }

    /**
     * Stop validation interval
     * @private
     */
    _stopValidationInterval() {
        if (this._validationIntervalId) {
            clearInterval(this._validationIntervalId);
            this._validationIntervalId = null;
        }
    }

    /**
     * Start expiration check interval
     * @private
     */
    _startExpirationCheck() {
        if (this._expirationCheckIntervalId) {
            return;
        }

        this._expirationCheckIntervalId = setInterval(() => {
            this._checkExpiration();
        }, 60000); // Check every minute
    }

    /**
     * Stop expiration check interval
     * @private
     */
    _stopExpirationCheck() {
        if (this._expirationCheckIntervalId) {
            clearInterval(this._expirationCheckIntervalId);
            this._expirationCheckIntervalId = null;
        }
    }

    /**
     * Check session expiration and emit warnings
     * @private
     */
    _checkExpiration() {
        if (this._state !== SESSION_STATES.ACTIVE && this._state !== SESSION_STATES.EXPIRING_SOON) {
            return;
        }

        const timeUntilExpiry = this._storage.getTimeUntilExpiry();

        // Session expired
        if (timeUntilExpiry <= 0) {
            this._handleExpiration();
            return;
        }

        // Critical warning (5 minutes)
        if (timeUntilExpiry <= SESSION_TIMING.EXPIRATION_CRITICAL) {
            this._setState(SESSION_STATES.EXPIRING_SOON);
            this._events.emit(SESSION_EVENTS.EXPIRING_SOON, {
                timeUntilExpiry,
                critical: true,
            });

            if (this._onSessionWarning) {
                this._onSessionWarning({
                    timeUntilExpiry,
                    critical: true,
                });
            }
            return;
        }

        // Warning (15 minutes)
        if (timeUntilExpiry <= SESSION_TIMING.EXPIRATION_WARNING) {
            this._events.emit(SESSION_EVENTS.EXPIRING_SOON, {
                timeUntilExpiry,
                critical: false,
            });

            if (this._onSessionWarning) {
                this._onSessionWarning({
                    timeUntilExpiry,
                    critical: false,
                });
            }
        }
    }

    /**
     * Handle session expiration
     * @private
     */
    _handleExpiration() {
        this._log('Session expired');
        this._stopValidationInterval();
        this._stopExpirationCheck();
        this._setState(SESSION_STATES.EXPIRED);
        this._events.emit(SESSION_EVENTS.EXPIRED);

        if (this._onSessionExpired) {
            this._onSessionExpired();
        }

        // Clear session data
        this._storage.clear();
        this._tabSync.broadcastLogout({ reason: 'expired' });
    }

    /**
     * Handle logout from another tab
     * @private
     */
    _handleRemoteLogout(data) {
        this._log('Remote logout', data);
        this._storage.clear();
        this._setState(SESSION_STATES.NONE);
        this._events.emit(SESSION_EVENTS.CLEARED, { remote: true, reason: data.reason });

        if (this._onSessionExpired) {
            this._onSessionExpired();
        }
    }

    /**
     * Record user activity
     * @private
     */
    _recordActivity() {
        if (this._state !== SESSION_STATES.ACTIVE && this._state !== SESSION_STATES.EXPIRING_SOON) {
            return;
        }

        const now = Date.now();
        this._lastActivity = now;
        this._storage.recordActivity();

        // Check if we should extend the session
        this._maybeExtendSession();

        // Broadcast activity to other tabs
        this._tabSync.broadcastActivity();

        this._events.emit(SESSION_EVENTS.ACTIVITY, { timestamp: now });
    }

    /**
     * Maybe extend session based on activity
     * @private
     */
    _maybeExtendSession() {
        const now = Date.now();

        // Throttle extension
        if (this._lastExtension &&
            now - this._lastExtension < SESSION_TIMING.EXTENSION_THROTTLE) {
            return;
        }

        // Extend session
        this._extendSession();
    }

    /**
     * Extend session expiration
     * @private
     */
    _extendSession() {
        const success = this._storage.extendExpiration(SESSION_TIMING.EXTENSION_PERIOD);

        if (success) {
            this._lastExtension = Date.now();
            this._events.emit(SESSION_EVENTS.EXTENDED, {
                expiresAt: this._storage.getField('expiresAt'),
            });

            // Sync to other tabs
            this._tabSync.broadcastSessionUpdate(this._storage.get());

            this._log('Session extended', {
                newExpiry: new Date(this._storage.getField('expiresAt')).toISOString(),
            });
        }
    }

    /**
     * Set session state
     * @private
     */
    _setState(newState) {
        if (this._state === newState) {
            return;
        }

        const oldState = this._state;
        this._state = newState;

        this._events.emit(SESSION_EVENTS.STATE_CHANGE, {
            oldState,
            newState,
        });

        if (this._onStateChange) {
            this._onStateChange(newState, oldState);
        }

        this._log(`State changed: ${oldState} -> ${newState}`);
    }

    // ==================== PUBLIC API ====================

    /**
     * Create a new session (login)
     * @param {Object} data - Session data from login response
     * @returns {boolean} Whether session was created successfully
     */
    createSession(data) {
        if (!data || !data.token || !data.user) {
            console.error('[SessionManager] Invalid session data');
            return false;
        }

        const now = Date.now();

        const sessionData = {
            user: data.user,
            token: data.token,
            permissions: data.permissions || [],
            limits: data.limits || {},
            interfaceAccess: data.interface_access || data.interfaceAccess || {},
            posModules: data.pos_modules || data.posModules || [],
            backofficeModules: data.backoffice_modules || data.backofficeModules || [],
            loginAt: now,
            lastActivity: now,
            lastValidation: now,
            lastExtension: now,
            expiresAt: now + SESSION_TIMING.MAX_LIFETIME,
        };

        const success = this._storage.save(sessionData);

        if (success) {
            this._setState(SESSION_STATES.ACTIVE);
            this._lastActivity = now;
            this._lastValidation = now;
            this._lastExtension = now;

            // Start validation if leader
            if (this._tabSync.isLeader()) {
                this._startValidationInterval();
            }

            // Notify other tabs
            this._tabSync.broadcastSessionUpdate(sessionData);

            this._events.emit(SESSION_EVENTS.CREATED, { user: data.user });
            this._log('Session created', { user: data.user.name });
        }

        return success;
    }

    /**
     * Restore session from storage
     * @returns {Promise<Object|null>} Session data or null if restore failed
     */
    async restoreSession() {
        this._setState(SESSION_STATES.INITIALIZING);

        // Check for existing session
        if (!this._storage.hasSession()) {
            this._log('No session to restore');
            this._setState(SESSION_STATES.NONE);
            return null;
        }

        // Check client-side expiration
        if (this._storage.isExpired()) {
            this._log('Session expired (client-side check)');
            this._storage.clear();
            this._setState(SESSION_STATES.EXPIRED);
            this._events.emit(SESSION_EVENTS.EXPIRED, {
                reason: VALIDATION_ERRORS.CLIENT_EXPIRED,
            });
            return null;
        }

        // Validate with server
        const validationResult = await this._validateSession(false);

        if (!validationResult.success) {
            // Серверные ошибки — не очищаем сессию, пробуем работать с кешем
            if (validationResult.reason === VALIDATION_ERRORS.SERVER_ERROR ||
                validationResult.reason === VALIDATION_ERRORS.NETWORK_ERROR) {
                this._log('Server/network error during restore, keeping cached session');
                this._setState(SESSION_STATES.ACTIVE);

                const session = this._storage.get();
                if (session) {
                    this._events.emit(SESSION_EVENTS.RESTORED, { user: session.user, offline: true });
                    return session;
                }
            }

            this._log('Session validation failed', validationResult);
            this._storage.clear();
            this._setState(SESSION_STATES.INVALID);
            this._events.emit(SESSION_EVENTS.VALIDATION_FAILED, {
                reason: validationResult.reason,
                error: validationResult.error,
            });
            return null;
        }

        // Session is valid - extend it
        this._extendSession();
        this._setState(SESSION_STATES.ACTIVE);

        // Start validation interval if leader
        if (this._tabSync.isLeader()) {
            this._startValidationInterval();
        }

        const session = this._storage.get();
        this._events.emit(SESSION_EVENTS.RESTORED, { user: session.user });
        this._log('Session restored', { user: session.user?.name });

        return session;
    }

    /**
     * Validate session with server
     * @private
     * @param {boolean} silent - Whether to suppress state changes
     * @returns {Promise<Object>} Validation result
     */
    async _validateSession(silent = false) {
        if (this._validating) {
            this._log('Validation already in progress');
            return { success: false, reason: 'validation_in_progress' };
        }

        const token = this._storage.getToken();
        if (!token) {
            return { success: false, reason: VALIDATION_ERRORS.NO_TOKEN };
        }

        this._validating = true;

        if (!silent) {
            this._setState(SESSION_STATES.VALIDATING);
        }

        try {
            const result = await this._networkRetry.execute(
                async () => {
                    const response = await axios.get(API_ENDPOINTS.CHECK_AUTH, {
                        headers: { Authorization: `Bearer ${token}` },
                        timeout: 15000,
                    });
                    return response.data;
                },
                {
                    maxAttempts: 3,
                    dedupeKey: 'session_validation',
                    onRetry: ({ attempt, delay }) => {
                        this._log(`Validation retry ${attempt}, waiting ${delay}ms`);
                    },
                }
            );

            if (result.success) {
                this._lastValidation = Date.now();

                // Update session with fresh data from server (all 3 access levels)
                this._storage.update({
                    user: result.data.user,
                    permissions: result.data.permissions,
                    limits: result.data.limits,
                    interfaceAccess: result.data.interface_access,
                    posModules: result.data.pos_modules,
                    backofficeModules: result.data.backoffice_modules,
                    lastValidation: this._lastValidation,
                });

                if (!silent) {
                    this._setState(SESSION_STATES.ACTIVE);
                }

                return { success: true };
            } else {
                return {
                    success: false,
                    reason: VALIDATION_ERRORS.TOKEN_REVOKED,
                    message: result.message,
                };
            }
        } catch (error) {
            this._log('Validation error', error);

            // Handle network errors differently from auth errors
            if (error instanceof NetworkRetryError) {
                if (error.isOffline() || error.code === 'ERR_NETWORK') {
                    // Network error - keep session, don't invalidate
                    this._events.emit(SESSION_EVENTS.NETWORK_ERROR, { error });

                    if (!silent) {
                        this._setState(SESSION_STATES.ACTIVE);
                    }

                    return {
                        success: true, // Consider valid - network is just down
                        networkError: true,
                        reason: VALIDATION_ERRORS.NETWORK_ERROR,
                    };
                }
            }

            // Check for 401 (unauthorized)
            const status = error.response?.status || error.getHttpStatus?.();
            if (status === 401) {
                return {
                    success: false,
                    reason: VALIDATION_ERRORS.TOKEN_EXPIRED,
                    error,
                };
            }

            // Серверные ошибки (500, 502, etc.) — сессия потенциально валидна, не инвалидируем
            const serverStatus = error.response?.status || error.getHttpStatus?.();
            if (serverStatus && serverStatus >= 500) {
                this._events.emit(SESSION_EVENTS.NETWORK_ERROR, { error });

                if (!silent) {
                    this._setState(SESSION_STATES.ACTIVE);
                }

                return {
                    success: true,
                    serverError: true,
                    reason: VALIDATION_ERRORS.SERVER_ERROR,
                };
            }

            // Другие ошибки — считаем невалидным
            return {
                success: false,
                reason: VALIDATION_ERRORS.SERVER_ERROR,
                error,
            };
        } finally {
            this._validating = false;
        }
    }

    /**
     * End session (logout)
     * @param {Object} options - Logout options
     */
    async logout(options = {}) {
        const { notifyServer = true, reason = 'user_logout' } = options;

        this._log('Logging out', { reason });

        // Stop timers
        this._stopValidationInterval();
        this._stopExpirationCheck();

        // Notify server
        if (notifyServer) {
            const token = this._storage.getToken();
            if (token) {
                try {
                    await axios.post(API_ENDPOINTS.LOGOUT, {}, {
                        headers: { Authorization: `Bearer ${token}` },
                    });
                } catch (error) {
                    // Ignore logout errors
                    this._log('Server logout failed (ignored)', error.message);
                }
            }
        }

        // Clear storage
        this._storage.clear();

        // Update state
        this._setState(SESSION_STATES.NONE);

        // Notify other tabs
        this._tabSync.broadcastLogout({ reason });

        // Emit event
        this._events.emit(SESSION_EVENTS.CLEARED, { reason });
    }

    /**
     * Get current session state
     * @returns {string} Current state
     */
    getState() {
        return this._state;
    }

    /**
     * Check if session is active
     * @returns {boolean}
     */
    isActive() {
        return this._state === SESSION_STATES.ACTIVE;
    }

    /**
     * Check if session exists (may or may not be validated)
     * @returns {boolean}
     */
    hasSession() {
        return this._storage.hasSession();
    }

    /**
     * Get current token
     * @returns {string|null}
     */
    getToken() {
        return this._storage.getToken();
    }

    /**
     * Get current user
     * @returns {Object|null}
     */
    getUser() {
        return this._storage.getUser();
    }

    /**
     * Get full session data
     * @returns {Object|null}
     */
    getSession() {
        return this._storage.get();
    }

    /**
     * Get session field
     * @param {string} field - Field name (supports dot notation)
     * @returns {*}
     */
    getField(field) {
        return this._storage.getField(field);
    }

    /**
     * Update session data (partial update)
     * @param {Object} updates - Fields to update
     * @returns {boolean}
     */
    updateSession(updates) {
        const success = this._storage.update(updates);

        if (success) {
            this._tabSync.broadcastSessionUpdate(this._storage.get());
        }

        return success;
    }

    /**
     * Get time until session expires
     * @returns {number} Milliseconds until expiration
     */
    getTimeUntilExpiry() {
        return this._storage.getTimeUntilExpiry();
    }

    /**
     * Manually extend session (e.g., user clicked "Stay logged in")
     * @returns {boolean}
     */
    extend() {
        const success = this._storage.extendExpiration(SESSION_TIMING.EXTENSION_PERIOD);

        if (success) {
            this._lastExtension = Date.now();

            // Сбрасываем состояние обратно в ACTIVE
            this._setState(SESSION_STATES.ACTIVE);

            this._events.emit(SESSION_EVENTS.EXTENDED, {
                expiresAt: this._storage.getField('expiresAt'),
            });

            // Синхронизируем с другими вкладками
            this._tabSync.broadcastSessionUpdate(this._storage.get());

            this._log('Session manually extended', {
                newExpiry: new Date(this._storage.getField('expiresAt')).toISOString(),
            });
        }

        return success;
    }

    /**
     * Force session validation
     * @returns {Promise<Object>} Validation result
     */
    async validate() {
        return this._validateSession(false);
    }

    /**
     * Subscribe to session events
     * @param {string} event - Event name
     * @param {Function} callback - Callback function
     * @returns {Function} Unsubscribe function
     */
    on(event, callback) {
        return this._events.on(event, callback);
    }

    /**
     * Subscribe to session events (once)
     * @param {string} event - Event name
     * @param {Function} callback - Callback function
     * @returns {Function} Unsubscribe function
     */
    once(event, callback) {
        return this._events.once(event, callback);
    }

    /**
     * Get comprehensive status
     * @returns {Object}
     */
    getStatus() {
        return {
            state: this._state,
            isActive: this.isActive(),
            hasSession: this.hasSession(),
            user: this.getUser(),
            timeUntilExpiry: this.getTimeUntilExpiry(),
            lastValidation: this._lastValidation,
            lastActivity: this._lastActivity,
            lastExtension: this._lastExtension,
            storage: this._storage.getStats(),
            network: this._networkRetry.getStatus(),
            tabSync: this._tabSync.getStatus(),
        };
    }

    /**
     * Debug logging
     * @private
     */
    _log(message, data) {
        if (this._debug) {
            if (data) {
                console.debug(`[SessionManager] ${message}`, data);
            } else {
                console.debug(`[SessionManager] ${message}`);
            }
        }
    }

    /**
     * Destroy the session manager
     */
    destroy() {
        this._stopValidationInterval();
        this._stopExpirationCheck();

        if (this._activityDebounceTimer) {
            clearTimeout(this._activityDebounceTimer);
        }

        this._storage.destroy();
        this._networkRetry.destroy();
        this._tabSync.destroy();

        this._log('SessionManager destroyed');
    }
}

// Singleton instance
let sessionManagerInstance = null;

/**
 * Get or create the singleton SessionManager instance
 * @param {Object} options - Configuration options
 * @returns {SessionManager}
 */
export function getSessionManager(options = {}) {
    if (!sessionManagerInstance) {
        sessionManagerInstance = new SessionManager(options);
    }
    return sessionManagerInstance;
}

/**
 * Reset the singleton (for testing)
 */
export function resetSessionManager() {
    if (sessionManagerInstance) {
        sessionManagerInstance.destroy();
        sessionManagerInstance = null;
    }
}

export default SessionManager;
