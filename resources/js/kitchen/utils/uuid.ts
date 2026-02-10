/**
 * UUID Utilities
 *
 * Functions for generating and validating UUIDs.
 *
 * @module kitchen/utils/uuid
 */

export function generateUUID(): string {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

export function isValidUUID(uuid: string | null | undefined): boolean {
    if (!uuid || typeof uuid !== 'string') return false;
    const pattern = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
    return pattern.test(uuid);
}

export function generateDeviceId(): string {
    const timestamp = Date.now();
    const random = Math.random().toString(36).substring(2, 8);
    return `tab_${timestamp}_${random}`;
}
