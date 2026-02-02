/**
 * Waiter App - Constants
 * Application constants and configuration
 */

// === API Configuration ===

export const API_BASE_URL = '/api';
export const API_TIMEOUT = 30000; // 30 seconds

// === Cache TTL (Time To Live) ===

export const CACHE_TTL = {
  TABLES: 30000,      // 30 seconds
  ORDERS: 15000,      // 15 seconds
  MENU: 60000,        // 1 minute
  CATEGORIES: 300000, // 5 minutes
} as const;

// === Toast Configuration ===

export const TOAST_DURATION = {
  SUCCESS: 3000,
  ERROR: 5000,
  WARNING: 4000,
  INFO: 3000,
} as const;

// === Pagination ===

export const DEFAULT_PAGE_SIZE = 20;
export const MAX_PAGE_SIZE = 100;

// === Table Configuration ===

export const TABLE_CONFIG = {
  MIN_GUESTS: 1,
  MAX_GUESTS: 50,
  DEFAULT_GUESTS: 2,
} as const;

// === Order Configuration ===

export const ORDER_CONFIG = {
  MIN_QUANTITY: 1,
  MAX_QUANTITY: 99,
  MAX_COMMENT_LENGTH: 500,
} as const;

// === Payment Configuration ===

export const PAYMENT_CONFIG = {
  QUICK_AMOUNTS: [100, 200, 500, 1000, 2000, 5000],
  MAX_DISCOUNT_PERCENT: 50,
} as const;

// === Status Constants ===

export const TABLE_STATUSES = {
  FREE: 'free',
  OCCUPIED: 'occupied',
  RESERVED: 'reserved',
  BILL_REQUESTED: 'bill_requested',
} as const;

export const ORDER_STATUSES = {
  NEW: 'new',
  PENDING: 'pending',
  COOKING: 'cooking',
  READY: 'ready',
  SERVED: 'served',
  PAID: 'paid',
  CANCELLED: 'cancelled',
} as const;

export const ORDER_ITEM_STATUSES = {
  NEW: 'new',
  PENDING: 'pending',
  COOKING: 'cooking',
  READY: 'ready',
  SERVED: 'served',
  CANCELLED: 'cancelled',
} as const;

export const PAYMENT_METHODS = {
  CASH: 'cash',
  CARD: 'card',
  MIXED: 'mixed',
} as const;

// === User Roles ===

export const USER_ROLES = {
  ADMIN: 'admin',
  MANAGER: 'manager',
  WAITER: 'waiter',
  CASHIER: 'cashier',
  COURIER: 'courier',
} as const;

// === Keyboard Shortcuts ===

export const KEYBOARD_SHORTCUTS = {
  TABLES: 'F1',
  ORDERS: 'F2',
  PROFILE: 'F3',
  SEND_TO_KITCHEN: 'F5',
  PAYMENT: 'F8',
  LOGOUT: 'Escape',
} as const;

// === Local Storage Keys ===

export const STORAGE_KEYS = {
  API_TOKEN: 'api_token',
  DARK_MODE: 'waiter-dark-mode',
  SELECTED_ZONE: 'waiter-selected-zone',
  LAST_USER: 'waiter-last-user',
} as const;

// === Event Names ===

export const EVENTS = {
  AUTH_LOGOUT: 'auth:logout',
  AUTH_LOGIN: 'auth:login',
  ORDER_UPDATED: 'order:updated',
  TABLE_UPDATED: 'table:updated',
  NETWORK_ONLINE: 'network:online',
  NETWORK_OFFLINE: 'network:offline',
} as const;

// === Colors ===

export const STATUS_COLORS = {
  // Table statuses
  'table-free': '#10B981',      // green-500
  'table-occupied': '#F97316',  // orange-500
  'table-reserved': '#3B82F6',  // blue-500
  'table-bill': '#EF4444',      // red-500

  // Order statuses
  'order-new': '#3B82F6',       // blue-500
  'order-pending': '#EAB308',   // yellow-500
  'order-cooking': '#F97316',   // orange-500
  'order-ready': '#10B981',     // green-500
  'order-served': '#6B7280',    // gray-500
  'order-paid': '#059669',      // emerald-600
  'order-cancelled': '#EF4444', // red-500
} as const;

// === Animation Durations ===

export const ANIMATION_DURATION = {
  FAST: 150,
  NORMAL: 300,
  SLOW: 500,
} as const;

// === Debounce/Throttle Times ===

export const DEBOUNCE_TIME = {
  SEARCH: 300,
  INPUT: 150,
  SCROLL: 100,
} as const;

// === Refresh Intervals ===

export const REFRESH_INTERVAL = {
  TABLES: 30000,  // 30 seconds
  ORDERS: 15000,  // 15 seconds
  STATUS: 60000,  // 1 minute
} as const;

// === Validation Limits ===

export const VALIDATION_LIMITS = {
  PIN_MIN_LENGTH: 4,
  PIN_MAX_LENGTH: 6,
  NAME_MIN_LENGTH: 2,
  NAME_MAX_LENGTH: 100,
  COMMENT_MAX_LENGTH: 500,
  PHONE_LENGTH: 11,
} as const;

// === Export all as single object for convenience ===

export const CONSTANTS = {
  API_BASE_URL,
  API_TIMEOUT,
  CACHE_TTL,
  TOAST_DURATION,
  DEFAULT_PAGE_SIZE,
  MAX_PAGE_SIZE,
  TABLE_CONFIG,
  ORDER_CONFIG,
  PAYMENT_CONFIG,
  TABLE_STATUSES,
  ORDER_STATUSES,
  ORDER_ITEM_STATUSES,
  PAYMENT_METHODS,
  USER_ROLES,
  KEYBOARD_SHORTCUTS,
  STORAGE_KEYS,
  EVENTS,
  STATUS_COLORS,
  ANIMATION_DURATION,
  DEBOUNCE_TIME,
  REFRESH_INTERVAL,
  VALIDATION_LIMITS,
} as const;

export default CONSTANTS;
