/**
 * Device API Service
 *
 * API methods for device-related operations including
 * linking, status checks, and station information.
 *
 * @module kitchen/services/api/deviceApi
 */

import { kitchenApi } from './kitchenApi.js';
import { API_ENDPOINTS, API_ERROR_CODE } from '../../constants/api.js';
import { DEVICE_STATUS } from '../../constants/deviceStatus.js';
import { KitchenApiError } from './errors.js';
import { safeValidate, DeviceSchema } from '../../utils/apiSchemas.js';
import type { KitchenDevice, KitchenStation, ApiResponse } from '../../types/index.js';

interface DeviceStatusResult {
    status: string;
    device: KitchenDevice | null;
}

class DeviceApiService {
    async checkDeviceStatus(deviceId: string): Promise<DeviceStatusResult> {
        try {
            const response = await kitchenApi.get<ApiResponse<KitchenDevice>>(API_ENDPOINTS.DEVICE_STATUS, {
                device_id: deviceId,
            });

            if (response.success) {
                if (response.data) {
                    safeValidate(response.data, DeviceSchema, 'checkDeviceStatus');
                }

                const status = response.status || (response.data as any)?.status || this._determineStatus(response.data || null);
                return {
                    status,
                    device: response.data || null,
                };
            }

            return {
                status: response.status || (response.data as any)?.status || DEVICE_STATUS.PENDING,
                device: null as any,
            };
        } catch (error: any) {
            if (error instanceof KitchenApiError) {
                if (error.status === 404) {
                    return { status: DEVICE_STATUS.NOT_LINKED, device: null };
                }
                if (error.status === 403) {
                    return { status: DEVICE_STATUS.DISABLED, device: null };
                }
            }
            throw error;
        }
    }

    private _determineStatus(device: KitchenDevice | null): string {
        if (!device) return DEVICE_STATUS.NOT_LINKED;
        if (device.status === 'disabled') return DEVICE_STATUS.DISABLED;
        if (device.kitchen_station) return DEVICE_STATUS.CONFIGURED;
        return DEVICE_STATUS.PENDING;
    }

    async linkDevice(deviceId: string, linkingCode: string): Promise<KitchenDevice> {
        if (!/^\d{6}$/.test(linkingCode)) {
            throw new KitchenApiError(
                'Invalid code format',
                API_ERROR_CODE.INVALID_CODE,
                { retryable: false }
            );
        }

        const response = await kitchenApi.post<ApiResponse<KitchenDevice>>(API_ENDPOINTS.DEVICE_LINK, {
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

        return response.data!;
    }

    async getActiveStations(): Promise<KitchenStation[]> {
        const response = await kitchenApi.get<ApiResponse<KitchenStation[]>>(API_ENDPOINTS.STATIONS_ACTIVE);

        if (!response.success) {
            throw new Error(response.message || 'Failed to fetch stations');
        }

        return response.data || [];
    }

    async getStationBySlug(slug: string): Promise<KitchenStation | null> {
        if (!slug) return null;

        const stations = await this.getActiveStations();
        return stations.find((s: any) => s.slug === slug) || null;
    }
}

export const deviceApi = new DeviceApiService();
