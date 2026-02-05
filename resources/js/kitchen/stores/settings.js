/**
 * Settings Store
 *
 * Pinia store for managing user preferences and display settings.
 * Persists settings to localStorage.
 *
 * @module kitchen/stores/settings
 */

import { defineStore } from 'pinia';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('KitchenSettings');

const STORAGE_KEY = 'kitchen_settings';

/**
 * @typedef {Object} KitchenSettings
 * @property {boolean} soundEnabled - Whether sound notifications are enabled
 * @property {boolean} compactMode - Compact display mode
 * @property {boolean} focusMode - Focus mode (minimal UI)
 * @property {boolean} singleColumnMode - Single column layout
 * @property {string} activeColumn - Active column in single column mode
 * @property {boolean} autoResponsiveEnabled - Whether auto-responsive mode is enabled
 * @property {'auto'|'manual'} layoutSource - Source of layout settings
 */

/**
 * Load settings from localStorage
 * @returns {Partial<KitchenSettings>}
 */
function loadSettings() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            return JSON.parse(stored);
        }
    } catch (e) {
        log.error('Failed to load settings:', e);
    }
    return {};
}

/**
 * Save settings to localStorage
 * @param {KitchenSettings} settings
 */
function saveSettings(settings) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
    } catch (e) {
        log.error('Failed to save settings:', e);
    }
}

export const useSettingsStore = defineStore('kitchen-settings', {
    state: () => {
        const saved = loadSettings();
        return {
            /** @type {boolean} Sound notifications enabled */
            soundEnabled: saved.soundEnabled ?? true,

            /** @type {boolean} Compact display mode */
            compactMode: saved.compactMode ?? false,

            /** @type {boolean} Focus mode (minimal distractions) */
            focusMode: saved.focusMode ?? false,

            /** @type {boolean} Single column layout (for tablets) */
            singleColumnMode: saved.singleColumnMode ?? false,

            /** @type {'new'|'cooking'|'ready'} Active column in single column mode */
            activeColumn: saved.activeColumn ?? 'new',

            /** @type {boolean} Fullscreen mode */
            isFullscreen: false,

            /** @type {boolean} Auto-responsive mode enabled */
            autoResponsiveEnabled: saved.autoResponsiveEnabled ?? true,

            /** @type {'auto'|'manual'} Source of layout settings */
            layoutSource: saved.layoutSource ?? 'auto',
        };
    },

    getters: {
        /**
         * Get all persistable settings
         * @returns {KitchenSettings}
         */
        allSettings: (state) => ({
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
        /**
         * Persist current settings to localStorage
         */
        _persist() {
            saveSettings(this.allSettings);
        },

        /**
         * Toggle sound notifications
         * @returns {boolean} New state
         */
        toggleSound() {
            this.soundEnabled = !this.soundEnabled;
            this._persist();
            return this.soundEnabled;
        },

        /**
         * Set sound enabled state
         * @param {boolean} enabled
         */
        setSoundEnabled(enabled) {
            this.soundEnabled = enabled;
            this._persist();
        },

        /**
         * Toggle compact mode
         * @returns {boolean} New state
         */
        toggleCompactMode() {
            this.compactMode = !this.compactMode;
            this._persist();
            return this.compactMode;
        },

        /**
         * Toggle focus mode
         * @returns {boolean} New state
         */
        toggleFocusMode() {
            this.focusMode = !this.focusMode;
            this._persist();
            return this.focusMode;
        },

        /**
         * Toggle single column mode (manual override)
         * @returns {boolean} New state
         */
        toggleSingleColumnMode() {
            this.singleColumnMode = !this.singleColumnMode;
            this.layoutSource = 'manual';
            this._persist();
            return this.singleColumnMode;
        },

        /**
         * Set single column mode from auto-responsive (doesn't change layoutSource)
         * @param {boolean} enabled
         */
        setSingleColumnModeAuto(enabled) {
            if (this.layoutSource === 'auto') {
                this.singleColumnMode = enabled;
                // Don't persist auto changes to avoid overriding manual settings
            }
        },

        /**
         * Set single column mode manually
         * @param {boolean} enabled
         */
        setSingleColumnModeManual(enabled) {
            this.singleColumnMode = enabled;
            this.layoutSource = 'manual';
            this._persist();
        },

        /**
         * Reset layout to auto-responsive mode
         */
        resetLayoutToAuto() {
            this.layoutSource = 'auto';
            this._persist();
        },

        /**
         * Toggle auto-responsive mode
         * @returns {boolean} New state
         */
        toggleAutoResponsive() {
            this.autoResponsiveEnabled = !this.autoResponsiveEnabled;
            if (this.autoResponsiveEnabled) {
                this.layoutSource = 'auto';
            }
            this._persist();
            return this.autoResponsiveEnabled;
        },

        /**
         * Set active column (in single column mode)
         * @param {'new'|'cooking'|'ready'} column
         */
        setActiveColumn(column) {
            this.activeColumn = column;
            this._persist();
        },

        /**
         * Toggle fullscreen mode
         */
        async toggleFullscreen() {
            try {
                if (!document.fullscreenElement) {
                    await document.documentElement.requestFullscreen();
                    this.isFullscreen = true;
                } else {
                    await document.exitFullscreen();
                    this.isFullscreen = false;
                }
            } catch (e) {
                log.error('Fullscreen toggle failed:', e);
            }
        },

        /**
         * Reset all settings to defaults
         */
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

        /**
         * Import settings
         * @param {Partial<KitchenSettings>} settings
         */
        importSettings(settings) {
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
