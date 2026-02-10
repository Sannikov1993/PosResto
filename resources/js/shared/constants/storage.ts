/**
 * Storage Constants - Centralized localStorage keys
 *
 * @module shared/constants/storage
 */

export const STORAGE_KEYS = {
    RESTAURANT_ID: 'menulab_restaurant_id',
    TENANT_ID: 'menulab_tenant_id',
    SESSION: 'menulab_session',
    ACTIVE_TAB: 'menulab_active_tab',
    OFFLINE_QUEUE: 'menulab_offline_queue',
    SESSION_META: 'menulab_session_meta',
    /** @deprecated Use RESTAURANT_ID instead */
    LEGACY_POS_RESTAURANT_ID: 'pos_restaurant_id',
    /** @deprecated Use RESTAURANT_ID instead */
    LEGACY_BACKOFFICE_RESTAURANT_ID: 'backoffice_restaurant_id',
} as const;

export const UNIFIED_AUTH_KEY = 'menulab_auth';

export const AUTH_KEYS = {
    UNIFIED: 'menulab_auth',
    POS_SESSION: 'menulab_session',
    POS_TOKEN: 'pos_token',
    BACKOFFICE_TOKEN: 'backoffice_token',
    WAITER_TOKEN: 'waiter_token',
    KITCHEN_TOKEN: 'kitchen_token',
} as const;

export const BROADCAST_CHANNELS = {
    RESTAURANT_SYNC: 'menulab_restaurant_sync',
    SESSION_SYNC: 'menulab_session_sync',
    AUTH_EVENTS: 'menulab_auth_events',
} as const;

export function getWithLegacyFallback(key: string, legacyKeys: string[] = []): string | null {
    let value = localStorage.getItem(key);
    if (value) return value;

    for (const legacyKey of legacyKeys) {
        value = localStorage.getItem(legacyKey);
        if (value) {
            localStorage.setItem(key, value);
            console.log(`[Storage] Migrated ${legacyKey} -> ${key}`);
            return value;
        }
    }

    return null;
}

export function getRestaurantId(): string | null {
    return getWithLegacyFallback(STORAGE_KEYS.RESTAURANT_ID, [
        STORAGE_KEYS.LEGACY_POS_RESTAURANT_ID,
        STORAGE_KEYS.LEGACY_BACKOFFICE_RESTAURANT_ID,
    ]);
}

export function setRestaurantId(id: string | number): void {
    const value = String(id);
    localStorage.setItem(STORAGE_KEYS.RESTAURANT_ID, value);
    localStorage.setItem(STORAGE_KEYS.LEGACY_POS_RESTAURANT_ID, value);
    localStorage.setItem(STORAGE_KEYS.LEGACY_BACKOFFICE_RESTAURANT_ID, value);

    try {
        const channel = new BroadcastChannel(BROADCAST_CHANNELS.RESTAURANT_SYNC);
        channel.postMessage({ type: 'restaurant_change', restaurantId: value });
        channel.close();
    } catch {
        // BroadcastChannel not supported
    }
}

export function clearRestaurantId(): void {
    localStorage.removeItem(STORAGE_KEYS.RESTAURANT_ID);
    localStorage.removeItem(STORAGE_KEYS.LEGACY_POS_RESTAURANT_ID);
    localStorage.removeItem(STORAGE_KEYS.LEGACY_BACKOFFICE_RESTAURANT_ID);
}

export default {
    STORAGE_KEYS,
    AUTH_KEYS,
    BROADCAST_CHANNELS,
    getWithLegacyFallback,
    getRestaurantId,
    setRestaurantId,
    clearRestaurantId,
};
