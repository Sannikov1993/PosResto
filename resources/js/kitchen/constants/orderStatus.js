/**
 * Order Status Constants
 *
 * Defines all possible order and order item statuses
 * used throughout the kitchen display system.
 *
 * @module kitchen/constants/orderStatus
 */

/**
 * Order-level status values
 * @readonly
 * @enum {string}
 */
export const ORDER_STATUS = Object.freeze({
    /** Order has been placed but not yet confirmed */
    PENDING: 'pending',
    /** Order confirmed and waiting to be prepared */
    CONFIRMED: 'confirmed',
    /** Order is being prepared in the kitchen */
    COOKING: 'cooking',
    /** Order is ready for pickup/serving */
    READY: 'ready',
    /** Order has been served/delivered */
    COMPLETED: 'completed',
    /** Order was cancelled */
    CANCELLED: 'cancelled',
});

/**
 * Order item status values
 * @readonly
 * @enum {string}
 */
export const ITEM_STATUS = Object.freeze({
    /** Item waiting to be prepared */
    PENDING: 'pending',
    /** Item is being cooked */
    COOKING: 'cooking',
    /** Item is ready */
    READY: 'ready',
    /** Item has been served */
    SERVED: 'served',
    /** Item was cancelled */
    CANCELLED: 'cancelled',
});

/**
 * Order types
 * @readonly
 * @enum {string}
 */
export const ORDER_TYPE = Object.freeze({
    /** Dine-in order (customer eating at restaurant) */
    DINE_IN: 'dine_in',
    /** Pickup order (customer picks up) */
    PICKUP: 'pickup',
    /** Delivery order */
    DELIVERY: 'delivery',
    /** Pre-order with scheduled time */
    PREORDER: 'preorder',
});

/**
 * Statuses considered "active" (order in progress)
 * @type {readonly string[]}
 */
export const ACTIVE_ORDER_STATUSES = Object.freeze([
    ORDER_STATUS.CONFIRMED,
    ORDER_STATUS.COOKING,
    ORDER_STATUS.READY,
]);

/**
 * Statuses considered "terminal" (order finished)
 * @type {readonly string[]}
 */
export const TERMINAL_ORDER_STATUSES = Object.freeze([
    ORDER_STATUS.COMPLETED,
    ORDER_STATUS.CANCELLED,
]);

/**
 * Order type priorities for sorting (higher = more urgent)
 * @type {Readonly<Record<string, number>>}
 */
export const ORDER_TYPE_PRIORITY = Object.freeze({
    [ORDER_TYPE.PREORDER]: 4,   // Scheduled orders - highest priority
    [ORDER_TYPE.DINE_IN]: 3,    // Guest is waiting
    [ORDER_TYPE.PICKUP]: 2,     // Customer coming soon
    [ORDER_TYPE.DELIVERY]: 1,   // Has travel time buffer
});

/**
 * Get priority for an order based on its type
 * @param {Object} order - The order object
 * @returns {number} Priority value (higher = more urgent)
 */
export function getOrderPriority(order) {
    // Preorder with scheduled time gets highest priority
    if (order.type === ORDER_TYPE.PREORDER ||
        (order.type === ORDER_TYPE.DINE_IN && order.scheduled_at)) {
        return ORDER_TYPE_PRIORITY[ORDER_TYPE.PREORDER];
    }
    return ORDER_TYPE_PRIORITY[order.type] ?? 1;
}

/**
 * Check if order is a scheduled preorder (not ASAP)
 * @param {Object} order - The order object
 * @returns {boolean}
 */
export function isPreorder(order) {
    return Boolean(order.scheduled_at && !order.is_asap);
}

/**
 * Check if order status is active
 * @param {string} status - Order status
 * @returns {boolean}
 */
export function isActiveStatus(status) {
    return ACTIVE_ORDER_STATUSES.includes(status);
}

/**
 * Check if order status is terminal
 * @param {string} status - Order status
 * @returns {boolean}
 */
export function isTerminalStatus(status) {
    return TERMINAL_ORDER_STATUSES.includes(status);
}
