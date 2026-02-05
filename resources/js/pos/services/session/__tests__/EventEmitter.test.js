/**
 * EventEmitter Unit Tests
 *
 * Tests for the pub/sub event system used throughout
 * the session management module.
 *
 * @group unit
 * @group session
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { EventEmitter, getSessionEventEmitter } from '../EventEmitter.js';

describe('EventEmitter', () => {
    let emitter;

    beforeEach(() => {
        emitter = new EventEmitter({ debug: false });
    });

    afterEach(() => {
        emitter.destroy();
    });

    // ==================== BASIC SUBSCRIPTION ====================

    describe('on()', () => {
        it('should subscribe to events', () => {
            const callback = vi.fn();
            emitter.on('test', callback);

            emitter.emit('test', { data: 'value' });

            expect(callback).toHaveBeenCalledWith(
                { data: 'value' },
                expect.objectContaining({ event: 'test' })
            );
        });

        it('should call callback with event metadata', () => {
            const callback = vi.fn();
            emitter.on('test', callback);

            emitter.emit('test', 'data');

            expect(callback).toHaveBeenCalledWith(
                'data',
                expect.objectContaining({
                    event: 'test',
                    timestamp: expect.any(Number),
                })
            );
        });

        it('should return unsubscribe function', () => {
            const callback = vi.fn();
            const unsubscribe = emitter.on('test', callback);

            expect(typeof unsubscribe).toBe('function');

            unsubscribe();
            emitter.emit('test', 'data');

            expect(callback).not.toHaveBeenCalled();
        });

        it('should throw on non-function callback', () => {
            expect(() => emitter.on('test', 'not a function')).toThrow(TypeError);
            expect(() => emitter.on('test', null)).toThrow(TypeError);
        });

        it('should allow multiple subscribers', () => {
            const callback1 = vi.fn();
            const callback2 = vi.fn();

            emitter.on('test', callback1);
            emitter.on('test', callback2);

            emitter.emit('test', 'data');

            expect(callback1).toHaveBeenCalled();
            expect(callback2).toHaveBeenCalled();
        });
    });

    // ==================== ONCE SUBSCRIPTION ====================

    describe('once()', () => {
        it('should call callback only once', () => {
            const callback = vi.fn();
            emitter.once('test', callback);

            emitter.emit('test', 'first');
            emitter.emit('test', 'second');

            expect(callback).toHaveBeenCalledTimes(1);
            expect(callback).toHaveBeenCalledWith('first', expect.any(Object));
        });

        it('should return unsubscribe function', () => {
            const callback = vi.fn();
            const unsubscribe = emitter.once('test', callback);

            unsubscribe();
            emitter.emit('test', 'data');

            expect(callback).not.toHaveBeenCalled();
        });
    });

    // ==================== UNSUBSCRIPTION ====================

    describe('off()', () => {
        it('should remove specific callback', () => {
            const callback1 = vi.fn();
            const callback2 = vi.fn();

            emitter.on('test', callback1);
            emitter.on('test', callback2);

            emitter.off('test', callback1);
            emitter.emit('test', 'data');

            expect(callback1).not.toHaveBeenCalled();
            expect(callback2).toHaveBeenCalled();
        });

        it('should return true when callback was removed', () => {
            const callback = vi.fn();
            emitter.on('test', callback);

            const result = emitter.off('test', callback);

            expect(result).toBe(true);
        });

        it('should return false when callback not found', () => {
            const callback = vi.fn();

            const result = emitter.off('test', callback);

            expect(result).toBe(false);
        });
    });

    describe('removeAllListeners()', () => {
        it('should remove all listeners for specific event', () => {
            const callback1 = vi.fn();
            const callback2 = vi.fn();
            const callback3 = vi.fn();

            emitter.on('test1', callback1);
            emitter.on('test1', callback2);
            emitter.on('test2', callback3);

            emitter.removeAllListeners('test1');

            emitter.emit('test1', 'data');
            emitter.emit('test2', 'data');

            expect(callback1).not.toHaveBeenCalled();
            expect(callback2).not.toHaveBeenCalled();
            expect(callback3).toHaveBeenCalled();
        });

        it('should remove all listeners when no event specified', () => {
            const callback1 = vi.fn();
            const callback2 = vi.fn();

            emitter.on('test1', callback1);
            emitter.on('test2', callback2);

            emitter.removeAllListeners();

            emitter.emit('test1', 'data');
            emitter.emit('test2', 'data');

            expect(callback1).not.toHaveBeenCalled();
            expect(callback2).not.toHaveBeenCalled();
        });
    });

    // ==================== EMIT ====================

    describe('emit()', () => {
        it('should return true when listeners called', () => {
            emitter.on('test', vi.fn());

            const result = emitter.emit('test', 'data');

            expect(result).toBe(true);
        });

        it('should return false when no listeners', () => {
            const result = emitter.emit('nonexistent', 'data');

            expect(result).toBe(false);
        });

        it('should not crash on callback error', () => {
            const errorCallback = vi.fn(() => {
                throw new Error('Callback error');
            });
            const successCallback = vi.fn();

            emitter.on('test', errorCallback);
            emitter.on('test', successCallback);

            expect(() => emitter.emit('test', 'data')).not.toThrow();
            expect(successCallback).toHaveBeenCalled();
        });
    });

    // ==================== PAUSE/RESUME ====================

    describe('pause() and resume()', () => {
        it('should queue events when paused', () => {
            const callback = vi.fn();
            emitter.on('test', callback);

            emitter.pause();
            emitter.emit('test', 'data1');
            emitter.emit('test', 'data2');

            expect(callback).not.toHaveBeenCalled();
        });

        it('should flush queued events on resume', () => {
            const callback = vi.fn();
            emitter.on('test', callback);

            emitter.pause();
            emitter.emit('test', 'data1');
            emitter.emit('test', 'data2');
            emitter.resume();

            expect(callback).toHaveBeenCalledTimes(2);
        });
    });

    // ==================== HISTORY ====================

    describe('event history', () => {
        it('should record event history', () => {
            emitter.emit('test1', 'data1');
            emitter.emit('test2', 'data2');

            const history = emitter.getHistory();

            expect(history).toHaveLength(2);
            expect(history[0].event).toBe('test1');
            expect(history[1].event).toBe('test2');
        });

        it('should filter history by event name', () => {
            emitter.emit('test1', 'data1');
            emitter.emit('test2', 'data2');
            emitter.emit('test1', 'data3');

            const history = emitter.getHistory('test1');

            expect(history).toHaveLength(2);
            expect(history.every(h => h.event === 'test1')).toBe(true);
        });

        it('should limit history size', () => {
            const smallEmitter = new EventEmitter({ maxHistorySize: 5 });

            for (let i = 0; i < 10; i++) {
                smallEmitter.emit('test', i);
            }

            const history = smallEmitter.getHistory();
            expect(history).toHaveLength(5);

            smallEmitter.destroy();
        });

        it('should sanitize sensitive data in history', () => {
            emitter.emit('test', { token: 'secret123', name: 'public' });

            const history = emitter.getHistory();

            expect(history[0].data.token).toBe('[REDACTED]');
            expect(history[0].data.name).toBe('public');
        });

        it('should clear history', () => {
            emitter.emit('test', 'data');
            emitter.clearHistory();

            expect(emitter.getHistory()).toHaveLength(0);
        });
    });

    // ==================== LISTENER COUNT ====================

    describe('listenerCount()', () => {
        it('should return correct count', () => {
            emitter.on('test', vi.fn());
            emitter.on('test', vi.fn());
            emitter.once('test', vi.fn());

            expect(emitter.listenerCount('test')).toBe(3);
        });

        it('should return 0 for unknown event', () => {
            expect(emitter.listenerCount('unknown')).toBe(0);
        });
    });

    // ==================== EVENT NAMES ====================

    describe('eventNames()', () => {
        it('should return all registered event names', () => {
            emitter.on('event1', vi.fn());
            emitter.on('event2', vi.fn());
            emitter.once('event3', vi.fn());

            const names = emitter.eventNames();

            expect(names).toContain('event1');
            expect(names).toContain('event2');
            expect(names).toContain('event3');
        });
    });

    // ==================== WAIT FOR ====================

    describe('waitFor()', () => {
        it('should resolve when event is emitted', async () => {
            const promise = emitter.waitFor('test');

            setTimeout(() => emitter.emit('test', 'data'), 10);

            const result = await promise;
            expect(result).toBe('data');
        });

        it('should reject on timeout', async () => {
            vi.useFakeTimers();

            const promise = emitter.waitFor('test', 100);

            vi.advanceTimersByTime(150);

            await expect(promise).rejects.toThrow(/timeout/i);

            vi.useRealTimers();
        });
    });

    // ==================== NAMESPACE ====================

    describe('namespace()', () => {
        it('should prefix events', () => {
            const callback = vi.fn();
            const ns = emitter.namespace('myns');

            ns.on('event', callback);
            ns.emit('event', 'data');

            expect(callback).toHaveBeenCalled();
        });

        it('should keep namespaced events separate', () => {
            const callback1 = vi.fn();
            const callback2 = vi.fn();

            const ns1 = emitter.namespace('ns1');
            const ns2 = emitter.namespace('ns2');

            ns1.on('event', callback1);
            ns2.on('event', callback2);

            ns1.emit('event', 'data');

            expect(callback1).toHaveBeenCalled();
            expect(callback2).not.toHaveBeenCalled();
        });
    });

    // ==================== DESTROY ====================

    describe('destroy()', () => {
        it('should remove all listeners', () => {
            const callback = vi.fn();
            emitter.on('test', callback);

            emitter.destroy();
            emitter.emit('test', 'data');

            expect(callback).not.toHaveBeenCalled();
        });

        it('should clear history', () => {
            emitter.emit('test', 'data');
            emitter.destroy();

            expect(emitter.getHistory()).toHaveLength(0);
        });
    });

    // ==================== GLOBAL EMITTER ====================

    describe('getSessionEventEmitter()', () => {
        it('should return singleton instance', () => {
            const emitter1 = getSessionEventEmitter();
            const emitter2 = getSessionEventEmitter();

            expect(emitter1).toBe(emitter2);
        });
    });
});
