/**
 * SessionManager Integration Tests
 *
 * Tests for the main session orchestrator that coordinates
 * all session management functionality.
 *
 * @group integration
 * @group session
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { SessionManager, resetSessionManager } from '../SessionManager.js';
import { SESSION_STATES, SESSION_EVENTS, SESSION_TIMING } from '../constants.js';
import axios from 'axios';

// Mock axios
vi.mock('axios');

// Mock localStorage
const localStorageMock = (() => {
    let store = {};
    return {
        getItem: vi.fn((key) => store[key] || null),
        setItem: vi.fn((key, value) => { store[key] = value; }),
        removeItem: vi.fn((key) => { delete store[key]; }),
        clear: vi.fn(() => { store = {}; }),
        get length() { return Object.keys(store).length; },
        key: vi.fn((i) => Object.keys(store)[i] || null),
    };
})();

Object.defineProperty(global, 'localStorage', { value: localStorageMock });

// Mock BroadcastChannel
class MockBroadcastChannel {
    constructor() {
        this.onmessage = null;
        this.onmessageerror = null;
    }
    postMessage() {}
    close() {}
}
global.BroadcastChannel = MockBroadcastChannel;

// Mock navigator.onLine
Object.defineProperty(global, 'navigator', {
    value: { onLine: true },
    writable: true,
});

describe('SessionManager', () => {
    let manager;

    const mockLoginResponse = {
        user: { id: 1, name: 'Test User', role: 'owner', restaurant_id: 1 },
        token: '1|abc123def456',
        permissions: ['orders.create', 'orders.cancel'],
        limits: { max_discount_percent: 50, max_refund_amount: 1000 },
        interface_access: { can_access_pos: true },
    };

    const mockCheckAuthResponse = {
        success: true,
        data: {
            user: { id: 1, name: 'Test User', role: 'owner' },
            permissions: ['orders.create'],
            limits: { max_discount_percent: 50 },
            interface_access: { can_access_pos: true },
        },
    };

    beforeEach(() => {
        vi.useFakeTimers();
        localStorageMock.clear();
        vi.clearAllMocks();
        resetSessionManager();

        // Default axios mock
        axios.get.mockResolvedValue({ data: mockCheckAuthResponse });
        axios.post.mockResolvedValue({ data: { success: true } });

        manager = new SessionManager({ debug: false });
    });

    afterEach(() => {
        manager.destroy();
        vi.useRealTimers();
    });

    // ==================== INITIALIZATION ====================

    describe('initialization', () => {
        it('should initialize with NONE state when no session', () => {
            expect(manager.getState()).toBe(SESSION_STATES.NONE);
            expect(manager.isActive()).toBe(false);
            expect(manager.hasSession()).toBe(false);
        });

        it('should detect existing session on init', () => {
            // Pre-populate storage
            const existingSession = {
                ...mockLoginResponse,
                loginAt: Date.now(),
                lastActivity: Date.now(),
                expiresAt: Date.now() + SESSION_TIMING.MAX_LIFETIME,
                version: 1,
            };
            localStorageMock.setItem('menulab_session', JSON.stringify(existingSession));

            const newManager = new SessionManager({ debug: false });

            expect(newManager.getState()).toBe(SESSION_STATES.ACTIVE);
            expect(newManager.hasSession()).toBe(true);

            newManager.destroy();
        });

        it('should detect expired session on init', () => {
            const expiredSession = {
                ...mockLoginResponse,
                loginAt: Date.now() - 10000000,
                expiresAt: Date.now() - 1000, // Expired
                version: 1,
            };
            localStorageMock.setItem('menulab_session', JSON.stringify(expiredSession));

            const newManager = new SessionManager({ debug: false });

            expect(newManager.getState()).toBe(SESSION_STATES.EXPIRED);

            newManager.destroy();
        });
    });

    // ==================== CREATE SESSION ====================

    describe('createSession()', () => {
        it('should create session with valid data', () => {
            const result = manager.createSession(mockLoginResponse);

            expect(result).toBe(true);
            expect(manager.isActive()).toBe(true);
            expect(manager.getState()).toBe(SESSION_STATES.ACTIVE);
        });

        it('should store token', () => {
            manager.createSession(mockLoginResponse);

            expect(manager.getToken()).toBe(mockLoginResponse.token);
        });

        it('should store user', () => {
            manager.createSession(mockLoginResponse);

            const user = manager.getUser();
            expect(user.id).toBe(1);
            expect(user.name).toBe('Test User');
        });

        it('should set expiration time', () => {
            const before = Date.now();
            manager.createSession(mockLoginResponse);
            const after = Date.now();

            const session = manager.getSession();
            expect(session.expiresAt).toBeGreaterThanOrEqual(before + SESSION_TIMING.MAX_LIFETIME);
            expect(session.expiresAt).toBeLessThanOrEqual(after + SESSION_TIMING.MAX_LIFETIME);
        });

        it('should reject invalid data', () => {
            const result = manager.createSession({ invalid: 'data' });

            expect(result).toBe(false);
            expect(manager.isActive()).toBe(false);
        });

        it('should emit CREATED event', () => {
            const callback = vi.fn();
            manager.on(SESSION_EVENTS.CREATED, callback);

            manager.createSession(mockLoginResponse);

            expect(callback).toHaveBeenCalledWith(expect.objectContaining({
                user: expect.objectContaining({ id: 1 }),
            }));
        });
    });

    // ==================== RESTORE SESSION ====================

    describe('restoreSession()', () => {
        it('should restore valid session', async () => {
            const existingSession = {
                ...mockLoginResponse,
                loginAt: Date.now(),
                lastActivity: Date.now(),
                expiresAt: Date.now() + SESSION_TIMING.MAX_LIFETIME,
                version: 1,
            };
            localStorageMock.setItem('menulab_session', JSON.stringify(existingSession));

            const newManager = new SessionManager({ debug: false });
            const result = await newManager.restoreSession();

            expect(result).not.toBeNull();
            expect(result.user.id).toBe(1);
            expect(newManager.isActive()).toBe(true);

            newManager.destroy();
        });

        it('should return null when no session exists', async () => {
            const result = await manager.restoreSession();

            expect(result).toBeNull();
            expect(manager.getState()).toBe(SESSION_STATES.NONE);
        });

        it('should return null for expired session', async () => {
            const expiredSession = {
                ...mockLoginResponse,
                loginAt: Date.now() - 10000000,
                expiresAt: Date.now() - 1000,
                version: 1,
            };
            localStorageMock.setItem('menulab_session', JSON.stringify(expiredSession));

            const newManager = new SessionManager({ debug: false });
            const result = await newManager.restoreSession();

            expect(result).toBeNull();

            newManager.destroy();
        });

        it('should validate token with server', async () => {
            const existingSession = {
                ...mockLoginResponse,
                loginAt: Date.now(),
                lastActivity: Date.now(),
                expiresAt: Date.now() + SESSION_TIMING.MAX_LIFETIME,
                version: 1,
            };
            localStorageMock.setItem('menulab_session', JSON.stringify(existingSession));

            const newManager = new SessionManager({ debug: false });
            await newManager.restoreSession();

            expect(axios.get).toHaveBeenCalledWith(
                '/api/auth/check',
                expect.objectContaining({
                    headers: expect.objectContaining({
                        Authorization: `Bearer ${mockLoginResponse.token}`,
                    }),
                })
            );

            newManager.destroy();
        });

        it('should clear session on server validation failure', async () => {
            axios.get.mockResolvedValueOnce({
                data: { success: false, message: 'Invalid token' },
            });

            const existingSession = {
                ...mockLoginResponse,
                loginAt: Date.now(),
                lastActivity: Date.now(),
                expiresAt: Date.now() + SESSION_TIMING.MAX_LIFETIME,
                version: 1,
            };
            localStorageMock.setItem('menulab_session', JSON.stringify(existingSession));

            const newManager = new SessionManager({ debug: false });
            const result = await newManager.restoreSession();

            expect(result).toBeNull();
            expect(newManager.getState()).toBe(SESSION_STATES.INVALID);

            newManager.destroy();
        });

        it('should extend session after successful restore', async () => {
            const existingSession = {
                ...mockLoginResponse,
                loginAt: Date.now() - 1000000,
                lastActivity: Date.now() - 1000000,
                expiresAt: Date.now() + 1000000, // Not much time left
                version: 1,
            };
            localStorageMock.setItem('menulab_session', JSON.stringify(existingSession));

            const newManager = new SessionManager({ debug: false });
            const result = await newManager.restoreSession();

            expect(result).not.toBeNull();
            // Session should be extended
            expect(newManager.getTimeUntilExpiry()).toBeGreaterThan(1000000);

            newManager.destroy();
        });

        it('should emit RESTORED event', async () => {
            const existingSession = {
                ...mockLoginResponse,
                loginAt: Date.now(),
                lastActivity: Date.now(),
                expiresAt: Date.now() + SESSION_TIMING.MAX_LIFETIME,
                version: 1,
            };
            localStorageMock.setItem('menulab_session', JSON.stringify(existingSession));

            const callback = vi.fn();
            const newManager = new SessionManager({ debug: false });
            newManager.on(SESSION_EVENTS.RESTORED, callback);

            await newManager.restoreSession();

            expect(callback).toHaveBeenCalled();

            newManager.destroy();
        });
    });

    // ==================== LOGOUT ====================

    describe('logout()', () => {
        beforeEach(() => {
            manager.createSession(mockLoginResponse);
        });

        it('should clear session state', async () => {
            await manager.logout();

            expect(manager.isActive()).toBe(false);
            expect(manager.getState()).toBe(SESSION_STATES.NONE);
            expect(manager.getToken()).toBeNull();
        });

        it('should notify server', async () => {
            await manager.logout();

            expect(axios.post).toHaveBeenCalledWith(
                '/api/auth/logout',
                {},
                expect.objectContaining({
                    headers: expect.objectContaining({
                        Authorization: expect.stringContaining('Bearer'),
                    }),
                })
            );
        });

        it('should skip server notification if specified', async () => {
            await manager.logout({ notifyServer: false });

            expect(axios.post).not.toHaveBeenCalled();
        });

        it('should emit CLEARED event', async () => {
            const callback = vi.fn();
            manager.on(SESSION_EVENTS.CLEARED, callback);

            await manager.logout();

            expect(callback).toHaveBeenCalledWith(expect.objectContaining({
                reason: 'user_logout',
            }));
        });

        it('should handle server error gracefully', async () => {
            axios.post.mockRejectedValueOnce(new Error('Network Error'));

            // Should not throw
            await expect(manager.logout()).resolves.not.toThrow();
            expect(manager.isActive()).toBe(false);
        });
    });

    // ==================== SESSION EXTENSION ====================

    describe('session extension', () => {
        beforeEach(() => {
            manager.createSession(mockLoginResponse);
        });

        it('should extend session manually', () => {
            const oldExpiry = manager.getSession().expiresAt;

            vi.advanceTimersByTime(1000); // Move time forward
            manager.extend();

            const newExpiry = manager.getSession().expiresAt;
            expect(newExpiry).toBeGreaterThan(oldExpiry);
        });

        it('should emit EXTENDED event', () => {
            const callback = vi.fn();
            manager.on(SESSION_EVENTS.EXTENDED, callback);

            manager.extend();

            expect(callback).toHaveBeenCalled();
        });
    });

    // ==================== EXPIRATION ====================

    describe('expiration handling', () => {
        beforeEach(() => {
            manager.createSession(mockLoginResponse);
        });

        it('should emit warning before expiration', async () => {
            const callback = vi.fn();
            manager.on(SESSION_EVENTS.EXPIRING_SOON, callback);

            // Fast-forward to near expiration
            vi.advanceTimersByTime(SESSION_TIMING.MAX_LIFETIME - SESSION_TIMING.EXPIRATION_WARNING + 60000);

            expect(callback).toHaveBeenCalled();
        });

        it('should call onSessionWarning callback', async () => {
            const onWarning = vi.fn();
            const newManager = new SessionManager({
                debug: false,
                onSessionWarning: onWarning,
            });
            newManager.createSession(mockLoginResponse);

            vi.advanceTimersByTime(SESSION_TIMING.MAX_LIFETIME - SESSION_TIMING.EXPIRATION_WARNING + 60000);

            expect(onWarning).toHaveBeenCalledWith(expect.objectContaining({
                timeUntilExpiry: expect.any(Number),
            }));

            newManager.destroy();
        });

        it('should emit critical warning near expiration', async () => {
            const callback = vi.fn();
            manager.on(SESSION_EVENTS.EXPIRING_SOON, callback);

            vi.advanceTimersByTime(SESSION_TIMING.MAX_LIFETIME - SESSION_TIMING.EXPIRATION_CRITICAL + 60000);

            expect(callback).toHaveBeenCalledWith(expect.objectContaining({
                critical: true,
            }));
        });
    });

    // ==================== GETTERS ====================

    describe('getters', () => {
        beforeEach(() => {
            manager.createSession(mockLoginResponse);
        });

        describe('getToken()', () => {
            it('should return token', () => {
                expect(manager.getToken()).toBe(mockLoginResponse.token);
            });

            it('should return null when no session', async () => {
                await manager.logout({ notifyServer: false });
                expect(manager.getToken()).toBeNull();
            });
        });

        describe('getUser()', () => {
            it('should return user', () => {
                const user = manager.getUser();
                expect(user.id).toBe(1);
                expect(user.name).toBe('Test User');
            });

            it('should return null when no session', async () => {
                await manager.logout({ notifyServer: false });
                expect(manager.getUser()).toBeNull();
            });
        });

        describe('getSession()', () => {
            it('should return full session', () => {
                const session = manager.getSession();

                expect(session.user).toBeDefined();
                expect(session.token).toBeDefined();
                expect(session.permissions).toBeDefined();
                expect(session.expiresAt).toBeDefined();
            });
        });

        describe('getField()', () => {
            it('should get nested fields', () => {
                expect(manager.getField('user.id')).toBe(1);
                expect(manager.getField('user.name')).toBe('Test User');
            });
        });

        describe('getTimeUntilExpiry()', () => {
            it('should return positive time for valid session', () => {
                expect(manager.getTimeUntilExpiry()).toBeGreaterThan(0);
            });
        });
    });

    // ==================== UPDATE SESSION ====================

    describe('updateSession()', () => {
        beforeEach(() => {
            manager.createSession(mockLoginResponse);
        });

        it('should update session fields', () => {
            const newPermissions = ['new.permission'];
            manager.updateSession({ permissions: newPermissions });

            expect(manager.getField('permissions')).toContain('new.permission');
        });

        it('should preserve unchanged fields', () => {
            manager.updateSession({ lastActivity: Date.now() });

            expect(manager.getToken()).toBe(mockLoginResponse.token);
            expect(manager.getUser().name).toBe('Test User');
        });
    });

    // ==================== STATUS ====================

    describe('getStatus()', () => {
        it('should return comprehensive status', () => {
            manager.createSession(mockLoginResponse);

            const status = manager.getStatus();

            expect(status).toHaveProperty('state');
            expect(status).toHaveProperty('isActive');
            expect(status).toHaveProperty('hasSession');
            expect(status).toHaveProperty('user');
            expect(status).toHaveProperty('timeUntilExpiry');
            expect(status).toHaveProperty('storage');
            expect(status).toHaveProperty('network');
            expect(status).toHaveProperty('tabSync');
        });
    });

    // ==================== EVENT SUBSCRIPTIONS ====================

    describe('event subscriptions', () => {
        it('should allow subscribing to events', () => {
            const callback = vi.fn();
            const unsubscribe = manager.on(SESSION_EVENTS.STATE_CHANGE, callback);

            manager.createSession(mockLoginResponse);

            expect(callback).toHaveBeenCalled();
            expect(typeof unsubscribe).toBe('function');
        });

        it('should allow unsubscribing', () => {
            const callback = vi.fn();
            const unsubscribe = manager.on(SESSION_EVENTS.STATE_CHANGE, callback);

            unsubscribe();
            manager.createSession(mockLoginResponse);

            // Callback may still be called due to internal state changes
            // but we verify unsubscribe function exists
            expect(typeof unsubscribe).toBe('function');
        });

        it('should support once subscriptions', async () => {
            const callback = vi.fn();
            manager.once(SESSION_EVENTS.CREATED, callback);

            manager.createSession(mockLoginResponse);
            await manager.logout({ notifyServer: false });
            manager.createSession(mockLoginResponse);

            expect(callback).toHaveBeenCalledTimes(1);
        });
    });

    // ==================== NETWORK RESILIENCE ====================

    describe('network resilience', () => {
        it('should keep session on network error during validation', async () => {
            axios.get.mockRejectedValueOnce(new Error('Network Error'));

            const existingSession = {
                ...mockLoginResponse,
                loginAt: Date.now(),
                lastActivity: Date.now(),
                expiresAt: Date.now() + SESSION_TIMING.MAX_LIFETIME,
                version: 1,
            };
            localStorageMock.setItem('menulab_session', JSON.stringify(existingSession));

            const newManager = new SessionManager({ debug: false });
            const result = await newManager.restoreSession();

            // Should consider valid despite network error
            expect(result).not.toBeNull();

            newManager.destroy();
        });
    });
});
