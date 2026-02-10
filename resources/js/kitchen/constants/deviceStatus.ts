/**
 * Device Status Constants
 *
 * Defines all possible states for kitchen display devices
 * and their linking process.
 *
 * @module kitchen/constants/deviceStatus
 */

export const DEVICE_STATUS = Object.freeze({
    LOADING: 'loading',
    NOT_LINKED: 'not_linked',
    PENDING: 'pending',
    CONFIGURED: 'configured',
    DISABLED: 'disabled',
});

export const LINKING_CODE_CONFIG = Object.freeze({
    LENGTH: 6,
    PATTERN: /^\d{6}$/,
});

export const DEVICE_STORAGE_KEYS = Object.freeze({
    DEVICE_ID: 'kitchen_device_id',
    DEVICE_CONFIG: 'kitchen_device_config',
    STATION_SLUG: 'kitchen_station_slug',
});

export function isDeviceReady(status: string): boolean {
    return status === DEVICE_STATUS.CONFIGURED;
}

export function deviceNeedsAction(status: string): boolean {
    return status === DEVICE_STATUS.NOT_LINKED ||
           status === DEVICE_STATUS.PENDING;
}

export function isDeviceError(status: string): boolean {
    return status === DEVICE_STATUS.DISABLED;
}
