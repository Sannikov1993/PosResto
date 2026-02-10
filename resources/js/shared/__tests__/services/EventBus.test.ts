/**
 * EventBus Service Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock logger
vi.mock('@/shared/services/logger.js', () => ({
    createLogger: () => ({
        debug: vi.fn(),
        warn: vi.fn(),
        error: vi.fn(),
        info: vi.fn(),
    }),
}));

import { EventBus } from '@/shared/services/EventBus.js';

describe('EventBus', () => {
    let bus: EventBus;

    beforeEach(() => {
        bus = new EventBus();
    });

    describe('on', () => {
        it('should register a handler for an event', () => {
            const handler = vi.fn();
            bus.on('test', handler);

            expect(bus.hasSubscribers('test')).toBe(true);
        });

        it('should return an unsubscribe function', () => {
            const handler = vi.fn();
            const unsub = bus.on('test', handler);

            expect(typeof unsub).toBe('function');
        });

        it('should allow multiple handlers for the same event', () => {
            const handler1 = vi.fn();
            const handler2 = vi.fn();

            bus.on('test', handler1);
            bus.on('test', handler2);

            expect(bus.getSubscriptions()).toEqual({ test: 2 });
        });

        it('should allow handlers for different events', () => {
            bus.on('event1', vi.fn());
            bus.on('event2', vi.fn());

            expect(bus.hasSubscribers('event1')).toBe(true);
            expect(bus.hasSubscribers('event2')).toBe(true);
            expect(bus.getSubscriptionCount()).toBe(2);
        });
    });

    describe('off', () => {
        it('should remove a specific handler', () => {
            const handler = vi.fn();
            bus.on('test', handler);
            bus.off('test', handler);

            expect(bus.hasSubscribers('test')).toBe(false);
        });

        it('should not throw when removing a handler from a non-existent event', () => {
            const handler = vi.fn();
            expect(() => bus.off('nonexistent', handler)).not.toThrow();
        });

        it('should clean up the event key when the last handler is removed', () => {
            const handler = vi.fn();
            bus.on('test', handler);
            bus.off('test', handler);

            expect(bus.getSubscriptions()).toEqual({});
        });

        it('should only remove the specified handler, not others', () => {
            const handler1 = vi.fn();
            const handler2 = vi.fn();

            bus.on('test', handler1);
            bus.on('test', handler2);
            bus.off('test', handler1);

            bus.emit('test', 'data');

            expect(handler1).not.toHaveBeenCalled();
            expect(handler2).toHaveBeenCalledWith('data');
        });
    });

    describe('emit', () => {
        it('should call all handlers for the emitted event', () => {
            const handler1 = vi.fn();
            const handler2 = vi.fn();

            bus.on('test', handler1);
            bus.on('test', handler2);

            bus.emit('test', { foo: 'bar' });

            expect(handler1).toHaveBeenCalledWith({ foo: 'bar' });
            expect(handler2).toHaveBeenCalledWith({ foo: 'bar' });
        });

        it('should pass data to the handler', () => {
            const handler = vi.fn();
            bus.on('test', handler);

            bus.emit('test', 42);

            expect(handler).toHaveBeenCalledWith(42);
        });

        it('should not throw when emitting to an event with no handlers', () => {
            expect(() => bus.emit('nonexistent', 'data')).not.toThrow();
        });

        it('should catch errors in handlers and continue executing', () => {
            const errorHandler = vi.fn(() => { throw new Error('handler error'); });
            const normalHandler = vi.fn();

            bus.on('test', errorHandler);
            bus.on('test', normalHandler);

            bus.emit('test', 'data');

            expect(errorHandler).toHaveBeenCalledWith('data');
            expect(normalHandler).toHaveBeenCalledWith('data');
        });

        it('should emit with undefined data when no data is provided', () => {
            const handler = vi.fn();
            bus.on('test', handler);

            bus.emit('test');

            expect(handler).toHaveBeenCalledWith(undefined);
        });
    });

    describe('unsubscribe function returned by on', () => {
        it('should unsubscribe the handler when called', () => {
            const handler = vi.fn();
            const unsub = bus.on('test', handler);

            unsub();

            bus.emit('test', 'data');
            expect(handler).not.toHaveBeenCalled();
        });

        it('should be safe to call multiple times', () => {
            const handler = vi.fn();
            const unsub = bus.on('test', handler);

            unsub();
            expect(() => unsub()).not.toThrow();
        });
    });

    describe('clear', () => {
        it('should remove all handlers for all events', () => {
            bus.on('event1', vi.fn());
            bus.on('event2', vi.fn());
            bus.on('event3', vi.fn());

            bus.clear();

            expect(bus.getSubscriptionCount()).toBe(0);
            expect(bus.getSubscriptions()).toEqual({});
        });
    });

    describe('getSubscriptions', () => {
        it('should return an empty object when no subscribers', () => {
            expect(bus.getSubscriptions()).toEqual({});
        });

        it('should return correct counts per event', () => {
            bus.on('a', vi.fn());
            bus.on('a', vi.fn());
            bus.on('b', vi.fn());

            expect(bus.getSubscriptions()).toEqual({ a: 2, b: 1 });
        });
    });

    describe('getSubscriptionCount', () => {
        it('should return 0 when no subscribers', () => {
            expect(bus.getSubscriptionCount()).toBe(0);
        });

        it('should return total handler count across all events', () => {
            bus.on('a', vi.fn());
            bus.on('a', vi.fn());
            bus.on('b', vi.fn());

            expect(bus.getSubscriptionCount()).toBe(3);
        });
    });

    describe('hasSubscribers', () => {
        it('should return false for events with no subscribers', () => {
            expect(bus.hasSubscribers('test')).toBe(false);
        });

        it('should return true for events with subscribers', () => {
            bus.on('test', vi.fn());
            expect(bus.hasSubscribers('test')).toBe(true);
        });

        it('should return false after the only subscriber is removed', () => {
            const handler = vi.fn();
            bus.on('test', handler);
            bus.off('test', handler);

            expect(bus.hasSubscribers('test')).toBe(false);
        });
    });
});
