/**
 * Device API Service Unit Tests
 *
 * @group unit
 * @group kitchen
 * @group api
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { deviceApi } from '../../../services/api/deviceApi.js';
import { kitchenApi } from '../../../services/api/kitchenApi.js';
import { DEVICE_STATUS } from '../../../constants/deviceStatus.js';
import { API_ENDPOINTS, API_ERROR_CODE } from '../../../constants/api.js';
import { KitchenApiError } from '../../../services/api/errors.js';

// Mock kitchenApi
vi.mock('../../../services/api/kitchenApi.js', () => ({
    kitchenApi: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

describe('DeviceApiService', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==================== checkDeviceStatus ====================

    describe('checkDeviceStatus()', () => {
        it('should return configured status when device has station', async () => {
            kitchenApi.get.mockResolvedValue({
                success: true,
                status: DEVICE_STATUS.CONFIGURED,
                data: {
                    device_id: 'device-123',
                    kitchen_station: {
                        id: 1,
                        name: 'Hot Station',
                        slug: 'hot',
                    },
                    timezone: 'Asia/Yekaterinburg',
                },
            });

            const result = await deviceApi.checkDeviceStatus('device-123');

            expect(kitchenApi.get).toHaveBeenCalledWith(
                API_ENDPOINTS.DEVICE_STATUS,
                { device_id: 'device-123' }
            );
            expect(result.status).toBe(DEVICE_STATUS.CONFIGURED);
            expect(result.device.kitchen_station.name).toBe('Hot Station');
        });

        it('should return pending status when no station configured', async () => {
            kitchenApi.get.mockResolvedValue({
                success: true,
                data: {
                    device_id: 'device-123',
                    kitchen_station: null,
                },
            });

            const result = await deviceApi.checkDeviceStatus('device-123');

            expect(result.status).toBe(DEVICE_STATUS.PENDING);
        });

        it('should return not_linked on 404', async () => {
            const error = new KitchenApiError('Not found', API_ERROR_CODE.DEVICE_NOT_FOUND, {
                status: 404,
            });
            kitchenApi.get.mockRejectedValue(error);

            const result = await deviceApi.checkDeviceStatus('device-123');

            expect(result.status).toBe(DEVICE_STATUS.NOT_LINKED);
            expect(result.device).toBeNull();
        });

        it('should return disabled on 403', async () => {
            const error = new KitchenApiError('Forbidden', API_ERROR_CODE.DEVICE_DISABLED, {
                status: 403,
            });
            kitchenApi.get.mockRejectedValue(error);

            const result = await deviceApi.checkDeviceStatus('device-123');

            expect(result.status).toBe(DEVICE_STATUS.DISABLED);
            expect(result.device).toBeNull();
        });

        it('should re-throw other errors', async () => {
            const error = new Error('Network error');
            kitchenApi.get.mockRejectedValue(error);

            await expect(
                deviceApi.checkDeviceStatus('device-123')
            ).rejects.toThrow('Network error');
        });
    });

    // ==================== linkDevice ====================

    describe('linkDevice()', () => {
        it('should link device with valid code', async () => {
            const mockDevice = {
                device_id: 'device-123',
                kitchen_station: {
                    id: 1,
                    name: 'Hot Station',
                    slug: 'hot',
                },
            };

            kitchenApi.post.mockResolvedValue({
                success: true,
                data: mockDevice,
            });

            const result = await deviceApi.linkDevice('device-123', '123456');

            expect(kitchenApi.post).toHaveBeenCalledWith(
                API_ENDPOINTS.DEVICE_LINK,
                {
                    device_id: 'device-123',
                    linking_code: '123456',
                }
            );
            expect(result).toEqual(mockDevice);
        });

        it('should throw error for invalid code format', async () => {
            await expect(
                deviceApi.linkDevice('device-123', '12345')
            ).rejects.toThrow('Invalid code format');

            await expect(
                deviceApi.linkDevice('device-123', 'abcdef')
            ).rejects.toThrow('Invalid code format');
        });

        it('should throw error on API failure', async () => {
            kitchenApi.post.mockResolvedValue({
                success: false,
                message: 'Invalid or expired code',
                error_code: API_ERROR_CODE.INVALID_CODE,
            });

            await expect(
                deviceApi.linkDevice('device-123', '999999')
            ).rejects.toThrow();
        });
    });

    // ==================== getActiveStations ====================

    describe('getActiveStations()', () => {
        it('should fetch active stations', async () => {
            const mockStations = [
                { id: 1, name: 'Hot Station', slug: 'hot' },
                { id: 2, name: 'Cold Station', slug: 'cold' },
            ];

            kitchenApi.get.mockResolvedValue({
                success: true,
                data: mockStations,
            });

            const result = await deviceApi.getActiveStations();

            expect(kitchenApi.get).toHaveBeenCalledWith(API_ENDPOINTS.STATIONS_ACTIVE);
            expect(result).toEqual(mockStations);
        });

        it('should return empty array when no data', async () => {
            kitchenApi.get.mockResolvedValue({
                success: true,
                data: null,
            });

            const result = await deviceApi.getActiveStations();

            expect(result).toEqual([]);
        });

        it('should throw on failure', async () => {
            kitchenApi.get.mockResolvedValue({
                success: false,
                message: 'Server error',
            });

            await expect(deviceApi.getActiveStations()).rejects.toThrow('Server error');
        });
    });

    // ==================== getStationBySlug ====================

    describe('getStationBySlug()', () => {
        it('should find station by slug', async () => {
            const mockStations = [
                { id: 1, name: 'Hot Station', slug: 'hot' },
                { id: 2, name: 'Cold Station', slug: 'cold' },
            ];

            kitchenApi.get.mockResolvedValue({
                success: true,
                data: mockStations,
            });

            const result = await deviceApi.getStationBySlug('cold');

            expect(result).toEqual({ id: 2, name: 'Cold Station', slug: 'cold' });
        });

        it('should return null when slug not found', async () => {
            kitchenApi.get.mockResolvedValue({
                success: true,
                data: [{ id: 1, name: 'Hot Station', slug: 'hot' }],
            });

            const result = await deviceApi.getStationBySlug('unknown');

            expect(result).toBeNull();
        });

        it('should return null when slug is empty', async () => {
            const result = await deviceApi.getStationBySlug('');

            expect(result).toBeNull();
            expect(kitchenApi.get).not.toHaveBeenCalled();
        });

        it('should return null when slug is null', async () => {
            const result = await deviceApi.getStationBySlug(null);

            expect(result).toBeNull();
        });
    });

    // ==================== _determineStatus ====================

    describe('_determineStatus()', () => {
        it('should return NOT_LINKED when device is null', () => {
            const result = deviceApi._determineStatus(null);
            expect(result).toBe(DEVICE_STATUS.NOT_LINKED);
        });

        it('should return DISABLED when device status is disabled', () => {
            const result = deviceApi._determineStatus({ status: 'disabled' });
            expect(result).toBe(DEVICE_STATUS.DISABLED);
        });

        it('should return CONFIGURED when device has kitchen_station', () => {
            const result = deviceApi._determineStatus({
                kitchen_station: { id: 1, name: 'Test' },
            });
            expect(result).toBe(DEVICE_STATUS.CONFIGURED);
        });

        it('should return PENDING when device exists without station', () => {
            const result = deviceApi._determineStatus({
                device_id: 'device-123',
            });
            expect(result).toBe(DEVICE_STATUS.PENDING);
        });
    });
});
