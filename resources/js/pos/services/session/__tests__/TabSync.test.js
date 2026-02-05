/**
 * TabSync Unit Tests
 *
 * Tests for cross-tab session synchronization
 * using BroadcastChannel and localStorage fallback.
 *
 * @group unit
 * @group session
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { TabSync } from '../TabSync.js';
import { TAB_SYNC_CONFIG } from '../constants.js';

// Mock BroadcastChannel
class MockBroadcastChannel {
    static instances = [];

    constructor(name) {
        this.name = name;
        this.onmessage = null;
        this.onmessageerror = null;
        this._closed = false;
        MockBroadcastChannel.instances.push(this);
    }

    postMessage(message) {
        if (this._closed) return;

        // Simulate broadcast to other instances
        MockBroadcastChannel.instances.forEach(instance => {
            if (instance !== this && instance.name === this.name && instance.onmessage && !instance._closed) {
                setTimeout(() => {
                    instance.onmessage({ data: message });
                }, 0);
            }
        });
    }

    close() {
        this._closed = true;
        const index = MockBroadcastChannel.instances.indexOf(this);
        if (index > -1) {
            MockBroadcastChannel.instances.splice(index, 1);
        }
    }

    static clearInstances() {
        MockBroadcastChannel.instances = [];
    }
}

global.BroadcastChannel = MockBroadcastChannel;

// Mock localStorage
const localStorageMock = (() => {
    let store = {};
    return {
        getItem: vi.fn((key) => store[key] || null),
        setItem: vi.fn((key, value) => { store[key] = value; }),
        removeItem: vi.fn((key) => { delete store[key]; }),
        clear: vi.fn(() => { store = {}; }),
    };
})();

Object.defineProperty(global, 'localStorage', { value: localStorageMock });

// Mock document.visibilityState
Object.defineProperty(document, 'visibilityState', {
    value: 'visible',
    writable: true,
});

describe('TabSync', () => {
    let tabSync;

    beforeEach(() => {
        vi.useFakeTimers();
        MockBroadcastChannel.clearInstances();
        localStorageMock.clear();
        vi.clearAllMocks();

        tabSync = new TabSync({ debug: false });
    });

    afterEach(() => {
        tabSync.destroy();
        vi.useRealTimers();
    });

    // ==================== INITIALIZATION ====================

    describe('initialization', () => {
        it('should generate unique tab ID', () => {
            const tabId = tabSync.getTabId();

            expect(tabId).toBeDefined();
            expect(tabId).toMatch(/^tab_\d+_[a-z0-9]+$/);
        });

        it('should create BroadcastChannel', () => {
            expect(MockBroadcastChannel.instances.length).toBeGreaterThan(0);
        });

        it('should use correct channel name', () => {
            const channel = MockBroadcastChannel.instances.find(
                c => c.name === TAB_SYNC_CONFIG.CHANNEL_NAME
            );
            expect(channel).toBeDefined();
        });
    });

    // ==================== LEADER ELECTION ====================

    describe('leader election', () => {
        it('should become leader when alone', () => {
            // Wait for leader election
            vi.advanceTimersByTime(1000);

            expect(tabSync.isLeader()).toBe(true);
        });

        it('should have leader ID match tab ID when leader', () => {
            vi.advanceTimersByTime(1000);

            expect(tabSync.getLeaderId()).toBe(tabSync.getTabId());
        });

        it('should emit leader event', () => {
            const callback = vi.fn();
            tabSync.on('leader', callback);

            vi.advanceTimersByTime(1000);

            expect(callback).toHaveBeenCalled();
        });

        it('should allow forcing leadership', () => {
            tabSync.forceLeadership();

            expect(tabSync.isLeader()).toBe(true);
        });
    });

    // ==================== BROADCAST ====================

    describe('broadcasting', () => {
        it('should broadcast session update', async () => {
            const callback = vi.fn();

            // Create second tab
            const tabSync2 = new TabSync({ debug: false });
            tabSync2.on('sessionUpdate', callback);

            // Broadcast from first tab
            tabSync.broadcastSessionUpdate({ user: { id: 1 } });

            // Wait for message propagation
            await vi.advanceTimersByTimeAsync(10);

            expect(callback).toHaveBeenCalled();

            tabSync2.destroy();
        });

        it('should broadcast logout', async () => {
            const callback = vi.fn();

            const tabSync2 = new TabSync({ debug: false });
            tabSync2.on('logout', callback);

            tabSync.broadcastLogout({ reason: 'test' });

            await vi.advanceTimersByTimeAsync(10);

            expect(callback).toHaveBeenCalledWith(expect.objectContaining({
                reason: 'test',
            }));

            tabSync2.destroy();
        });

        it('should broadcast activity', async () => {
            const callback = vi.fn();

            const tabSync2 = new TabSync({ debug: false });
            tabSync2.on('activity', callback);

            tabSync.broadcastActivity();

            await vi.advanceTimersByTimeAsync(10);

            expect(callback).toHaveBeenCalled();

            tabSync2.destroy();
        });

        it('should broadcast token refresh', async () => {
            const callback = vi.fn();

            const tabSync2 = new TabSync({ debug: false });
            tabSync2.on('tokenRefresh', callback);

            tabSync.broadcastTokenRefresh({ token: 'newtoken' });

            await vi.advanceTimersByTimeAsync(10);

            expect(callback).toHaveBeenCalledWith(expect.objectContaining({
                token: 'newtoken',
            }));

            tabSync2.destroy();
        });
    });

    // ==================== EVENT HANDLERS ====================

    describe('event handlers', () => {
        it('should register event handler', () => {
            const callback = vi.fn();
            tabSync.on('testEvent', callback);

            // Trigger via internal mechanism
            tabSync._triggerHandler('testEvent', { data: 'test' });

            expect(callback).toHaveBeenCalledWith({ data: 'test' });
        });

        it('should return unsubscribe function', () => {
            const callback = vi.fn();
            const unsubscribe = tabSync.on('testEvent', callback);

            expect(typeof unsubscribe).toBe('function');

            unsubscribe();

            tabSync._triggerHandler('testEvent', {});

            expect(callback).not.toHaveBeenCalled();
        });

        it('should handle multiple handlers for same event', () => {
            const callback1 = vi.fn();
            const callback2 = vi.fn();

            tabSync.on('testEvent', callback1);
            tabSync.on('testEvent', callback2);

            tabSync._triggerHandler('testEvent', {});

            expect(callback1).toHaveBeenCalled();
            expect(callback2).toHaveBeenCalled();
        });

        it('should not crash on handler error', () => {
            const errorCallback = vi.fn(() => {
                throw new Error('Handler error');
            });
            const successCallback = vi.fn();

            tabSync.on('testEvent', errorCallback);
            tabSync.on('testEvent', successCallback);

            expect(() => {
                tabSync._triggerHandler('testEvent', {});
            }).not.toThrow();

            expect(successCallback).toHaveBeenCalled();
        });
    });

    // ==================== STATUS ====================

    describe('getStatus()', () => {
        it('should return comprehensive status', () => {
            const status = tabSync.getStatus();

            expect(status).toHaveProperty('tabId');
            expect(status).toHaveProperty('isLeader');
            expect(status).toHaveProperty('leaderId');
            expect(status).toHaveProperty('usingFallback');
        });
    });

    // ==================== DESTROY ====================

    describe('destroy()', () => {
        it('should clean up on destroy', () => {
            const initialChannelCount = MockBroadcastChannel.instances.length;

            tabSync.destroy();

            expect(MockBroadcastChannel.instances.length).toBeLessThan(initialChannelCount);
        });

        it('should stop heartbeat on destroy', () => {
            const spy = vi.spyOn(global, 'clearInterval');

            tabSync.destroy();

            expect(spy).toHaveBeenCalled();
        });
    });

    // ==================== FALLBACK ====================

    describe('localStorage fallback', () => {
        it('should use fallback when BroadcastChannel not available', () => {
            const originalBC = global.BroadcastChannel;
            global.BroadcastChannel = undefined;

            const fallbackSync = new TabSync({ debug: false });

            expect(fallbackSync.getStatus().usingFallback).toBe(true);

            fallbackSync.destroy();
            global.BroadcastChannel = originalBC;
        });
    });
});
