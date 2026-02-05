/**
 * Device Status Constants
 *
 * Defines all possible states for kitchen display devices
 * and their linking process.
 *
 * @module kitchen/constants/deviceStatus
 */

/**
 * Device status values
 * @readonly
 * @enum {string}
 */
export const DEVICE_STATUS = Object.freeze({
    /** Initial loading state */
    LOADING: 'loading',
    /** Device not linked to any restaurant */
    NOT_LINKED: 'not_linked',
    /** Device linked but waiting for station assignment */
    PENDING: 'pending',
    /** Device fully configured and ready */
    CONFIGURED: 'configured',
    /** Device has been disabled by admin */
    DISABLED: 'disabled',
});

/**
 * Linking code configuration
 * @readonly
 */
export const LINKING_CODE_CONFIG = Object.freeze({
    /** Number of digits in linking code */
    LENGTH: 6,
    /** Regex pattern for valid code */
    PATTERN: /^\d{6}$/,
});

/**
 * Local storage keys for device data
 * @readonly
 * @enum {string}
 */
export const DEVICE_STORAGE_KEYS = Object.freeze({
    /** Unique device identifier */
    DEVICE_ID: 'kitchen_device_id',
    /** Cached device configuration */
    DEVICE_CONFIG: 'kitchen_device_config',
    /** Last known station slug */
    STATION_SLUG: 'kitchen_station_slug',
});

/**
 * Check if device is ready to display orders
 * @param {string} status - Device status
 * @returns {boolean}
 */
export function isDeviceReady(status) {
    return status === DEVICE_STATUS.CONFIGURED;
}

/**
 * Check if device needs user action
 * @param {string} status - Device status
 * @returns {boolean}
 */
export function deviceNeedsAction(status) {
    return status === DEVICE_STATUS.NOT_LINKED ||
           status === DEVICE_STATUS.PENDING;
}

/**
 * Check if device is in error state
 * @param {string} status - Device status
 * @returns {boolean}
 */
export function isDeviceError(status) {
    return status === DEVICE_STATUS.DISABLED;
}
