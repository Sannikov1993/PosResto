/**
 * Device API Service
 *
 * API methods for device-related operations including
 * linking, status checks, and station information.
 *
 * @module kitchen/services/api/deviceApi
 */

import { kitchenApi } from './kitchenApi.js';
import { API_ENDPOINTS } from '../../constants/api.js';
import { DEVICE_STATUS } from '../../constants/deviceStatus.js';
import { KitchenApiError } from './errors.js';
import { API_ERROR_CODE } from '../../constants/api.js';
import { safeValidate, DeviceSchema } from '../../utils/apiSchemas.js';

/**
 * @typedef {import('../../types').KitchenDevice} KitchenDevice
 * @typedef {import('../../types').KitchenStation} KitchenStation
 * @typedef {import('../../types').DeviceStatusResponse} DeviceStatusResponse
 */

/**
 * Device API service
 */
class DeviceApiService {
    /**
     * Check device status and get station configuration
     * @param {string} deviceId - Device identifier
     * @returns {Promise<{status: string, device: KitchenDevice|null}>}
     */
    async checkDeviceStatus(deviceId) {
        try {
            const response = await kitchenApi.get(API_ENDPOINTS.DEVICE_STATUS, {
                device_id: deviceId,
            });

            if (response.success) {
                // Validate response in development
                if (response.data) {
                    safeValidate(response.data, DeviceSchema, 'checkDeviceStatus');
                }

                // Status can be in response.status or response.data.status
                const status = response.status || response.data?.status || this._determineStatus(response.data);
                return {
                    status,
                    device: response.data || null,
                };
            }

            return {
                status: response.status || response.data?.status || DEVICE_STATUS.PENDING,
                device: null,
            };
        } catch (error) {
            // Handle specific error cases
            if (error instanceof KitchenApiError) {
                if (error.status === 404) {
                    return { status: DEVICE_STATUS.NOT_LINKED, device: null };
                }
                if (error.status === 403) {
                    return { status: DEVICE_STATUS.DISABLED, device: null };
                }
            }

            // Re-throw other errors
            throw error;
        }
    }

    /**
     * Determine device status from device data
     * @private
     * @param {KitchenDevice|null} device - Device data
     * @returns {string} Device status
     */
    _determineStatus(device) {
        if (!device) return DEVICE_STATUS.NOT_LINKED;
        if (device.status === 'disabled') return DEVICE_STATUS.DISABLED;
        if (device.kitchen_station) return DEVICE_STATUS.CONFIGURED;
        return DEVICE_STATUS.PENDING;
    }

    /**
     * Link device using a 6-digit code
     * @param {string} deviceId - Device identifier
     * @param {string} linkingCode - 6-digit linking code
     * @returns {Promise<KitchenDevice>}
     * @throws {KitchenApiError} If code is invalid
     */
    async linkDevice(deviceId, linkingCode) {
        // Validate code format
        if (!/^\d{6}$/.test(linkingCode)) {
            throw new KitchenApiError(
                'Invalid code format',
                API_ERROR_CODE.INVALID_CODE,
                { retryable: false }
            );
        }

        const response = await kitchenApi.post(API_ENDPOINTS.DEVICE_LINK, {
            device_id: deviceId,
            linking_code: linkingCode,
        });

        if (!response.success) {
            const errorCode = response.error_code || API_ERROR_CODE.INVALID_CODE;
            throw new KitchenApiError(
                response.message || 'Failed to link device',
                errorCode,
                { retryable: false }
            );
        }

        return response.data;
    }

    /**
     * Get list of active kitchen stations
     * @returns {Promise<KitchenStation[]>}
     */
    async getActiveStations() {
        const response = await kitchenApi.get(API_ENDPOINTS.STATIONS_ACTIVE);

        if (!response.success) {
            throw new Error(response.message || 'Failed to fetch stations');
        }

        return response.data || [];
    }

    /**
     * Find station by slug from active stations
     * @param {string} slug - Station slug
     * @returns {Promise<KitchenStation|null>}
     */
    async getStationBySlug(slug) {
        if (!slug) return null;

        const stations = await this.getActiveStations();
        return stations.find(s => s.slug === slug) || null;
    }
}

// Export singleton instance
export const deviceApi = new DeviceApiService();
