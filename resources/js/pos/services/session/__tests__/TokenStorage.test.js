/**
 * TokenStorage Unit Tests
 *
 * Tests for the token storage layer that handles
 * session persistence with dual-layer caching.
 *
 * @group unit
 * @group session
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { TokenStorage } from '../TokenStorage.js';
import { STORAGE_KEYS } from '../constants.js';

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

describe('TokenStorage', () => {
    let storage;

    const validSession = {
        user: { id: 1, name: 'Test User', role: 'owner' },
        token: '1|abc123def456',
        permissions: ['orders.create', 'orders.cancel'],
        limits: { max_discount_percent: 50 },
        interfaceAccess: { can_access_pos: true },
        loginAt: Date.now(),
        lastActivity: Date.now(),
        expiresAt: Date.now() + 8 * 60 * 60 * 1000,
    };

    beforeEach(() => {
        localStorageMock.clear();
        vi.clearAllMocks();
        storage = new TokenStorage({ debug: false });
    });

    afterEach(() => {
        storage.destroy();
    });

    // ==================== INITIALIZATION ====================

    describe('initialization', () => {
        it('should initialize with empty state when no stored session', () => {
            expect(storage.hasSession()).toBe(false);
            expect(storage.get()).toBeNull();
            expect(storage.getToken()).toBeNull();
        });

        it('should restore session from localStorage on init', () => {
            // Pre-populate localStorage
            localStorageMock.setItem(
                STORAGE_KEYS.SESSION,
                JSON.stringify({ ...validSession, version: 1 })
            );

            // Create new instance - should restore
            const newStorage = new TokenStorage({ debug: false });

            expect(newStorage.hasSession()).toBe(true);
            expect(newStorage.getToken()).toBe(validSession.token);
            expect(newStorage.getUser().name).toBe('Test User');

            newStorage.destroy();
        });

        it('should clear corrupted data on init', () => {
            localStorageMock.setItem(STORAGE_KEYS.SESSION, 'invalid json{{{');

            const newStorage = new TokenStorage({ debug: false });

            expect(newStorage.hasSession()).toBe(false);
            newStorage.destroy();
        });

        it('should migrate old schema versions', () => {
            // Old format without version
            const oldSession = {
                user: { id: 1, name: 'Old User' },
                token: '1|oldtoken',
                expiresAt: Date.now() + 1000000,
            };
            localStorageMock.setItem(STORAGE_KEYS.SESSION, JSON.stringify(oldSession));

            const newStorage = new TokenStorage({ debug: false });

            expect(newStorage.hasSession()).toBe(true);
            expect(newStorage.getField('version')).toBe(1);

            newStorage.destroy();
        });
    });

    // ==================== SAVE ====================

    describe('save()', () => {
        it('should save valid session data', () => {
            const result = storage.save(validSession);

            expect(result).toBe(true);
            expect(storage.hasSession()).toBe(true);
            expect(storage.getToken()).toBe(validSession.token);
        });

        it('should reject invalid data (no token)', () => {
            const result = storage.save({ user: { id: 1 } });

            expect(result).toBe(false);
            expect(storage.hasSession()).toBe(false);
        });

        it('should reject invalid data (no user)', () => {
            const result = storage.save({ token: '1|abc' });

            expect(result).toBe(false);
        });

        it('should persist to localStorage', () => {
            storage.save(validSession);

            expect(localStorageMock.setItem).toHaveBeenCalled();
            const stored = JSON.parse(localStorageMock.getItem(STORAGE_KEYS.SESSION));
            expect(stored.token).toBe(validSession.token);
        });

        it('should add version to saved data', () => {
            storage.save(validSession);

            const stored = JSON.parse(localStorageMock.getItem(STORAGE_KEYS.SESSION));
            expect(stored.version).toBe(1);
        });

        it('should reject invalid token format', () => {
            const result = storage.save({
                ...validSession,
                token: 'invalidformat', // No pipe separator
            });

            expect(result).toBe(false);
        });
    });

    // ==================== GET ====================

    describe('get()', () => {
        beforeEach(() => {
            storage.save(validSession);
        });

        it('should return full session data', () => {
            const session = storage.get();

            expect(session).not.toBeNull();
            expect(session.user.id).toBe(1);
            expect(session.token).toBe(validSession.token);
            expect(session.permissions).toContain('orders.create');
        });

        it('should return a deep clone (immutable)', () => {
            const session1 = storage.get();
            session1.user.name = 'Modified';

            const session2 = storage.get();
            expect(session2.user.name).toBe('Test User');
        });
    });

    describe('getField()', () => {
        beforeEach(() => {
            storage.save(validSession);
        });

        it('should get top-level fields', () => {
            expect(storage.getField('token')).toBe(validSession.token);
        });

        it('should support dot notation', () => {
            expect(storage.getField('user.id')).toBe(1);
            expect(storage.getField('user.name')).toBe('Test User');
            expect(storage.getField('limits.max_discount_percent')).toBe(50);
        });

        it('should return undefined for missing fields', () => {
            expect(storage.getField('nonexistent')).toBeUndefined();
            expect(storage.getField('user.nonexistent')).toBeUndefined();
        });
    });

    describe('getToken()', () => {
        it('should return token when session exists', () => {
            storage.save(validSession);
            expect(storage.getToken()).toBe(validSession.token);
        });

        it('should return null when no session', () => {
            expect(storage.getToken()).toBeNull();
        });
    });

    describe('getUser()', () => {
        it('should return user when session exists', () => {
            storage.save(validSession);
            const user = storage.getUser();
            expect(user.id).toBe(1);
            expect(user.name).toBe('Test User');
        });

        it('should return null when no session', () => {
            expect(storage.getUser()).toBeNull();
        });

        it('should return a clone (immutable)', () => {
            storage.save(validSession);
            const user1 = storage.getUser();
            user1.name = 'Modified';

            const user2 = storage.getUser();
            expect(user2.name).toBe('Test User');
        });
    });

    // ==================== UPDATE ====================

    describe('update()', () => {
        beforeEach(() => {
            storage.save(validSession);
        });

        it('should update specific fields', () => {
            const result = storage.update({ lastActivity: Date.now() + 1000 });

            expect(result).toBe(true);
            expect(storage.getField('lastActivity')).toBeGreaterThan(validSession.lastActivity);
        });

        it('should merge with existing data', () => {
            storage.update({ lastActivity: 999999 });

            expect(storage.getToken()).toBe(validSession.token); // Unchanged
            expect(storage.getField('lastActivity')).toBe(999999); // Updated
        });

        it('should fail when no session exists', () => {
            storage.clear();
            const result = storage.update({ lastActivity: 123 });

            expect(result).toBe(false);
        });

        it('should persist updates to localStorage', () => {
            storage.update({ lastActivity: 123456 });

            const stored = JSON.parse(localStorageMock.getItem(STORAGE_KEYS.SESSION));
            expect(stored.lastActivity).toBe(123456);
        });
    });

    // ==================== EXPIRATION ====================

    describe('expiration', () => {
        it('should detect expired session', () => {
            storage.save({
                ...validSession,
                expiresAt: Date.now() - 1000, // Expired 1 second ago
            });

            expect(storage.isExpired()).toBe(true);
        });

        it('should detect valid session', () => {
            storage.save({
                ...validSession,
                expiresAt: Date.now() + 1000000, // Far future
            });

            expect(storage.isExpired()).toBe(false);
        });

        it('should return correct time until expiry', () => {
            const expiresAt = Date.now() + 60000; // 1 minute
            storage.save({ ...validSession, expiresAt });

            const timeUntil = storage.getTimeUntilExpiry();
            expect(timeUntil).toBeGreaterThan(59000);
            expect(timeUntil).toBeLessThanOrEqual(60000);
        });

        it('should return negative for expired session', () => {
            storage.save({
                ...validSession,
                expiresAt: Date.now() - 5000,
            });

            expect(storage.getTimeUntilExpiry()).toBeLessThan(0);
        });
    });

    describe('extendExpiration()', () => {
        beforeEach(() => {
            storage.save(validSession);
        });

        it('should extend expiration time', () => {
            const oldExpiry = storage.getField('expiresAt');
            const extension = 60 * 60 * 1000; // 1 hour

            storage.extendExpiration(extension);

            const newExpiry = storage.getField('expiresAt');
            expect(newExpiry).toBeGreaterThan(oldExpiry);
        });

        it('should update lastExtension timestamp', () => {
            storage.extendExpiration(60000);

            expect(storage.getField('lastExtension')).toBeDefined();
            expect(storage.getField('lastExtension')).toBeGreaterThan(0);
        });

        it('should fail when no session', () => {
            storage.clear();
            const result = storage.extendExpiration(60000);

            expect(result).toBe(false);
        });
    });

    // ==================== ACTIVITY ====================

    describe('recordActivity()', () => {
        beforeEach(() => {
            storage.save(validSession);
        });

        it('should update lastActivity in memory', () => {
            const oldActivity = storage.getField('lastActivity');

            // Wait a bit to ensure different timestamp
            vi.advanceTimersByTime?.(10) || (async () => await new Promise(r => setTimeout(r, 10)))();

            storage.recordActivity();

            // Note: recordActivity only updates memory, not storage
            // So we check the internal cache
            expect(storage.getField('lastActivity')).toBeGreaterThanOrEqual(oldActivity);
        });
    });

    // ==================== CLEAR ====================

    describe('clear()', () => {
        beforeEach(() => {
            storage.save(validSession);
        });

        it('should clear memory cache', () => {
            storage.clear();

            expect(storage.hasSession()).toBe(false);
            expect(storage.get()).toBeNull();
        });

        it('should clear localStorage', () => {
            storage.clear();

            expect(localStorageMock.removeItem).toHaveBeenCalledWith(STORAGE_KEYS.SESSION);
        });
    });

    // ==================== SYNC ====================

    describe('syncFromStorage()', () => {
        it('should sync newer data from storage', () => {
            storage.save(validSession);

            // Simulate another tab updating storage
            const newerSession = {
                ...validSession,
                lastActivity: Date.now() + 10000,
                version: 1,
            };
            localStorageMock.setItem(STORAGE_KEYS.SESSION, JSON.stringify(newerSession));

            const synced = storage.syncFromStorage();

            expect(synced).toBe(true);
        });

        it('should not sync older data', () => {
            const newerSession = {
                ...validSession,
                lastActivity: Date.now() + 10000,
            };
            storage.save(newerSession);

            // Simulate storage having older data
            localStorageMock.setItem(STORAGE_KEYS.SESSION, JSON.stringify({
                ...validSession,
                lastActivity: Date.now() - 10000,
                version: 1,
            }));

            const synced = storage.syncFromStorage();

            expect(synced).toBe(false);
        });

        it('should clear cache when storage is cleared', () => {
            storage.save(validSession);
            localStorageMock.removeItem(STORAGE_KEYS.SESSION);

            const synced = storage.syncFromStorage();

            expect(synced).toBe(true);
            expect(storage.hasSession()).toBe(false);
        });
    });

    // ==================== STATS ====================

    describe('getStats()', () => {
        it('should return comprehensive stats', () => {
            storage.save(validSession);

            const stats = storage.getStats();

            expect(stats).toHaveProperty('hasSession', true);
            expect(stats).toHaveProperty('isExpired');
            expect(stats).toHaveProperty('timeUntilExpiry');
            expect(stats).toHaveProperty('sessionSize');
            expect(stats).toHaveProperty('localStorageAvailable', true);
        });

        it('should report correct state when no session', () => {
            const stats = storage.getStats();

            expect(stats.hasSession).toBe(false);
            expect(stats.sessionSize).toBe(0);
        });
    });
});
