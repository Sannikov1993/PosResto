/**
 * UUID Utilities
 *
 * Functions for generating and validating UUIDs.
 *
 * @module kitchen/utils/uuid
 */

/**
 * Generate a UUID v4
 * Uses crypto.randomUUID() if available, falls back to manual generation
 * @returns {string} UUID string
 */
export function generateUUID() {
    // Use native crypto.randomUUID if available (modern browsers)
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    // Fallback for older browsers
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

/**
 * Validate if string is a valid UUID
 * @param {string} uuid - String to validate
 * @returns {boolean} True if valid UUID
 */
export function isValidUUID(uuid) {
    if (!uuid || typeof uuid !== 'string') return false;
    const pattern = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
    return pattern.test(uuid);
}

/**
 * Generate a device-specific ID with timestamp prefix
 * @returns {string} Device ID in format "tab_{timestamp}_{random}"
 */
export function generateDeviceId() {
    const timestamp = Date.now();
    const random = Math.random().toString(36).substring(2, 8);
    return `tab_${timestamp}_${random}`;
}
