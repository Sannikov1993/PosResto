/**
 * Device Store Unit Tests
 *
 * @group unit
 * @group kitchen
 * @group stores
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useDeviceStore } from '../../stores/device.js';
import { deviceApi } from '../../services/api/deviceApi.js';
import { DEVICE_STATUS, DEVICE_STORAGE_KEYS } from '../../constants/deviceStatus.js';

// Mock deviceApi
vi.mock('../../services/api/deviceApi.js', () => ({
    deviceApi: {
        checkDeviceStatus: vi.fn(),
        linkDevice: vi.fn(),
        getStationBySlug: vi.fn(),
    },
}));

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

describe('DeviceStore', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useDeviceStore();
        localStorageMock.clear();
        vi.clearAllMocks();
    });

    // ==================== Initial State ====================

    describe('initial state', () => {
        it('should have correct defaults', () => {
            expect(store.deviceId).toBeNull();
            expect(store.status).toBe(DEVICE_STATUS.LOADING);
            expect(store.deviceData).toBeNull();
            expect(store.currentStation).toBeNull();
            expect(store.stationSlug).toBeNull();
            expect(store.timezone).toBe('UTC');
            expect(store.isLinking).toBe(false);
            expect(store.linkingError).toBeNull();
        });
    });

    // ==================== Getters ====================

    describe('getters', () => {
        describe('isConfigured', () => {
            it('should return true when status is CONFIGURED', () => {
                store.status = DEVICE_STATUS.CONFIGURED;
                expect(store.isConfigured).toBe(true);
            });

            it('should return false for other statuses', () => {
                store.status = DEVICE_STATUS.PENDING;
                expect(store.isConfigured).toBe(false);

                store.status = DEVICE_STATUS.NOT_LINKED;
                expect(store.isConfigured).toBe(false);
            });
        });

        describe('needsLinking', () => {
            it('should return true when NOT_LINKED', () => {
                store.status = DEVICE_STATUS.NOT_LINKED;
                expect(store.needsLinking).toBe(true);
            });

            it('should return false for other statuses', () => {
                store.status = DEVICE_STATUS.CONFIGURED;
                expect(store.needsLinking).toBe(false);
            });
        });

        describe('isPending', () => {
            it('should return true when PENDING', () => {
                store.status = DEVICE_STATUS.PENDING;
                expect(store.isPending).toBe(true);
            });
        });

        describe('isDisabled', () => {
            it('should return true when DISABLED', () => {
                store.status = DEVICE_STATUS.DISABLED;
                expect(store.isDisabled).toBe(true);
            });
        });

        describe('stationSound', () => {
            it('should return notification sound from station', () => {
                store.currentStation = { notification_sound: 'bell' };
                expect(store.stationSound).toBe('bell');
            });

            it('should return null when no station', () => {
                expect(store.stationSound).toBeNull();
            });
        });

        describe('stationName', () => {
            it('should return station name', () => {
                store.currentStation = { name: 'Hot Station' };
                expect(store.stationName).toBe('Hot Station');
            });

            it('should return default when no station', () => {
                expect(store.stationName).toBe('Кухня');
            });
        });

        describe('stationIcon', () => {
            it('should return station icon', () => {
                store.currentStation = { icon: '🔥' };
                expect(store.stationIcon).toBe('🔥');
            });

            it('should return default when no station', () => {
                expect(store.stationIcon).toBe('🍳');
            });
        });
    });

    // ==================== Actions ====================

    describe('initialize()', () => {
        it('should get or create device ID and check status', async () => {
            deviceApi.checkDeviceStatus.mockResolvedValue({
                status: DEVICE_STATUS.CONFIGURED,
                device: {
                    device_id: 'device-123',
                    timezone: 'Asia/Yekaterinburg',
                    kitchen_station: {
                        id: 1,
                        name: 'Hot Station',
                        slug: 'hot',
                    },
                },
            });

            await store.initialize();

            expect(store.deviceId).toBeTruthy();
            expect(localStorageMock.setItem).toHaveBeenCalled();
            expect(deviceApi.checkDeviceStatus).toHaveBeenCalled();
        });

        it('should use existing device ID from localStorage', async () => {
            localStorageMock.getItem.mockReturnValue('existing-device-id');

            deviceApi.checkDeviceStatus.mockResolvedValue({
                status: DEVICE_STATUS.NOT_LINKED,
                device: null,
            });

            await store.initialize();

            expect(store.deviceId).toBe('existing-device-id');
        });
    });

    describe('checkStatus()', () => {
        beforeEach(() => {
            store.deviceId = 'device-123';
        });

        it('should update status from API response', async () => {
            deviceApi.checkDeviceStatus.mockResolvedValue({
                status: DEVICE_STATUS.CONFIGURED,
                device: {
                    device_id: 'device-123',
                    timezone: 'Europe/Moscow',
                    kitchen_station: {
                        id: 1,
                        name: 'Hot Station',
                        slug: 'hot',
                    },
                },
            });

            await store.checkStatus();

            expect(store.status).toBe(DEVICE_STATUS.CONFIGURED);
            expect(store.timezone).toBe('Europe/Moscow');
            expect(store.currentStation).toEqual({
                id: 1,
                name: 'Hot Station',
                slug: 'hot',
            });
            expect(store.stationSlug).toBe('hot');
        });

        it('should set NOT_LINKED when no device ID', async () => {
            store.deviceId = null;

            await store.checkStatus();

            expect(store.status).toBe(DEVICE_STATUS.NOT_LINKED);
            expect(deviceApi.checkDeviceStatus).not.toHaveBeenCalled();
        });

        it('should handle API errors gracefully', async () => {
            store.status = DEVICE_STATUS.CONFIGURED;
            deviceApi.checkDeviceStatus.mockRejectedValue(new Error('Network error'));

            await store.checkStatus();

            // Status should remain unchanged on error
            expect(store.status).toBe(DEVICE_STATUS.CONFIGURED);
        });
    });

    describe('linkDevice()', () => {
        beforeEach(() => {
            store.deviceId = 'device-123';
        });

        it('should link device with valid code', async () => {
            const mockDevice = {
                device_id: 'device-123',
                kitchen_station: { id: 1, name: 'Test' },
            };

            deviceApi.linkDevice.mockResolvedValue(mockDevice);
            deviceApi.checkDeviceStatus.mockResolvedValue({
                status: DEVICE_STATUS.CONFIGURED,
                device: mockDevice,
            });

            const result = await store.linkDevice('123456');

            expect(result).toBe(true);
            expect(store.deviceData).toEqual(mockDevice);
            expect(store.isLinking).toBe(false);
            expect(store.linkingError).toBeNull();
        });

        it('should handle linking errors', async () => {
            const error = new Error('Invalid code');
            error.getUserMessage = () => 'Неверный код';
            deviceApi.linkDevice.mockRejectedValue(error);

            const result = await store.linkDevice('999999');

            expect(result).toBe(false);
            expect(store.linkingError).toBe('Неверный код');
            expect(store.isLinking).toBe(false);
        });

        it('should fail when no device ID', async () => {
            store.deviceId = null;

            const result = await store.linkDevice('123456');

            expect(result).toBe(false);
            expect(store.linkingError).toBe('No device ID');
        });

        it('should set isLinking during process', async () => {
            let isLinkingDuringCall = false;

            deviceApi.linkDevice.mockImplementation(async () => {
                isLinkingDuringCall = store.isLinking;
                return { device_id: 'device-123' };
            });
            deviceApi.checkDeviceStatus.mockResolvedValue({
                status: DEVICE_STATUS.PENDING,
                device: null,
            });

            await store.linkDevice('123456');

            expect(isLinkingDuringCall).toBe(true);
            expect(store.isLinking).toBe(false);
        });
    });

    describe('clearLinkingError()', () => {
        it('should clear linking error', () => {
            store.linkingError = 'Some error';
            store.clearLinkingError();
            expect(store.linkingError).toBeNull();
        });
    });

    describe('loadStation()', () => {
        it('should load station by slug', async () => {
            const mockStation = { id: 1, name: 'Hot Station', slug: 'hot' };
            deviceApi.getStationBySlug.mockResolvedValue(mockStation);

            await store.loadStation('hot');

            expect(store.currentStation).toEqual(mockStation);
            expect(store.stationSlug).toBe('hot');
        });

        it('should clear station when slug is empty', async () => {
            store.currentStation = { id: 1, name: 'Test' };
            store.stationSlug = 'test';

            await store.loadStation('');

            expect(store.currentStation).toBeNull();
            expect(store.stationSlug).toBeNull();
            expect(deviceApi.getStationBySlug).not.toHaveBeenCalled();
        });

        it('should handle API errors', async () => {
            deviceApi.getStationBySlug.mockRejectedValue(new Error('Not found'));

            // Should not throw
            await store.loadStation('unknown');
        });
    });

    describe('reset()', () => {
        it('should clear all state and localStorage', () => {
            // Set some state
            store.deviceId = 'device-123';
            store.status = DEVICE_STATUS.CONFIGURED;
            store.deviceData = { device_id: 'device-123' };
            store.currentStation = { id: 1 };
            store.stationSlug = 'hot';
            store.linkingError = 'error';

            store.reset();

            expect(store.deviceId).toBeNull();
            expect(store.status).toBe(DEVICE_STATUS.LOADING);
            expect(store.deviceData).toBeNull();
            expect(store.currentStation).toBeNull();
            expect(store.stationSlug).toBeNull();
            expect(store.linkingError).toBeNull();

            expect(localStorageMock.removeItem).toHaveBeenCalledWith(DEVICE_STORAGE_KEYS.DEVICE_ID);
            expect(localStorageMock.removeItem).toHaveBeenCalledWith(DEVICE_STORAGE_KEYS.DEVICE_CONFIG);
            expect(localStorageMock.removeItem).toHaveBeenCalledWith(DEVICE_STORAGE_KEYS.STATION_SLUG);
        });
    });
});
