/**
 * Prop Validators
 *
 * Reusable validation functions for Vue component props.
 *
 * @module kitchen/utils/validators
 */

import type { Order, OrderItem, TimeSlot } from '../types/index.js';

export function isValidOrder(order: unknown): order is Order {
    if (!order || typeof order !== 'object') return false;
    const o = order as Record<string, any>;
    if (typeof o.id !== 'number' && typeof o.id !== 'string') return false;
    if (typeof o.order_number !== 'number' && typeof o.order_number !== 'string') return false;
    return true;
}

export function isValidOrderWithItems(order: unknown): order is Order {
    if (!isValidOrder(order)) return false;
    if (!Array.isArray((order as Order).items)) return false;
    return true;
}

export function isValidOrderItem(item: unknown): item is OrderItem {
    if (!item || typeof item !== 'object') return false;
    const i = item as Record<string, any>;
    if (typeof i.id !== 'number' && typeof i.id !== 'string') return false;
    if (typeof i.name !== 'string') return false;
    if (typeof i.quantity !== 'number') return false;
    return true;
}

export function isValidOrdersArray(orders: unknown): orders is Order[] {
    if (!Array.isArray(orders)) return false;
    return orders.every(isValidOrder);
}

export function isValidTimeSlot(slot: unknown): slot is TimeSlot {
    if (!slot || typeof slot !== 'object') return false;
    const s = slot as Record<string, any>;
    if (typeof s.key !== 'string') return false;
    if (typeof s.label !== 'string') return false;
    if (!Array.isArray(s.orders)) return false;
    return true;
}

export function isValidTimeSlotsArray(slots: unknown): slots is TimeSlot[] {
    if (!Array.isArray(slots)) return false;
    return slots.every(isValidTimeSlot);
}

export function isValidTailwindColor(color: string): boolean {
    const validColors = [
        'blue', 'green', 'orange', 'red', 'yellow', 'purple',
        'pink', 'gray', 'amber', 'emerald', 'teal', 'cyan',
        'indigo', 'violet', 'fuchsia', 'rose', 'sky', 'lime',
    ];
    return validColors.includes(color);
}
