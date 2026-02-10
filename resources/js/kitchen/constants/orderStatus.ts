/**
 * Order Status Constants
 *
 * Defines all possible order and order item statuses
 * used throughout the kitchen display system.
 *
 * @module kitchen/constants/orderStatus
 */

import type { Order } from '../types/index.js';

export const ORDER_STATUS = Object.freeze({
    PENDING: 'pending',
    CONFIRMED: 'confirmed',
    COOKING: 'cooking',
    READY: 'ready',
    COMPLETED: 'completed',
    CANCELLED: 'cancelled',
});

export const ITEM_STATUS = Object.freeze({
    PENDING: 'pending',
    COOKING: 'cooking',
    READY: 'ready',
    SERVED: 'served',
    CANCELLED: 'cancelled',
});

export const ORDER_TYPE = Object.freeze({
    DINE_IN: 'dine_in',
    PICKUP: 'pickup',
    DELIVERY: 'delivery',
    PREORDER: 'preorder',
});

export const ACTIVE_ORDER_STATUSES: readonly string[] = Object.freeze([
    ORDER_STATUS.CONFIRMED,
    ORDER_STATUS.COOKING,
    ORDER_STATUS.READY,
]);

export const TERMINAL_ORDER_STATUSES: readonly string[] = Object.freeze([
    ORDER_STATUS.COMPLETED,
    ORDER_STATUS.CANCELLED,
]);

export const ORDER_TYPE_PRIORITY: Readonly<Record<string, number>> = Object.freeze({
    [ORDER_TYPE.PREORDER]: 4,
    [ORDER_TYPE.DINE_IN]: 3,
    [ORDER_TYPE.PICKUP]: 2,
    [ORDER_TYPE.DELIVERY]: 1,
});

export function getOrderPriority(order: Order): number {
    if (order.type === ORDER_TYPE.PREORDER ||
        (order.type === ORDER_TYPE.DINE_IN && order.scheduled_at)) {
        return ORDER_TYPE_PRIORITY[ORDER_TYPE.PREORDER];
    }
    return ORDER_TYPE_PRIORITY[order.type] ?? 1;
}

export function isPreorder(order: Order): boolean {
    return Boolean(order.scheduled_at && !order.is_asap);
}

export function isActiveStatus(status: string): boolean {
    return ACTIVE_ORDER_STATUSES.includes(status);
}

export function isTerminalStatus(status: string): boolean {
    return TERMINAL_ORDER_STATUSES.includes(status);
}
