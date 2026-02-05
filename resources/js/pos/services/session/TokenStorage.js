/**
 * TokenStorage - Secure token storage with dual-layer caching
 *
 * Implements a two-layer storage strategy:
 * 1. Memory cache (fast, volatile) - primary read source
 * 2. localStorage (persistent) - backup and cross-tab sync
 *
 * Features:
 * - Automatic memory/storage synchronization
 * - Data integrity validation
 * - Corruption detection and recovery
 * - Secure data handling
 *
 * @module services/session/TokenStorage
 */

import { STORAGE_KEYS, DEFAULT_SESSION } from './constants.js';

/**
 * Current storage schema version
 */
const SCHEMA_VERSION = 1;

/**
 * TokenStorage class for managing session data persistence
 */
export class TokenStorage {
    /**
     * Creates a new TokenStorage instance
     * @param {Object} options - Configuration options
     * @param {string} options.storageKey - localStorage key for session data
     * @param {boolean} options.debug - Enable debug logging
     */
    constructor(options = {}) {
        this._storageKey = options.storageKey || STORAGE_KEYS.SESSION;
        this._metaKey = options.metaKey || STORAGE_KEYS.SESSION_META;
        this._debug = options.debug || false;

        // Memory cache (primary read source)
        this._cache = null;
        this._cacheTimestamp = null;

        // Debounced persist timer for recordActivity
        this._activityPersistTimer = null;

        // Storage availability flags
        this._localStorageAvailable = this._checkLocalStorage();

        // Initialize from storage
        this._initializeFromStorage();
    }

    /**
     * Check if localStorage is available
     * @private
     * @returns {boolean}
     */
    _checkLocalStorage() {
        try {
            const testKey = '__storage_test__';
            localStorage.setItem(testKey, 'test');
            localStorage.removeItem(testKey);
            return true;
        } catch (e) {
            console.warn('[TokenStorage] localStorage not available:', e.message);
            return false;
        }
    }

    /**
     * Initialize cache from storage on startup
     * @private
     */
    _initializeFromStorage() {
        if (!this._localStorageAvailable) {
            return;
        }

        try {
            const stored = localStorage.getItem(this._storageKey);
            if (!stored) {
                return;
            }

            const parsed = JSON.parse(stored);

            // Validate schema version
            if (parsed.version !== SCHEMA_VERSION) {
                this._log('Schema version mismatch, migrating...');
                const migrated = this._migrateSchema(parsed);
                if (migrated) {
                    this._cache = migrated;
                    this._cacheTimestamp = Date.now();
                    this._persistToStorage();
                }
                return;
            }

            // Validate data integrity
            if (!this._validateSessionData(parsed)) {
                this._log('Invalid session data found, clearing...');
                this.clear();
                return;
            }

            this._cache = parsed;
            this._cacheTimestamp = Date.now();
            this._log('Initialized from storage');
        } catch (error) {
            console.error('[TokenStorage] Failed to initialize from storage:', error);
            this.clear();
        }
    }

    /**
     * Migrate session data from old schema versions
     * @private
     * @param {Object} data - Old session data
     * @returns {Object|null} Migrated data or null if migration failed
     */
    _migrateSchema(data) {
        try {
            // Handle pre-versioned data (version undefined or 0)
            if (!data.version || data.version < 1) {
                return {
                    ...DEFAULT_SESSION,
                    user: data.user || null,
                    token: data.token || null,
                    permissions: data.permissions || [],
                    limits: data.limits || DEFAULT_SESSION.limits,
                    interfaceAccess: data.interfaceAccess || DEFAULT_SESSION.interfaceAccess,
                    loginAt: data.loginAt || Date.now(),
                    lastActivity: data.lastActivity || Date.now(),
                    lastValidation: null,
                    lastExtension: null,
                    expiresAt: data.expiresAt || null,
                    version: SCHEMA_VERSION,
                };
            }

            // Future migrations can be added here

            return null;
        } catch (error) {
            console.error('[TokenStorage] Schema migration failed:', error);
            return null;
        }
    }

    /**
     * Validate session data structure
     * @private
     * @param {Object} data - Session data to validate
     * @returns {boolean} Whether the data is valid
     */
    _validateSessionData(data) {
        if (!data || typeof data !== 'object') {
            return false;
        }

        // Required fields check
        const requiredFields = ['token', 'user', 'expiresAt'];
        for (const field of requiredFields) {
            if (data[field] === undefined) {
                this._log(`Missing required field: ${field}`);
                return false;
            }
        }

        // Token format validation — должен быть непустой строкой
        if (data.token && typeof data.token !== 'string') {
            this._log('Invalid token type');
            return false;
        }

        // User validation
        if (data.user && typeof data.user === 'object') {
            if (!data.user.id || !data.user.name) {
                this._log('Invalid user data');
                return false;
            }
        }

        // Expiration validation
        if (data.expiresAt && typeof data.expiresAt !== 'number') {
            this._log('Invalid expiresAt format');
            return false;
        }

        return true;
    }

