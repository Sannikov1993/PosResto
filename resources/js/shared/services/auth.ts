/**
 * Centralized Authentication Service
 *
 * Unified auth management for all MenuLab applications.
 *
 * @module shared/services/auth
 */

import { createLogger } from './logger.js';

const log = createLogger('Auth');

// Storage keys
const AUTH_KEY = 'menulab_auth';
const SESSION_KEY = 'menulab_session';

// Legacy keys for backwards compatibility
const LEGACY_KEYS: Record<string, string> = {
    pos: 'menulab_session',
    backoffice: 'backoffice_token',
    admin: 'admin_token',
    courier: 'courier_token',
    cabinet: 'cabinet_token',
    waiter: 'api_token',
    kitchen: 'backoffice_token',
};

export interface AuthSession {
    token: string;
    user: Record<string, any> | null;
    permissions: string[];
    limits: Record<string, number>;
    interfaceAccess: Record<string, boolean>;
    posModules: string[];
    backofficeModules: string[];
    app: string;
    loginAt: number;
    version: number;
}

export interface SetSessionData {
    token: string;
    user?: Record<string, any> | null;
    permissions?: string[];
    limits?: Record<string, number>;
    interfaceAccess?: Record<string, boolean>;
    interface_access?: Record<string, boolean>;
    posModules?: string[];
    pos_modules?: string[];
    backofficeModules?: string[];
    backoffice_modules?: string[];
}

export interface SetSessionOptions {
    app?: string;
}

export function getSession(): AuthSession | null {
    try {
        const auth = localStorage.getItem(AUTH_KEY);
        if (auth) {
            const session = JSON.parse(auth) as AuthSession;
            if (session?.token) {
                return session;
            }
        }

        const legacySession = localStorage.getItem(SESSION_KEY);
        if (legacySession) {
            const session = JSON.parse(legacySession) as AuthSession;
            if (session?.token) {
                return session;
            }
        }

        return null;
    } catch (error: any) {
        log.warn('Failed to parse auth session from storage:', error);
        return null;
    }
}

export function getToken(): string | null {
    const session = getSession();
    return session?.token || null;
}

export function getUser(): Record<string, any> | null {
    const session = getSession();
    return session?.user || null;
}

export function isAuthenticated(): boolean {
    return !!getToken();
}

export function setSession(data: SetSessionData, options: SetSessionOptions = {}): AuthSession {
    const { app = 'pos' } = options;

    const session: AuthSession = {
        token: data.token,
        user: data.user || null,
        permissions: data.permissions || [],
        limits: data.limits || {},
        interfaceAccess: data.interfaceAccess || data.interface_access || {},
        posModules: data.posModules || data.pos_modules || [],
        backofficeModules: data.backofficeModules || data.backoffice_modules || [],
        app,
        loginAt: Date.now(),
        version: 2,
    };

    try {
        localStorage.setItem(AUTH_KEY, JSON.stringify(session));

        if (app === 'pos') {
            localStorage.setItem(SESSION_KEY, JSON.stringify(session));
        }
    } catch (error: any) {
        log.error('Failed to save auth session to storage (quota exceeded?):', error);
    }

    return session;
}

export function setToken(token: string, user: Record<string, any> | null = null, app: string = 'unknown'): void {
    setSession({ token, user }, { app });
}

export function clearAuth(): void {
    localStorage.removeItem(AUTH_KEY);

    Object.values(LEGACY_KEYS).forEach((key: any) => {
        localStorage.removeItem(key);
    });
}

export function getAuthHeader(): string | null {
    const token = getToken();
    return token ? `Bearer ${token}` : null;
}

export function getAuthHeaders(additionalHeaders: Record<string, string> = {}): Record<string, string> {
    const headers = { ...additionalHeaders };
    const authHeader = getAuthHeader();
    if (authHeader) {
        headers['Authorization'] = authHeader;
    }
    return headers;
}

export async function authFetch(url: string, options: RequestInit = {}): Promise<Response> {
    const headers = getAuthHeaders((options.headers as Record<string, string>) || {});
    return fetch(url, { ...options, headers });
}

export function migrateFromLegacy(): void {
    if (localStorage.getItem(AUTH_KEY)) {
        return;
    }

    for (const [app, key] of Object.entries(LEGACY_KEYS)) {
        try {
            const value = localStorage.getItem(key);
            if (!value) continue;

            if (value.startsWith('{')) {
                const session = JSON.parse(value) as AuthSession;
                if (session?.token) {
                    session.app = app;
                    session.version = 2;
                    localStorage.setItem(AUTH_KEY, JSON.stringify(session));
                    return;
                }
            } else {
                const session: Partial<AuthSession> = {
                    token: value,
                    app,
                    loginAt: Date.now(),
                    version: 2,
                };
                localStorage.setItem(AUTH_KEY, JSON.stringify(session));
                return;
            }
        } catch (error: any) {
            log.warn(`Failed to parse legacy key "${key}":`, error);
        }
    }
}

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
