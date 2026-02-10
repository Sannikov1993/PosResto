/**
 * Settings Store Unit Tests
 *
 * @group unit
 * @group kitchen
 * @group stores
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useSettingsStore } from '../../stores/settings.js';

// Mock the logger
vi.mock('../../../shared/services/logger.js', () => ({
    createLogger: () => ({
        info: vi.fn(),
        error: vi.fn(),
        warn: vi.fn(),
        debug: vi.fn(),
    }),
}));

// Mock localStorage
const localStorageMock = (() => {
    let store: Record<string, string> = {};
    return {
        getItem: vi.fn((key: string) => store[key] || null),
        setItem: vi.fn((key: string, value: string) => { store[key] = value; }),
        removeItem: vi.fn((key: string) => { delete store[key]; }),
        clear: vi.fn(() => { store = {}; }),
        _getStore: () => store,
    };
})();
Object.defineProperty(global, 'localStorage', { value: localStorageMock });

describe('Settings Store', () => {
    let store: ReturnType<typeof useSettingsStore>;

    beforeEach(() => {
        localStorageMock.clear();
        vi.clearAllMocks();
        setActivePinia(createPinia());
        store = useSettingsStore();
    });

    // ==================== Initial State ====================

    describe('initial state', () => {
        it('should have sound enabled by default', () => {
            expect(store.soundEnabled).toBe(true);
        });

        it('should have compact mode disabled by default', () => {
            expect(store.compactMode).toBe(false);
        });

        it('should have focus mode disabled by default', () => {
            expect(store.focusMode).toBe(false);
        });

        it('should have single column mode disabled by default', () => {
            expect(store.singleColumnMode).toBe(false);
        });

        it('should have active column set to "new" by default', () => {
            expect(store.activeColumn).toBe('new');
        });

        it('should not be fullscreen by default', () => {
            expect(store.isFullscreen).toBe(false);
        });

        it('should have auto responsive enabled by default', () => {
            expect(store.autoResponsiveEnabled).toBe(true);
        });

        it('should have layout source set to "auto" by default', () => {
            expect(store.layoutSource).toBe('auto');
        });

        it('should load settings from localStorage on initialization', () => {
            localStorageMock.setItem('kitchen_settings', JSON.stringify({
                soundEnabled: false,
                compactMode: true,
                focusMode: true,
            }));

            // Create a new pinia and store to trigger re-initialization
            setActivePinia(createPinia());
            const newStore = useSettingsStore();

            expect(newStore.soundEnabled).toBe(false);
            expect(newStore.compactMode).toBe(true);
            expect(newStore.focusMode).toBe(true);
        });
    });

    // ==================== Getters ====================

    describe('getters', () => {
        it('allSettings should return all current settings', () => {
            const settings = store.allSettings;

            expect(settings).toEqual({
                soundEnabled: true,
                compactMode: false,
                focusMode: false,
                singleColumnMode: false,
                activeColumn: 'new',
                autoResponsiveEnabled: true,
                layoutSource: 'auto',
            });
        });

        it('allSettings should reflect changed state', () => {
            store.soundEnabled = false;
            store.compactMode = true;

            const settings = store.allSettings;

            expect(settings.soundEnabled).toBe(false);
            expect(settings.compactMode).toBe(true);
        });
    });

    // ==================== Actions ====================

    describe('actions', () => {
        describe('toggleSound', () => {
            it('should toggle sound from enabled to disabled', () => {
                const result = store.toggleSound();

                expect(result).toBe(false);
                expect(store.soundEnabled).toBe(false);
            });

            it('should toggle sound from disabled to enabled', () => {
                store.soundEnabled = false;
                const result = store.toggleSound();

                expect(result).toBe(true);
                expect(store.soundEnabled).toBe(true);
            });

            it('should persist after toggling sound', () => {
                store.toggleSound();

                expect(localStorageMock.setItem).toHaveBeenCalledWith(
                    'kitchen_settings',
                    expect.any(String)
                );
            });
        });

        describe('setSoundEnabled', () => {
            it('should set sound to a specific value', () => {
                store.setSoundEnabled(false);
                expect(store.soundEnabled).toBe(false);

                store.setSoundEnabled(true);
                expect(store.soundEnabled).toBe(true);
            });
        });

        describe('toggleCompactMode', () => {
            it('should toggle compact mode and return new value', () => {
                const result = store.toggleCompactMode();

                expect(result).toBe(true);
                expect(store.compactMode).toBe(true);
            });

            it('should persist after toggling compact mode', () => {
                store.toggleCompactMode();

                expect(localStorageMock.setItem).toHaveBeenCalled();
            });
        });

        describe('toggleFocusMode', () => {
            it('should toggle focus mode and return new value', () => {
                const result = store.toggleFocusMode();

                expect(result).toBe(true);
                expect(store.focusMode).toBe(true);
            });
        });

        describe('toggleSingleColumnMode', () => {
            it('should toggle single column mode', () => {
                const result = store.toggleSingleColumnMode();

                expect(result).toBe(true);
                expect(store.singleColumnMode).toBe(true);
            });

            it('should set layout source to manual when toggling', () => {
                store.toggleSingleColumnMode();

                expect(store.layoutSource).toBe('manual');
            });
        });

        describe('setSingleColumnModeAuto', () => {
            it('should set single column mode when layout source is auto', () => {
                store.layoutSource = 'auto';
                store.setSingleColumnModeAuto(true);

                expect(store.singleColumnMode).toBe(true);
            });

            it('should not change single column mode when layout source is manual', () => {
                store.layoutSource = 'manual';
                store.singleColumnMode = false;
                store.setSingleColumnModeAuto(true);

                expect(store.singleColumnMode).toBe(false);
            });
        });

        describe('setSingleColumnModeManual', () => {
            it('should set single column mode and mark layout as manual', () => {
                store.setSingleColumnModeManual(true);

                expect(store.singleColumnMode).toBe(true);
                expect(store.layoutSource).toBe('manual');
            });
        });

        describe('resetLayoutToAuto', () => {
            it('should set layout source back to auto', () => {
                store.layoutSource = 'manual';
                store.resetLayoutToAuto();

                expect(store.layoutSource).toBe('auto');
            });
        });

        describe('toggleAutoResponsive', () => {
            it('should toggle auto responsive and return new value', () => {
                const result = store.toggleAutoResponsive();

                expect(result).toBe(false);
                expect(store.autoResponsiveEnabled).toBe(false);
            });

            it('should set layout source to auto when enabling auto responsive', () => {
                store.autoResponsiveEnabled = false;
                store.layoutSource = 'manual';

                store.toggleAutoResponsive();

                expect(store.autoResponsiveEnabled).toBe(true);
                expect(store.layoutSource).toBe('auto');
            });

            it('should not change layout source when disabling auto responsive', () => {
                store.autoResponsiveEnabled = true;
                store.layoutSource = 'auto';

                store.toggleAutoResponsive();

                expect(store.autoResponsiveEnabled).toBe(false);
                expect(store.layoutSource).toBe('auto');
            });
        });

        describe('setActiveColumn', () => {
            it('should set active column and persist', () => {
                store.setActiveColumn('cooking');

                expect(store.activeColumn).toBe('cooking');
                expect(localStorageMock.setItem).toHaveBeenCalled();
            });
        });

        describe('toggleFullscreen', () => {
            it('should request fullscreen when not in fullscreen', async () => {
                const requestFullscreen = vi.fn().mockResolvedValue(undefined);
                Object.defineProperty(document.documentElement, 'requestFullscreen', {
                    value: requestFullscreen,
                    writable: true,
                    configurable: true,
                });
                Object.defineProperty(document, 'fullscreenElement', {
                    value: null,
                    writable: true,
                    configurable: true,
                });

                await store.toggleFullscreen();

                expect(requestFullscreen).toHaveBeenCalled();
                expect(store.isFullscreen).toBe(true);
            });

            it('should exit fullscreen when in fullscreen', async () => {
                const exitFullscreen = vi.fn().mockResolvedValue(undefined);
                Object.defineProperty(document, 'exitFullscreen', {
                    value: exitFullscreen,
                    writable: true,
                    configurable: true,
                });
                Object.defineProperty(document, 'fullscreenElement', {
                    value: document.documentElement,
                    writable: true,
                    configurable: true,
                });

                await store.toggleFullscreen();

                expect(exitFullscreen).toHaveBeenCalled();
                expect(store.isFullscreen).toBe(false);
            });
        });

        describe('resetToDefaults', () => {
            it('should reset all settings to default values', () => {
                // Change everything first
                store.soundEnabled = false;
                store.compactMode = true;
                store.focusMode = true;
                store.singleColumnMode = true;
                store.activeColumn = 'cooking';
                store.autoResponsiveEnabled = false;
                store.layoutSource = 'manual';

                store.resetToDefaults();

                expect(store.soundEnabled).toBe(true);
                expect(store.compactMode).toBe(false);
                expect(store.focusMode).toBe(false);
                expect(store.singleColumnMode).toBe(false);
                expect(store.activeColumn).toBe('new');
                expect(store.autoResponsiveEnabled).toBe(true);
                expect(store.layoutSource).toBe('auto');
            });

            it('should persist defaults after reset', () => {
                store.resetToDefaults();

                expect(localStorageMock.setItem).toHaveBeenCalledWith(
                    'kitchen_settings',
                    expect.any(String)
                );
            });
        });

        describe('importSettings', () => {
            it('should import partial settings', () => {
                store.importSettings({
                    soundEnabled: false,
                    compactMode: true,
                });

                expect(store.soundEnabled).toBe(false);
                expect(store.compactMode).toBe(true);
                // Unchanged settings remain at defaults
                expect(store.focusMode).toBe(false);
            });

            it('should import all settings', () => {
                store.importSettings({
                    soundEnabled: false,
                    compactMode: true,
                    focusMode: true,
                    singleColumnMode: true,
                    activeColumn: 'ready',
                    autoResponsiveEnabled: false,
                    layoutSource: 'manual',
                });

                expect(store.soundEnabled).toBe(false);
                expect(store.compactMode).toBe(true);
                expect(store.focusMode).toBe(true);
                expect(store.singleColumnMode).toBe(true);
                expect(store.activeColumn).toBe('ready');
                expect(store.autoResponsiveEnabled).toBe(false);
                expect(store.layoutSource).toBe('manual');
            });

            it('should persist after importing', () => {
                store.importSettings({ soundEnabled: false });

                expect(localStorageMock.setItem).toHaveBeenCalled();
            });

            it('should not change settings for properties not provided', () => {
                store.soundEnabled = false;
                store.compactMode = true;

                store.importSettings({ focusMode: true });

                expect(store.soundEnabled).toBe(false);
                expect(store.compactMode).toBe(true);
                expect(store.focusMode).toBe(true);
            });
        });
    });

    // ==================== Persistence ====================

    describe('persistence', () => {
        it('should save correct JSON to localStorage', () => {
            store.toggleSound(); // soundEnabled -> false

            const savedValue = localStorageMock.setItem.mock.calls[0][1];
            const parsed = JSON.parse(savedValue);

            expect(parsed.soundEnabled).toBe(false);
            expect(parsed.compactMode).toBe(false);
            expect(parsed.focusMode).toBe(false);
        });

        it('should handle corrupt localStorage data gracefully', () => {
            localStorageMock.getItem.mockReturnValue('not-valid-json');

            setActivePinia(createPinia());
            const newStore = useSettingsStore();

            // Should fall back to defaults
            expect(newStore.soundEnabled).toBe(true);
            expect(newStore.compactMode).toBe(false);
        });
    });
});
