/**
 * Centralized Authentication Service
 *
 * Unified auth management for all MenuLab applications.
 * Серверный токен (Sanctum, 7 дней) — единственный источник истины по экспирации.
 *
 * @module shared/services/auth
 */

// Storage keys
const AUTH_KEY = 'menulab_auth';
const SESSION_KEY = 'menulab_session'; // Legacy POS key

// Legacy keys for backwards compatibility
const LEGACY_KEYS = {
    pos: 'menulab_session',
    backoffice: 'backoffice_token',
    admin: 'admin_token',
    courier: 'courier_token',
    cabinet: 'cabinet_token',
    waiter: 'api_token',
    kitchen: 'backoffice_token',
};

/**
 * Get current auth session
 * @returns {Object|null} Session object or null
 */
export function getSession() {
    try {
        // Try new unified key first
        const auth = localStorage.getItem(AUTH_KEY);
        if (auth) {
            const session = JSON.parse(auth);
            if (session?.token) {
                return session;
            }
        }

        // Fallback to legacy POS session key
        const legacySession = localStorage.getItem(SESSION_KEY);
        if (legacySession) {
            const session = JSON.parse(legacySession);
            if (session?.token) {
                return session;
            }
        }

        return null;
    } catch {
        return null;
    }
}

/**
 * Get auth token
 * @returns {string|null} Token or null
 */
export function getToken() {
    const session = getSession();
    return session?.token || null;
}

/**
 * Get current user
 * @returns {Object|null} User object or null
 */
export function getUser() {
    const session = getSession();
    return session?.user || null;
}

/**
 * Check if user is authenticated
 * @returns {boolean}
 */
export function isAuthenticated() {
    return !!getToken();
}

/**
 * Set auth session
 * @param {Object} data - Session data {token, user, permissions, ...}
 * @param {Object} options - Options {app}
 */
export function setSession(data, options = {}) {
    const { app = 'pos' } = options;

    const session = {
        token: data.token,
        user: data.user,
        permissions: data.permissions || [],
        limits: data.limits || {},
        interfaceAccess: data.interfaceAccess || data.interface_access || {},
        posModules: data.posModules || data.pos_modules || [],
        backofficeModules: data.backofficeModules || data.backoffice_modules || [],
        app,
        loginAt: Date.now(),
        version: 2,
    };

    // Save to unified key
    localStorage.setItem(AUTH_KEY, JSON.stringify(session));

    // Also save to legacy key for backwards compatibility (only POS uses menulab_session)
    if (app === 'pos') {
        localStorage.setItem(SESSION_KEY, JSON.stringify(session));
    }

    return session;
}

/**
 * Set just the token (for simple apps)
 * @param {string} token
 * @param {Object} user
 * @param {string} app
 */
export function setToken(token, user = null, app = 'unknown') {
    setSession({ token, user }, { app });
}

/**
 * Clear auth session (logout)
 */
export function clearAuth() {
    // Remove unified key
    localStorage.removeItem(AUTH_KEY);

    // Remove legacy keys
    Object.values(LEGACY_KEYS).forEach(key => {
        localStorage.removeItem(key);
    });
}

/**
 * Get Authorization header value
 * @returns {string|null} "Bearer {token}" or null
 */
export function getAuthHeader() {
    const token = getToken();
    return token ? `Bearer ${token}` : null;
}

/**
 * Get headers object with Authorization
 * @param {Object} additionalHeaders - Additional headers to merge
 * @returns {Object} Headers object
 */
export function getAuthHeaders(additionalHeaders = {}) {
    const headers = { ...additionalHeaders };
    const authHeader = getAuthHeader();
    if (authHeader) {
        headers['Authorization'] = authHeader;
    }
    return headers;
}

/**
 * Authenticated fetch wrapper
 * @param {string} url
 * @param {Object} options
 * @returns {Promise<Response>}
 */
export async function authFetch(url, options = {}) {
    const headers = getAuthHeaders(options.headers || {});
    return fetch(url, { ...options, headers });
}

/**
 * Migrate from legacy storage to unified storage
 * Call this on app init to migrate old sessions
 */
export function migrateFromLegacy() {
    // Check if already migrated
    if (localStorage.getItem(AUTH_KEY)) {
        return;
    }

    // Try to migrate from legacy keys
    for (const [app, key] of Object.entries(LEGACY_KEYS)) {
        try {
            const value = localStorage.getItem(key);
            if (!value) continue;

            // Check if it's a JSON session or plain token
            if (value.startsWith('{')) {
                const session = JSON.parse(value);
                if (session?.token) {
                    session.app = app;
                    session.version = 2;
                    localStorage.setItem(AUTH_KEY, JSON.stringify(session));
                    return;
                }
            } else {
                // Plain token string
                const session = {
                    token: value,
                    app,
                    loginAt: Date.now(),
                    version: 2,
                };
                localStorage.setItem(AUTH_KEY, JSON.stringify(session));
                return;
            }
        } catch {
            // Ignore parsing errors
        }
    }
}

// Export default object for convenient imports
export default {
    getSession,
    getToken,
    getUser,
    isAuthenticated,
    setSession,
    setToken,
    clearAuth,
    getAuthHeader,
    getAuthHeaders,
    authFetch,
    migrateFromLegacy,
};
