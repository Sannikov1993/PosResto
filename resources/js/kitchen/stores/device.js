/**
 * Device Store
 *
 * Pinia store for managing kitchen device state.
 * Handles device registration, linking, and station configuration.
 *
 * @module kitchen/stores/device
 */

import { defineStore } from 'pinia';
import { deviceApi } from '../services/api/deviceApi.js';
import { DEVICE_STATUS, DEVICE_STORAGE_KEYS, isDeviceReady } from '../constants/deviceStatus.js';
import { generateUUID } from '../utils/uuid.js';
import { setTimezone } from '../utils/time.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('KitchenDevice');

/**
 * @typedef {import('../types').KitchenDevice} KitchenDevice
 * @typedef {import('../types').KitchenStation} KitchenStation
 */

export const useDeviceStore = defineStore('kitchen-device', {
    state: () => ({
        /** @type {string|null} */
        deviceId: null,

        /** @type {string} */
        status: DEVICE_STATUS.LOADING,

        /** @type {KitchenDevice|null} */
        deviceData: null,

        /** @type {KitchenStation|null} */
        currentStation: null,

        /** @type {string|null} */
        stationSlug: null,

        /** @type {string} Restaurant timezone (e.g., 'Asia/Yekaterinburg') */
        timezone: 'UTC',

        /** @type {boolean} */
        isLinking: false,

        /** @type {string|null} */
        linkingError: null,

        /** @type {boolean} */
        isCheckingStatus: false,
    }),

    getters: {
        /**
         * Check if device is fully configured and ready
         * @returns {boolean}
         */
        isConfigured: (state) => {
            return isDeviceReady(state.status);
        },

        /**
         * Check if device needs linking
         * @returns {boolean}
         */
        needsLinking: (state) => {
            return state.status === DEVICE_STATUS.NOT_LINKED;
        },

        /**
         * Check if device is pending configuration
         * @returns {boolean}
         */
        isPending: (state) => {
            return state.status === DEVICE_STATUS.PENDING;
        },

        /**
         * Check if device is disabled
         * @returns {boolean}
         */
        isDisabled: (state) => {
            return state.status === DEVICE_STATUS.DISABLED;
        },

        /**
         * Check if device is loading
         * @returns {boolean}
         */
        isLoading: (state) => {
            return state.status === DEVICE_STATUS.LOADING;
        },

        /**
         * Get station notification sound
         * @returns {string|null}
         */
        stationSound: (state) => {
            return state.currentStation?.notification_sound || null;
        },

        /**
         * Get station display name
         * @returns {string}
         */
        stationName: (state) => {
            return state.currentStation?.name || 'Кухня';
        },

        /**
         * Get station icon
         * @returns {string}
         */
        stationIcon: (state) => {
            return state.currentStation?.icon || '🍳';
        },
    },

    actions: {
        /**
         * Initialize device - get or create device ID and check status
         */
        async initialize() {
            this.deviceId = this._getOrCreateDeviceId();
            await this.checkStatus();
        },

        /**
         * Get device ID from storage or create new one
         * @returns {string}
         */
        _getOrCreateDeviceId() {
            let id = localStorage.getItem(DEVICE_STORAGE_KEYS.DEVICE_ID);
            if (!id) {
                id = generateUUID();
                localStorage.setItem(DEVICE_STORAGE_KEYS.DEVICE_ID, id);
            }
            return id;
        },

        /**
         * Check device status with API
         */
        async checkStatus() {
            if (!this.deviceId) {
                this.status = DEVICE_STATUS.NOT_LINKED;
                return;
            }

            this.isCheckingStatus = true;

            try {
                const result = await deviceApi.checkDeviceStatus(this.deviceId);

                this.status = result.status;
                this.deviceData = result.device;

                // Extract station from device data FIRST (needed for orders fetch)
                if (result.device?.kitchen_station) {
                    this.currentStation = result.device.kitchen_station;
                    this.stationSlug = result.device.kitchen_station.slug;
                }

                // Extract timezone from device data (restaurant's timezone)
                // This ensures all time calculations use restaurant's timezone, not browser's
                if (result.device?.timezone) {
                    const previousTimezone = this.timezone;
                    this.timezone = result.device.timezone;
                    setTimezone(result.device.timezone);

                    // If timezone changed (especially from initial UTC), reset orders store
                    // This fixes the race condition where selectedDate is computed with UTC
                    // before restaurant's timezone is loaded from API
                    if (previousTimezone !== result.device.timezone) {
                        log.debug('Timezone changed from', previousTimezone, 'to', result.device.timezone);
                        // Import and reset orders store date
                        // Using dynamic import to avoid circular dependency
                        try {
                            const { useOrdersStore } = await import('./orders.js');
                            const ordersStore = useOrdersStore();
                            ordersStore.resetToToday();

                            // If device is configured, also refetch orders with correct date
                            if (this.status === DEVICE_STATUS.CONFIGURED) {
                                log.debug('Refetching orders with correct timezone date');
                                ordersStore.fetchOrders(this.deviceId, this.stationSlug);
                            }
                        } catch (importError) {
                            log.error('Failed to reset orders date:', importError);
                        }
                    }
                }
            } catch (error) {
                log.error('Failed to check device status:', error);
                // Don't change status on error - keep current state
            } finally {
                this.isCheckingStatus = false;
            }
        },

        /**
         * Link device using a 6-digit code
         * @param {string} code - 6-digit linking code
         * @returns {Promise<boolean>} True if linking successful
         */
        async linkDevice(code) {
            if (!this.deviceId) {
                this.linkingError = 'No device ID';
                return false;
            }

            this.isLinking = true;
            this.linkingError = null;

            try {
                const device = await deviceApi.linkDevice(this.deviceId, code);
                this.deviceData = device;

                // Check status after linking
                await this.checkStatus();

                return true;
            } catch (error) {
                this.linkingError = error.getUserMessage?.() || error.message;
                return false;
            } finally {
                this.isLinking = false;
            }
        },

        /**
         * Clear linking error
         */
        clearLinkingError() {
            this.linkingError = null;
        },

        /**
         * Load station information by slug
         * @param {string} slug - Station slug
         */
        async loadStation(slug) {
            if (!slug) {
                this.currentStation = null;
                this.stationSlug = null;
                return;
            }

            try {
                const station = await deviceApi.getStationBySlug(slug);
                this.currentStation = station;
                this.stationSlug = slug;
            } catch (error) {
                log.error('Failed to load station:', error);
            }
        },

        /**
         * Reset device (clear all data)
         */
        reset() {
            localStorage.removeItem(DEVICE_STORAGE_KEYS.DEVICE_ID);
            localStorage.removeItem(DEVICE_STORAGE_KEYS.DEVICE_CONFIG);
            localStorage.removeItem(DEVICE_STORAGE_KEYS.STATION_SLUG);

            this.deviceId = null;
            this.status = DEVICE_STATUS.LOADING;
            this.deviceData = null;
            this.currentStation = null;
            this.stationSlug = null;
            this.linkingError = null;
        },
    },
});
