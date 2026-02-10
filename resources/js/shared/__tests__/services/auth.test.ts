/**
 * Shared Auth Service Unit Tests
 */

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';

// Mock logger
vi.mock('@/shared/services/logger.js', () => ({
    createLogger: () => ({
        debug: vi.fn(),
        warn: vi.fn(),
        error: vi.fn(),
        info: vi.fn(),
    }),
}));

import {
    getSession,
    getToken,
    getUser,
    isAuthenticated,
    setSession,
    setToken,
    clearAuth,
    getAuthHeader,
    getAuthHeaders,
    migrateFromLegacy,
} from '@/shared/services/auth.js';

describe('Auth Service', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    afterEach(() => {
        localStorage.clear();
    });

    describe('getSession', () => {
        it('should return null when no session exists', () => {
            expect(getSession()).toBeNull();
        });

        it('should return session from menulab_auth key', () => {
            const session = {
                token: 'test-token',
                user: { id: 1, name: 'Test' },
                permissions: ['orders.view'],
                limits: {},
                interfaceAccess: {},
                posModules: [],
                backofficeModules: [],
                app: 'pos',
                loginAt: Date.now(),
                version: 2,
            };
            localStorage.setItem('menulab_auth', JSON.stringify(session));

            const result = getSession();
            expect(result).not.toBeNull();
            expect(result!.token).toBe('test-token');
            expect(result!.user).toEqual({ id: 1, name: 'Test' });
        });

        it('should fallback to menulab_session key', () => {
            const session = {
                token: 'legacy-token',
                user: null,
                app: 'pos',
            };
            localStorage.setItem('menulab_session', JSON.stringify(session));

            const result = getSession();
            expect(result).not.toBeNull();
            expect(result!.token).toBe('legacy-token');
        });

        it('should return null for invalid JSON', () => {
            localStorage.setItem('menulab_auth', 'invalid-json');
            expect(getSession()).toBeNull();
        });

        it('should return null for session without token', () => {
            localStorage.setItem('menulab_auth', JSON.stringify({ user: {} }));
            expect(getSession()).toBeNull();
        });
    });

    describe('getToken', () => {
        it('should return null when no session', () => {
            expect(getToken()).toBeNull();
        });

        it('should return token from session', () => {
            localStorage.setItem('menulab_auth', JSON.stringify({
                token: 'my-token',
                user: null,
            }));
            expect(getToken()).toBe('my-token');
        });
    });

    describe('getUser', () => {
        it('should return null when no session', () => {
            expect(getUser()).toBeNull();
        });

        it('should return user from session', () => {
            localStorage.setItem('menulab_auth', JSON.stringify({
                token: 'token',
                user: { id: 1, name: 'John' },
            }));
            expect(getUser()).toEqual({ id: 1, name: 'John' });
        });
    });

    describe('isAuthenticated', () => {
        it('should return false when no token', () => {
            expect(isAuthenticated()).toBe(false);
        });

        it('should return true when token exists', () => {
            localStorage.setItem('menulab_auth', JSON.stringify({
                token: 'token',
            }));
            expect(isAuthenticated()).toBe(true);
        });
    });

    describe('setSession', () => {
        it('should save session to localStorage', () => {
            const session = setSession({
                token: 'new-token',
                user: { id: 1, name: 'Test' },
                permissions: ['orders.view'],
            });

            expect(session.token).toBe('new-token');
            expect(session.version).toBe(2);
            expect(session.app).toBe('pos'); // default

            const stored = JSON.parse(localStorage.getItem('menulab_auth')!);
            expect(stored.token).toBe('new-token');
        });

        it('should also save to menulab_session for pos app', () => {
            setSession({ token: 'pos-token' }, { app: 'pos' });

            expect(localStorage.getItem('menulab_session')).not.toBeNull();
            const session = JSON.parse(localStorage.getItem('menulab_session')!);
            expect(session.token).toBe('pos-token');
        });

        it('should NOT save to menulab_session for non-pos app', () => {
            setSession({ token: 'admin-token' }, { app: 'admin' });

            expect(localStorage.getItem('menulab_session')).toBeNull();
        });

        it('should normalize interface_access to interfaceAccess', () => {
            const session = setSession({
                token: 'token',
                interface_access: { can_access_pos: true },
            });

            expect(session.interfaceAccess).toEqual({ can_access_pos: true });
        });

        it('should normalize pos_modules to posModules', () => {
            const session = setSession({
                token: 'token',
                pos_modules: ['cash', 'orders'],
            });

            expect(session.posModules).toEqual(['cash', 'orders']);
        });

        it('should set loginAt timestamp', () => {
            const before = Date.now();
            const session = setSession({ token: 'token' });
            const after = Date.now();

            expect(session.loginAt).toBeGreaterThanOrEqual(before);
            expect(session.loginAt).toBeLessThanOrEqual(after);
        });
    });

    describe('setToken', () => {
        it('should create session with just token', () => {
            setToken('simple-token');

            const session = getSession();
            expect(session).not.toBeNull();
            expect(session!.token).toBe('simple-token');
        });

        it('should create session with token and user', () => {
            setToken('token', { id: 1, name: 'User' }, 'backoffice');

            const session = getSession();
            expect(session!.token).toBe('token');
            expect(session!.user).toEqual({ id: 1, name: 'User' });
            expect(session!.app).toBe('backoffice');
        });
    });

    describe('clearAuth', () => {
        it('should remove menulab_auth from localStorage', () => {
            localStorage.setItem('menulab_auth', 'test');
            clearAuth();
            expect(localStorage.getItem('menulab_auth')).toBeNull();
        });

        it('should remove all legacy keys', () => {
            localStorage.setItem('menulab_session', 'test');
            localStorage.setItem('backoffice_token', 'test');
            localStorage.setItem('admin_token', 'test');
            localStorage.setItem('courier_token', 'test');
            localStorage.setItem('cabinet_token', 'test');
            localStorage.setItem('api_token', 'test');

            clearAuth();

            expect(localStorage.getItem('menulab_session')).toBeNull();
            expect(localStorage.getItem('backoffice_token')).toBeNull();
            expect(localStorage.getItem('admin_token')).toBeNull();
            expect(localStorage.getItem('courier_token')).toBeNull();
            expect(localStorage.getItem('cabinet_token')).toBeNull();
            expect(localStorage.getItem('api_token')).toBeNull();
        });
    });

    describe('getAuthHeader', () => {
        it('should return null when no token', () => {
            expect(getAuthHeader()).toBeNull();
        });

        it('should return Bearer token', () => {
            localStorage.setItem('menulab_auth', JSON.stringify({ token: 'abc123' }));
            expect(getAuthHeader()).toBe('Bearer abc123');
        });
    });

    describe('getAuthHeaders', () => {
        it('should return empty headers when no token', () => {
            const headers = getAuthHeaders();
            expect(headers).toEqual({});
        });

        it('should include Authorization header when token exists', () => {
            localStorage.setItem('menulab_auth', JSON.stringify({ token: 'token' }));
            const headers = getAuthHeaders();
            expect(headers['Authorization']).toBe('Bearer token');
        });

        it('should merge with additional headers', () => {
            localStorage.setItem('menulab_auth', JSON.stringify({ token: 'token' }));
            const headers = getAuthHeaders({ 'Content-Type': 'application/json' });
            expect(headers['Authorization']).toBe('Bearer token');
            expect(headers['Content-Type']).toBe('application/json');
        });
    });

    describe('migrateFromLegacy', () => {
        it('should not migrate if menulab_auth already exists', () => {
            localStorage.setItem('menulab_auth', JSON.stringify({ token: 'existing' }));
            localStorage.setItem('menulab_session', JSON.stringify({ token: 'legacy' }));

            migrateFromLegacy();

            const session = JSON.parse(localStorage.getItem('menulab_auth')!);
            expect(session.token).toBe('existing');
        });

        it('should migrate JSON session from legacy key', () => {
            const legacySession = {
                token: 'legacy-token',
                user: { id: 1 },
            };
            localStorage.setItem('menulab_session', JSON.stringify(legacySession));

            migrateFromLegacy();

            const session = JSON.parse(localStorage.getItem('menulab_auth')!);
            expect(session.token).toBe('legacy-token');
            expect(session.version).toBe(2);
            expect(session.app).toBe('pos');
        });

        it('should migrate plain token string from legacy key', () => {
            localStorage.setItem('api_token', 'plain-token-string');

            migrateFromLegacy();

            const session = JSON.parse(localStorage.getItem('menulab_auth')!);
            expect(session.token).toBe('plain-token-string');
            expect(session.version).toBe(2);
        });

        it('should skip empty legacy keys', () => {
            migrateFromLegacy();
            expect(localStorage.getItem('menulab_auth')).toBeNull();
        });
    });
});
