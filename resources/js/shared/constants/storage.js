/**
 * Storage Constants - Centralized localStorage keys
 *
 * Single source of truth for all storage keys across applications.
 * Eliminates magic strings and ensures consistency between POS, BackOffice, and Waiter apps.
 *
 * @module shared/constants/storage
 */

/**
 * Application-agnostic storage keys (shared between all apps)
 */
export const STORAGE_KEYS = {
    // ═══════════════════════════════════════════════════════════════════════════
    // RESTAURANT CONTEXT (shared across all apps)
    // ═══════════════════════════════════════════════════════════════════════════

    /** Current restaurant ID - единый ключ для всех приложений */
    RESTAURANT_ID: 'menulab_restaurant_id',

    /** Current tenant ID */
    TENANT_ID: 'menulab_tenant_id',

    // ═══════════════════════════════════════════════════════════════════════════
    // SESSION & AUTH
    // ═══════════════════════════════════════════════════════════════════════════

    /** Primary session data storage key */
    SESSION: 'menulab_session',

    /** Active tab identifier storage key */
    ACTIVE_TAB: 'menulab_active_tab',

    /** Offline operations queue storage key */
    OFFLINE_QUEUE: 'menulab_offline_queue',

    /** Session metadata for debugging */
    SESSION_META: 'menulab_session_meta',

    // ═══════════════════════════════════════════════════════════════════════════
    // LEGACY KEYS (for migration - will be removed in future versions)
    // ═══════════════════════════════════════════════════════════════════════════

    /** @deprecated Use RESTAURANT_ID instead */
    LEGACY_POS_RESTAURANT_ID: 'pos_restaurant_id',

    /** @deprecated Use RESTAURANT_ID instead */
    LEGACY_BACKOFFICE_RESTAURANT_ID: 'backoffice_restaurant_id',
};

/**
 * Unified auth key - single source of truth for authentication
 */
export const UNIFIED_AUTH_KEY = 'menulab_auth';

/**
 * App-specific token storage keys (legacy - for backward compatibility)
 * Each app may have its own authentication context
 */
export const AUTH_KEYS = {
    /** Unified auth key - preferred */
    UNIFIED: 'menulab_auth',
    /** Legacy POS session key */
    POS_SESSION: 'menulab_session',
    /** Legacy keys */
    POS_TOKEN: 'pos_token',
    BACKOFFICE_TOKEN: 'backoffice_token',
    WAITER_TOKEN: 'waiter_token',
    KITCHEN_TOKEN: 'kitchen_token',
};

/**
 * BroadcastChannel names for cross-tab/cross-app communication
 */
export const BROADCAST_CHANNELS = {
    /** Restaurant context sync channel */
    RESTAURANT_SYNC: 'menulab_restaurant_sync',

    /** Session sync channel */
    SESSION_SYNC: 'menulab_session_sync',

    /** Auth events channel */
    AUTH_EVENTS: 'menulab_auth_events',
};

/**
 * Migration helper - reads from new key, falls back to legacy keys
 * @param {string} key - Primary key to read
 * @param {string[]} legacyKeys - Legacy keys to check if primary is empty
 * @returns {string|null}
 */
export function getWithLegacyFallback(key, legacyKeys = []) {
    let value = localStorage.getItem(key);
    if (value) return value;

    for (const legacyKey of legacyKeys) {
        value = localStorage.getItem(legacyKey);
        if (value) {
            // Migrate to new key
            localStorage.setItem(key, value);
            console.log(`[Storage] Migrated ${legacyKey} -> ${key}`);
            return value;
        }
    }

    return null;
}

/**
 * Get current restaurant ID with legacy fallback
 * @returns {string|null}
 */
export function getRestaurantId() {
    return getWithLegacyFallback(STORAGE_KEYS.RESTAURANT_ID, [
        STORAGE_KEYS.LEGACY_POS_RESTAURANT_ID,
        STORAGE_KEYS.LEGACY_BACKOFFICE_RESTAURANT_ID,
    ]);
}

/**
 * Set restaurant ID (updates both new and legacy keys for compatibility)
 * @param {string|number} id - Restaurant ID
 */
export function setRestaurantId(id) {
    const value = String(id);
    localStorage.setItem(STORAGE_KEYS.RESTAURANT_ID, value);
    // Also set legacy keys for backward compatibility during migration period
    localStorage.setItem(STORAGE_KEYS.LEGACY_POS_RESTAURANT_ID, value);
    localStorage.setItem(STORAGE_KEYS.LEGACY_BACKOFFICE_RESTAURANT_ID, value);

    // Broadcast to other tabs/apps
    try {
        const channel = new BroadcastChannel(BROADCAST_CHANNELS.RESTAURANT_SYNC);
        channel.postMessage({ type: 'restaurant_change', restaurantId: value });
        channel.close();
    } catch (e) {
        // BroadcastChannel not supported
    }
}

/**
 * Clear restaurant ID from all storage keys
 */
export function clearRestaurantId() {
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
