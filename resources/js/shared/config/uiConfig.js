/**
 * Centralized UI Configuration
 *
 * Single source of truth for timing, dimensions, and other UI constants.
 * Prevents magic numbers scattered across components.
 *
 * @module shared/config/uiConfig
 */

// ===== Toast / Notification =====

/** Duration a toast message stays visible (ms) */
export const TOAST_DURATION = 3000;

/** Duration for persistent notifications like WebSocket errors (ms) */
export const NOTIFICATION_TIMEOUT = 5000;

// ===== Dropdowns & Menus =====

/** Delay before hiding a dropdown on blur (ms) */
export const DROPDOWN_HIDE_DELAY = 200;

// ===== Animations & Transitions =====

/** Modal close animation delay (ms) */
export const ANIMATION_DELAY = 300;

/** Flash animation duration for order items (ms) */
export const FLASH_DURATION = 600;

// ===== Floor Map =====

/** Default floor width (px) */
export const FLOOR_WIDTH = 1200;

/** Default floor height (px) */
export const FLOOR_HEIGHT = 800;

/** Maximum floor zoom scale */
export const MAX_FLOOR_SCALE = 1.5;

/** Minimum floor zoom scale */
export const MIN_FLOOR_SCALE = 0.3;

// ===== Drafts =====

/** Auto-save delay for form drafts (ms) */
export const DRAFT_AUTOSAVE_DELAY = 1000;

/** Time after which a draft is considered expired (ms) */
export const DRAFT_EXPIRY = 10 * 60 * 1000;

// ===== Polling Intervals =====

/** Bar panel refresh interval (ms) */
export const BAR_REFRESH_INTERVAL = 15000;

/** Delivery map polling interval (ms) */
export const DELIVERY_MAP_POLL_INTERVAL = 10000;

/** Delivery calculation debounce (ms) */
export const DELIVERY_CALC_DEBOUNCE = 800;
