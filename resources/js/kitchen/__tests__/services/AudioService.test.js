/**
 * Audio Service Unit Tests
 *
 * @group unit
 * @group kitchen
 * @group services
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { AudioService } from '../../services/audio/AudioService.js';
import { SOUND_TYPE } from '../../constants/sounds.js';

// Mock Web Audio API
class MockAudioContext {
    constructor() {
        this.state = 'running';
        this.currentTime = 0;
    }

    createOscillator() {
        return {
            type: 'sine',
            frequency: { value: 440 },
            connect: vi.fn(),
            start: vi.fn(),
            stop: vi.fn(),
        };
    }

    createGain() {
        return {
            gain: {
                value: 0,
                setValueAtTime: vi.fn(),
                linearRampToValueAtTime: vi.fn(),
                exponentialRampToValueAtTime: vi.fn(),
            },
            connect: vi.fn(),
        };
    }

    resume() {
        this.state = 'running';
        return Promise.resolve();
    }

    close() {
        this.state = 'closed';
        return Promise.resolve();
    }
}

// Setup global mock
global.AudioContext = MockAudioContext;
global.webkitAudioContext = MockAudioContext;

describe('AudioService', () => {
    let service;

    beforeEach(() => {
        service = new AudioService();
    });

    afterEach(() => {
        service.destroy();
    });

    // ==================== Initialization ====================

    describe('initialization', () => {
        it('should start with audio enabled', () => {
            expect(service.enabled).toBe(true);
        });

        it('should not have context initially', () => {
            expect(service._context).toBeNull();
        });

        it('should initialize on user interaction', () => {
            service.initialize();
            service._onUserInteraction();

            expect(service._context).toBeInstanceOf(MockAudioContext);
            expect(service._userInteracted).toBe(true);
        });
    });

    // ==================== Enable/Disable ====================

    describe('enable/disable', () => {
        it('should toggle enabled state', () => {
            expect(service.enabled).toBe(true);

            service.toggle();
            expect(service.enabled).toBe(false);

            service.toggle();
            expect(service.enabled).toBe(true);
        });

        it('should set enabled directly', () => {
            service.enabled = false;
            expect(service.enabled).toBe(false);
        });
    });

    // ==================== Volume ====================

    describe('volume', () => {
        it('should default to 1.0', () => {
            expect(service.volume).toBe(1);
        });

        it('should set volume within 0-1 range', () => {
            service.volume = 0.5;
            expect(service.volume).toBe(0.5);
        });

        it('should clamp volume to 0', () => {
            service.volume = -1;
            expect(service.volume).toBe(0);
        });

        it('should clamp volume to 1', () => {
            service.volume = 2;
            expect(service.volume).toBe(1);
        });
    });

    // ==================== Play Sound ====================

    describe('play()', () => {
        beforeEach(() => {
            // Simulate user interaction
            service.initialize();
            service._onUserInteraction();
        });

        it('should not play when disabled', () => {
            service.enabled = false;
            const synthSpy = vi.spyOn(service._synthesizer, 'synthesize');

            service.play(SOUND_TYPE.BELL);

            expect(synthSpy).not.toHaveBeenCalled();
        });

        it('should play sound when enabled', () => {
            const synthSpy = vi.spyOn(service._synthesizer, 'synthesize');

            service.play(SOUND_TYPE.BELL);

            expect(synthSpy).toHaveBeenCalledWith(SOUND_TYPE.BELL);
        });

        it('should queue sounds before user interaction', () => {
            const newService = new AudioService();
            newService.initialize();

            newService.play(SOUND_TYPE.CHIME);

            expect(newService._pendingSounds).toHaveLength(1);
            expect(newService._pendingSounds[0]).toBe(SOUND_TYPE.CHIME);

            newService.destroy();
        });
    });

    // ==================== Convenience Methods ====================

    describe('convenience methods', () => {
        beforeEach(() => {
            service.initialize();
            service._onUserInteraction();
        });

        it('playNewOrder should play notification sound', () => {
            const playSpy = vi.spyOn(service, 'play');

            service.playNewOrder();

            expect(playSpy).toHaveBeenCalledWith(SOUND_TYPE.BELL);
        });

        it('playOrderReady should play chime sound', () => {
            const playSpy = vi.spyOn(service, 'play');

            service.playOrderReady();

            expect(playSpy).toHaveBeenCalledWith(SOUND_TYPE.CHIME);
        });

        it('playOverdueWarning should play overdue sound', () => {
            const playSpy = vi.spyOn(service, 'play');

            service.playOverdueWarning();

            expect(playSpy).toHaveBeenCalledWith(SOUND_TYPE.OVERDUE);
        });

        it('playStationNotification should use custom sound', () => {
            const playSpy = vi.spyOn(service, 'play');

            service.playStationNotification(SOUND_TYPE.GONG);

            expect(playSpy).toHaveBeenCalledWith(SOUND_TYPE.GONG);
        });

        it('playStationNotification should fallback to bell', () => {
            const playSpy = vi.spyOn(service, 'play');

            service.playStationNotification(null);

            expect(playSpy).toHaveBeenCalledWith(SOUND_TYPE.BELL);
        });
    });

    // ==================== Destroy ====================

    describe('destroy()', () => {
        it('should clean up resources', () => {
            service.initialize();
            service._onUserInteraction();

            service.destroy();

            expect(service._context).toBeNull();
            expect(service._synthesizer).toBeNull();
            expect(service._initialized).toBe(false);
        });
    });
});
