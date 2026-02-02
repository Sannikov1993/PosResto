/**
 * Waiter App - Stores Index
 * Re-export all Pinia stores for convenient imports
 */

export { useAuthStore } from './auth';
export { useTablesStore } from './tables';
export { useOrdersStore } from './orders';
export { useMenuStore } from './menu';
export { useUiStore } from './ui';

// Re-export UI types
export type { Tab, ToastType, Toast, ConfirmOptions } from './ui';