    /**
     * Get current session data
     * @returns {Object|null} Session data or null if not exists
     */
    get() {
        // Return from memory cache if available
        if (this._cache) {
            return this._deepClone(this._cache);
        }

        return null;
    }

    /**
     * Get specific field from session
     * @param {string} field - Field name (supports dot notation)
     * @returns {*} Field value or undefined
     */
    getField(field) {
        const session = this.get();
        if (!session) {
            return undefined;
        }

        // Support dot notation (e.g., 'user.id')
        return field.split('.').reduce((obj, key) => {
            return obj && obj[key] !== undefined ? obj[key] : undefined;
        }, session);
    }

    /**
     * Get the authentication token
     * @returns {string|null} Token or null
     */
    getToken() {
        return this._cache?.token || null;
    }

    /**
     * Get the user data
     * @returns {Object|null} User data or null
     */
    getUser() {
        return this._cache?.user ? this._deepClone(this._cache.user) : null;
    }

    /**
     * Check if a valid session exists
     * @returns {boolean}
     */
    hasSession() {
        return this._cache !== null && this._cache.token !== null;
    }

    /**
     * Check if session is expired (client-side check)
     * @returns {boolean}
     */
    isExpired() {
        if (!this._cache || !this._cache.expiresAt) {
            return true;
        }

        return Date.now() > this._cache.expiresAt;
    }

    /**
     * Get time until expiration
     * @returns {number} Milliseconds until expiration (negative if expired)
     */
    getTimeUntilExpiry() {
        if (!this._cache || !this._cache.expiresAt) {
            return -Infinity;
        }

        return this._cache.expiresAt - Date.now();
    }

    /**
     * Save session data
     * @param {Object} data - Session data to save
     * @returns {boolean} Whether save was successful
     */
    save(data) {
        if (!data || typeof data !== 'object') {
            console.error('[TokenStorage] Invalid data provided to save()');
            return false;
        }

        try {
            // Merge with defaults and add metadata
            const sessionData = {
                ...DEFAULT_SESSION,
                ...data,
                version: SCHEMA_VERSION,
            };

            // Validate before saving
            if (!this._validateSessionData(sessionData)) {
                console.error('[TokenStorage] Validation failed for session data');
                return false;
            }

            // Update memory cache
            this._cache = sessionData;
            this._cacheTimestamp = Date.now();

            // Persist to storage
            this._persistToStorage();

            // Save metadata
            this._saveMetadata();

            this._log('Session saved successfully');
            return true;
        } catch (error) {
            console.error('[TokenStorage] Failed to save session:', error);
            return false;
        }
    }

    /**
     * Update specific fields in session
     * @param {Object} updates - Fields to update
     * @returns {boolean} Whether update was successful
     */
    update(updates) {
        if (!this._cache) {
            console.error('[TokenStorage] No session to update');
            return false;
        }

        try {
            // Merge updates
            const updatedSession = {
                ...this._cache,
                ...updates,
            };

            // Validate
            if (!this._validateSessionData(updatedSession)) {
                console.error('[TokenStorage] Validation failed after update');
                return false;
            }

            // Update cache
            this._cache = updatedSession;
            this._cacheTimestamp = Date.now();

            // Persist
            this._persistToStorage();

            this._log('Session updated successfully');
            return true;
        } catch (error) {
            console.error('[TokenStorage] Failed to update session:', error);
            return false;
        }
    }

    /**
     * Extend session expiration
     * @param {number} extensionMs - Extension time in milliseconds
     * @returns {boolean} Whether extension was successful
     */
    extendExpiration(extensionMs) {
        if (!this._cache) {
            return false;
        }

        const now = Date.now();
        const newExpiry = now + extensionMs;

        return this.update({
            expiresAt: newExpiry,
            lastExtension: now,
            lastActivity: now,
        });
    }

    /**
     * Record activity (updates lastActivity timestamp)
     * @returns {boolean} Whether update was successful
     */
    recordActivity() {
        if (!this._cache) {
            return false;
        }

        // Update in memory immediately
        this._cache.lastActivity = Date.now();

        // Debounced persist to localStorage (every 30 seconds)
        if (!this._activityPersistTimer) {
            this._activityPersistTimer = setTimeout(() => {
                this._activityPersistTimer = null;
                this._persistToStorage();
            }, 30000);
        }

        return true;
    }

    /**
     * Clear all session data
     */
    clear() {
        this._cache = null;
        this._cacheTimestamp = null;

        if (this._localStorageAvailable) {
            try {
                localStorage.removeItem(this._storageKey);
                localStorage.removeItem(this._metaKey);
            } catch (error) {
                console.error('[TokenStorage] Failed to clear storage:', error);
            }
        }

        this._log('Session cleared');
    }

