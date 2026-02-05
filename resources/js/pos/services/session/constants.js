/**
 * Session Management Constants
 *
 * Centralized configuration for session management.
 * All timing values are in milliseconds unless otherwise noted.
 *
 * @module services/session/constants
 */

/**
 * Storage keys used across the session management system
 */
export const STORAGE_KEYS = {
    /** Primary session data storage key */
    SESSION: 'menulab_session',

    /** Restaurant context storage key */
    RESTAURANT_ID: 'pos_restaurant_id',

    /** Active tab identifier storage key */
    ACTIVE_TAB: 'menulab_active_tab',

    /** Offline operations queue storage key */
    OFFLINE_QUEUE: 'menulab_offline_queue',

    /** Session metadata for debugging */
    SESSION_META: 'menulab_session_meta',
};

/**
 * Session timing configuration
 */
export const SESSION_TIMING = {
    /** Maximum session lifetime from login (8 hours) */
    MAX_LIFETIME: 8 * 60 * 60 * 1000,

    /** Session extension on activity (8 hours sliding window) */
    EXTENSION_PERIOD: 8 * 60 * 60 * 1000,

    /** Minimum time between session extensions (5 minutes) */
    EXTENSION_THROTTLE: 5 * 60 * 1000,

    /** Warning before session expiration (15 minutes) */
    EXPIRATION_WARNING: 15 * 60 * 1000,

    /** Critical warning before expiration (5 minutes) */
    EXPIRATION_CRITICAL: 5 * 60 * 1000,

    /** Token validation interval (30 minutes) */
    VALIDATION_INTERVAL: 30 * 60 * 1000,

    /** Activity tracking debounce (1 minute) */
    ACTIVITY_DEBOUNCE: 60 * 1000,
};

/**
 * Network retry configuration with exponential backoff
 */
export const RETRY_CONFIG = {
    /** Maximum number of retry attempts */
    MAX_ATTEMPTS: 3,

    /** Base delay between retries (1 second) */
    BASE_DELAY: 1000,

    /** Maximum delay between retries (30 seconds) */
    MAX_DELAY: 30000,

    /** Exponential backoff multiplier */
    BACKOFF_MULTIPLIER: 2,

    /** Jitter factor to prevent thundering herd (0-1) */
    JITTER_FACTOR: 0.3,

    /** Request timeout (15 seconds) */
    REQUEST_TIMEOUT: 15000,

    /** HTTP status codes that should trigger retry */
    RETRYABLE_STATUS_CODES: [408, 429, 500, 502, 503, 504],

    /** Error types that should trigger retry */
    RETRYABLE_ERRORS: ['ECONNABORTED', 'ETIMEDOUT', 'ENOTFOUND', 'ENETUNREACH', 'ERR_NETWORK'],
};

/**
 * Tab synchronization configuration
 */
export const TAB_SYNC_CONFIG = {
    /** BroadcastChannel name for session sync */
    CHANNEL_NAME: 'menulab_session_sync',

    /** Heartbeat interval for leader election (10 seconds) */
    HEARTBEAT_INTERVAL: 10000,

    /** Leader timeout threshold (30 seconds) */
    LEADER_TIMEOUT: 30000,

    /** Message types for inter-tab communication */
    MESSAGE_TYPES: {
        SESSION_UPDATE: 'session_update',
        SESSION_CLEAR: 'session_clear',
        LOGOUT: 'logout',
        TOKEN_REFRESH: 'token_refresh',
        HEARTBEAT: 'heartbeat',
        LEADER_CLAIM: 'leader_claim',
        LEADER_ACK: 'leader_ack',
        ACTIVITY: 'activity',
    },
};

/**
 * Session states
 */
export const SESSION_STATES = {
    /** No session exists */
    NONE: 'none',

    /** Session is being initialized */
    INITIALIZING: 'initializing',

    /** Session is active and valid */
    ACTIVE: 'active',

    /** Session is being validated */
    VALIDATING: 'validating',

    /** Session is expiring soon (warning) */
    EXPIRING_SOON: 'expiring_soon',

    /** Session has expired */
    EXPIRED: 'expired',

    /** Session validation failed (network error, etc.) */
    INVALID: 'invalid',

    /** Session is being refreshed */
    REFRESHING: 'refreshing',
};

/**
 * Event names emitted by SessionManager
 */
export const SESSION_EVENTS = {
    /** Session state changed */
    STATE_CHANGE: 'session:state_change',

    /** Session successfully restored */
    RESTORED: 'session:restored',

    /** Session created (login) */
    CREATED: 'session:created',

    /** Session extended */
    EXTENDED: 'session:extended',

    /** Session expiring soon (warning) */
    EXPIRING_SOON: 'session:expiring_soon',

    /** Session expired */
    EXPIRED: 'session:expired',

    /** Session cleared (logout) */
    CLEARED: 'session:cleared',

    /** Session validation failed */
    VALIDATION_FAILED: 'session:validation_failed',

    /** Network error during session operation */
    NETWORK_ERROR: 'session:network_error',

    /** Session synced from another tab */
    TAB_SYNCED: 'session:tab_synced',

    /** Activity detected */
    ACTIVITY: 'session:activity',
};

/**
 * Validation error codes
 */
export const VALIDATION_ERRORS = {
    /** No token provided */
    NO_TOKEN: 'no_token',

    /** Token format invalid */
    INVALID_FORMAT: 'invalid_format',

    /** Token expired on server */
    TOKEN_EXPIRED: 'token_expired',

    /** Token revoked */
    TOKEN_REVOKED: 'token_revoked',

    /** User deactivated */
    USER_INACTIVE: 'user_inactive',

    /** Network error during validation */
    NETWORK_ERROR: 'network_error',

    /** Server error during validation */
    SERVER_ERROR: 'server_error',

    /** Session data corrupted */
    CORRUPTED_DATA: 'corrupted_data',

    /** Client-side expiration */
    CLIENT_EXPIRED: 'client_expired',
};

/**
 * Default session structure
 */
export const DEFAULT_SESSION = {
    user: null,
    token: null,
    permissions: [],
    limits: {
        max_discount_percent: 0,
        max_refund_amount: 0,
        max_cancel_amount: 0,
    },
    interfaceAccess: {
        can_access_pos: false,
        can_access_backoffice: false,
        can_access_kitchen: false,
        can_access_delivery: false,
    },
    loginAt: null,
    lastActivity: null,
    lastValidation: null,
    lastExtension: null,
    expiresAt: null,
    version: 1,
};

export default {
    STORAGE_KEYS,
    SESSION_TIMING,
    RETRY_CONFIG,
    TAB_SYNC_CONFIG,
    SESSION_STATES,
    SESSION_EVENTS,
    VALIDATION_ERRORS,
    DEFAULT_SESSION,
};
