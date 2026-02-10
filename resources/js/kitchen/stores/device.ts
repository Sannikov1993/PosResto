/**
 * Device Store
 *
 * Pinia store for managing kitchen device state.
 *
 * @module kitchen/stores/device
 */

import { defineStore } from 'pinia';
import { deviceApi } from '../services/api/deviceApi.js';
import { DEVICE_STATUS, DEVICE_STORAGE_KEYS, isDeviceReady } from '../constants/deviceStatus.js';
import { generateUUID } from '../utils/uuid.js';
import { setTimezone } from '../utils/time.js';
import { createLogger } from '../../shared/services/logger.js';
import type { KitchenDevice, KitchenStation } from '../types/index.js';

const log = createLogger('KitchenDevice');

export const useDeviceStore = defineStore('kitchen-device', {
    state: () => ({
        deviceId: null as string | null,
        status: DEVICE_STATUS.LOADING as string,
        deviceData: null as KitchenDevice | null,
        currentStation: null as KitchenStation | null,
        stationSlug: null as string | null,
        timezone: 'UTC',
        isLinking: false,
        linkingError: null as string | null,
        isCheckingStatus: false,
    }),

    getters: {
        isConfigured: (state): boolean => {
            return isDeviceReady(state.status);
        },

        needsLinking: (state): boolean => {
            return state.status === DEVICE_STATUS.NOT_LINKED;
        },

        isPending: (state): boolean => {
            return state.status === DEVICE_STATUS.PENDING;
        },

        isDisabled: (state): boolean => {
            return state.status === DEVICE_STATUS.DISABLED;
        },

        isLoading: (state): boolean => {
            return state.status === DEVICE_STATUS.LOADING;
        },

        stationSound: (state): string | null => {
            return state.currentStation?.notification_sound || null;
        },

        stationName: (state): string => {
            return state.currentStation?.name || 'Кухня';
        },

        stationIcon: (state): string => {
            return state.currentStation?.icon || '🍳';
        },
    },

    actions: {
        async initialize() {
            this.deviceId = this._getOrCreateDeviceId();
            await this.checkStatus();
        },

        _getOrCreateDeviceId(): string {
            let id = localStorage.getItem(DEVICE_STORAGE_KEYS.DEVICE_ID);
            if (!id) {
                id = generateUUID();
                localStorage.setItem(DEVICE_STORAGE_KEYS.DEVICE_ID, id);
            }
            return id;
        },

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

                if (result.device?.kitchen_station) {
                    this.currentStation = result.device.kitchen_station;
                    this.stationSlug = result.device.kitchen_station.slug;
                }

                if (result.device?.timezone) {
                    const previousTimezone = this.timezone;
                    this.timezone = result.device.timezone;
                    setTimezone(result.device.timezone);

                    if (previousTimezone !== result.device.timezone) {
                        log.debug('Timezone changed from', previousTimezone, 'to', result.device.timezone);
                        try {
                            const { useOrdersStore } = await import('./orders.js');
                            const ordersStore = useOrdersStore();
                            ordersStore.resetToToday();

                            if (this.status === DEVICE_STATUS.CONFIGURED) {
                                log.debug('Refetching orders with correct timezone date');
                                ordersStore.fetchOrders(this.deviceId!, this.stationSlug || undefined);
                            }
                        } catch (importError: any) {
                            log.error('Failed to reset orders date:', importError);
                        }
                    }
                }
            } catch (error: any) {
                log.error('Failed to check device status:', error);
            } finally {
                this.isCheckingStatus = false;
            }
        },

        async linkDevice(code: string): Promise<boolean> {
            if (!this.deviceId) {
                this.linkingError = 'No device ID';
                return false;
            }

            this.isLinking = true;
            this.linkingError = null;

            try {
                const device = await deviceApi.linkDevice(this.deviceId, code);
                this.deviceData = device;

                await this.checkStatus();

                return true;
            } catch (error: any) {
                this.linkingError = error.getUserMessage?.() || error.message;
                return false;
            } finally {
                this.isLinking = false;
            }
        },

        clearLinkingError() {
            this.linkingError = null;
        },

        async loadStation(slug: string) {
            if (!slug) {
                this.currentStation = null;
                this.stationSlug = null;
                return;
            }

            try {
                const station = await deviceApi.getStationBySlug(slug);
                this.currentStation = station;
                this.stationSlug = slug;
            } catch (error: any) {
                log.error('Failed to load station:', error);
            }
        },

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
