/**
 * Settings Store
 *
 * Pinia store for managing user preferences and display settings.
 *
 * @module kitchen/stores/settings
 */

import { defineStore } from 'pinia';
import { createLogger } from '../../shared/services/logger.js';
import type { KitchenSettings } from '../types/index.js';

const log = createLogger('KitchenSettings');

const STORAGE_KEY = 'kitchen_settings';

function loadSettings(): Partial<KitchenSettings> {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            return JSON.parse(stored);
        }
    } catch (e: any) {
        log.error('Failed to load settings:', e);
    }
    return {} as Record<string, any>;
}

function saveSettings(settings: KitchenSettings): void {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
    } catch (e: any) {
        log.error('Failed to save settings:', e);
    }
}

export const useSettingsStore = defineStore('kitchen-settings', {
    state: () => {
        const saved = loadSettings();
        return {
            soundEnabled: saved.soundEnabled ?? true,
            compactMode: saved.compactMode ?? false,
            focusMode: saved.focusMode ?? false,
            singleColumnMode: saved.singleColumnMode ?? false,
            activeColumn: (saved.activeColumn ?? 'new') as string,
            isFullscreen: false,
            autoResponsiveEnabled: saved.autoResponsiveEnabled ?? true,
            layoutSource: (saved.layoutSource ?? 'auto') as 'auto' | 'manual',
        };
    },

    getters: {
        allSettings: (state): KitchenSettings => ({
            soundEnabled: state.soundEnabled,
            compactMode: state.compactMode,
            focusMode: state.focusMode,
            singleColumnMode: state.singleColumnMode,
            activeColumn: state.activeColumn,
            autoResponsiveEnabled: state.autoResponsiveEnabled,
            layoutSource: state.layoutSource,
        }),
    },

    actions: {
        _persist() {
            saveSettings(this.allSettings);
        },

        toggleSound(): boolean {
            this.soundEnabled = !this.soundEnabled;
            this._persist();
            return this.soundEnabled;
        },

        setSoundEnabled(enabled: boolean) {
            this.soundEnabled = enabled;
            this._persist();
        },

        toggleCompactMode(): boolean {
            this.compactMode = !this.compactMode;
            this._persist();
            return this.compactMode;
        },

        toggleFocusMode(): boolean {
            this.focusMode = !this.focusMode;
            this._persist();
            return this.focusMode;
        },

        toggleSingleColumnMode(): boolean {
            this.singleColumnMode = !this.singleColumnMode;
            this.layoutSource = 'manual';
            this._persist();
            return this.singleColumnMode;
        },

        setSingleColumnModeAuto(enabled: boolean) {
            if (this.layoutSource === 'auto') {
                this.singleColumnMode = enabled;
            }
        },

        setSingleColumnModeManual(enabled: boolean) {
            this.singleColumnMode = enabled;
            this.layoutSource = 'manual';
            this._persist();
        },

        resetLayoutToAuto() {
            this.layoutSource = 'auto';
            this._persist();
        },

        toggleAutoResponsive(): boolean {
            this.autoResponsiveEnabled = !this.autoResponsiveEnabled;
            if (this.autoResponsiveEnabled) {
                this.layoutSource = 'auto';
            }
            this._persist();
            return this.autoResponsiveEnabled;
        },

        setActiveColumn(column: string) {
            this.activeColumn = column;
            this._persist();
        },

        async toggleFullscreen() {
            try {
                if (!document.fullscreenElement) {
                    await document.documentElement.requestFullscreen();
                    this.isFullscreen = true;
                } else {
                    await document.exitFullscreen();
                    this.isFullscreen = false;
                }
            } catch (e: any) {
                log.error('Fullscreen toggle failed:', e);
            }
        },

        resetToDefaults() {
            this.soundEnabled = true;
            this.compactMode = false;
            this.focusMode = false;
            this.singleColumnMode = false;
            this.activeColumn = 'new';
            this.autoResponsiveEnabled = true;
            this.layoutSource = 'auto';
            this._persist();
        },

        importSettings(settings: Partial<KitchenSettings>) {
            if (settings.soundEnabled !== undefined) this.soundEnabled = settings.soundEnabled;
            if (settings.compactMode !== undefined) this.compactMode = settings.compactMode;
            if (settings.focusMode !== undefined) this.focusMode = settings.focusMode;
            if (settings.singleColumnMode !== undefined) this.singleColumnMode = settings.singleColumnMode;
            if (settings.activeColumn !== undefined) this.activeColumn = settings.activeColumn;
            if (settings.autoResponsiveEnabled !== undefined) this.autoResponsiveEnabled = settings.autoResponsiveEnabled;
            if (settings.layoutSource !== undefined) this.layoutSource = settings.layoutSource;
            this._persist();
        },
    },
});