    /**
     * Persist current cache to localStorage
     * @private
     */
    _persistToStorage() {
        if (!this._localStorageAvailable || !this._cache) {
            return;
        }

        try {
            const serialized = JSON.stringify(this._cache);
            localStorage.setItem(this._storageKey, serialized);
        } catch (error) {
            // Handle quota exceeded
            if (error.name === 'QuotaExceededError') {
                console.error('[TokenStorage] Storage quota exceeded');
                this._handleQuotaExceeded();
            } else {
                console.error('[TokenStorage] Failed to persist to storage:', error);
            }
        }
    }

    /**
     * Handle storage quota exceeded
     * @private
     */
    _handleQuotaExceeded() {
        // Try to clear non-essential data from localStorage
        const keysToPreserve = [this._storageKey, STORAGE_KEYS.RESTAURANT_ID];

        for (let i = localStorage.length - 1; i >= 0; i--) {
            const key = localStorage.key(i);
            if (key && !keysToPreserve.includes(key) && key.startsWith('menulab_')) {
                try {
                    localStorage.removeItem(key);
                } catch (e) {
                    // Ignore
                }
            }
        }

        // Retry persistence
        try {
            const serialized = JSON.stringify(this._cache);
            localStorage.setItem(this._storageKey, serialized);
        } catch (e) {
            console.error('[TokenStorage] Still unable to persist after cleanup');
        }
    }

    /**
     * Save session metadata for debugging
     * @private
     */
    _saveMetadata() {
        if (!this._localStorageAvailable) {
            return;
        }

        try {
            const meta = {
                lastSave: Date.now(),
                userAgent: navigator.userAgent,
                screenSize: `${window.innerWidth}x${window.innerHeight}`,
            };
            localStorage.setItem(this._metaKey, JSON.stringify(meta));
        } catch (error) {
            // Non-critical, ignore
        }
    }

    /**
     * Sync cache from storage (for cross-tab updates)
     * @returns {boolean} Whether sync found newer data
     */
    syncFromStorage() {
        if (!this._localStorageAvailable) {
            return false;
        }

        try {
            const stored = localStorage.getItem(this._storageKey);
            if (!stored) {
                // Storage cleared, clear cache too
                if (this._cache) {
                    this._cache = null;
                    this._cacheTimestamp = null;
                    return true;
                }
                return false;
            }

            const parsed = JSON.parse(stored);

            // Check if storage data is newer
            const storageActivity = parsed.lastActivity || 0;
            const cacheActivity = this._cache?.lastActivity || 0;

            if (storageActivity > cacheActivity) {
                if (this._validateSessionData(parsed)) {
                    this._cache = parsed;
                    this._cacheTimestamp = Date.now();
                    this._log('Synced newer data from storage');
                    return true;
                }
            }

            return false;
        } catch (error) {
            console.error('[TokenStorage] Failed to sync from storage:', error);
            return false;
        }
    }

    /**
     * Export session data (for backup/transfer)
     * @returns {string} JSON string of session data (sanitized)
     */
    export() {
        if (!this._cache) {
            return null;
        }

        // Create sanitized copy (remove token)
        const sanitized = {
            ...this._cache,
            token: '[EXPORTED]',
        };

        return JSON.stringify(sanitized);
    }

    /**
     * Get storage statistics
     * @returns {Object} Storage statistics
     */
    getStats() {
        const sessionSize = this._cache
            ? new Blob([JSON.stringify(this._cache)]).size
            : 0;

        return {
            hasSession: this.hasSession(),
            isExpired: this.isExpired(),
            timeUntilExpiry: this.getTimeUntilExpiry(),
            cacheTimestamp: this._cacheTimestamp,
            sessionSize,
            localStorageAvailable: this._localStorageAvailable,
        };
    }

    /**
     * Deep clone an object
     * @private
     * @param {Object} obj - Object to clone
     * @returns {Object} Cloned object
     */
    _deepClone(obj) {
        if (obj === null || typeof obj !== 'object') {
            return obj;
        }

        try {
            return JSON.parse(JSON.stringify(obj));
        } catch (e) {
            return { ...obj };
        }
    }

    /**
     * Debug logging
     * @private
     */
    _log(message, data) {
        if (this._debug) {
            if (data) {
                console.debug(`[TokenStorage] ${message}`, data);
            } else {
                console.debug(`[TokenStorage] ${message}`);
            }
        }
    }

    /**
     * Destroy the storage instance
     */
    destroy() {
        if (this._activityPersistTimer) {
            clearTimeout(this._activityPersistTimer);
            this._activityPersistTimer = null;
        }
        this._cache = null;
        this._cacheTimestamp = null;
        this._log('TokenStorage destroyed');
    }
}

// Export factory function
export function createTokenStorage(options = {}) {
    return new TokenStorage(options);
}

export default TokenStorage;
