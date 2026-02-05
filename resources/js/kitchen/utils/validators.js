/**
 * Prop Validators
 *
 * Reusable validation functions for Vue component props.
 *
 * @module kitchen/utils/validators
 */

/**
 * Validate order object has required properties
 * @param {Object} order - Order to validate
 * @returns {boolean} True if valid
 */
export function isValidOrder(order) {
    if (!order || typeof order !== 'object') return false;
    if (typeof order.id !== 'number' && typeof order.id !== 'string') return false;
    if (typeof order.order_number !== 'number' && typeof order.order_number !== 'string') return false;
    return true;
}

/**
 * Validate order with items
 * @param {Object} order - Order to validate
 * @returns {boolean} True if valid
 */
export function isValidOrderWithItems(order) {
    if (!isValidOrder(order)) return false;
    if (!Array.isArray(order.items)) return false;
    return true;
}

/**
 * Validate order item
 * @param {Object} item - Item to validate
 * @returns {boolean} True if valid
 */
export function isValidOrderItem(item) {
    if (!item || typeof item !== 'object') return false;
    if (typeof item.id !== 'number' && typeof item.id !== 'string') return false;
    if (typeof item.name !== 'string') return false;
    if (typeof item.quantity !== 'number') return false;
    return true;
}

/**
 * Validate array of orders
 * @param {Array} orders - Orders array to validate
 * @returns {boolean} True if valid
 */
export function isValidOrdersArray(orders) {
    if (!Array.isArray(orders)) return false;
    return orders.every(isValidOrder);
}

/**
 * Validate time slot object
 * @param {Object} slot - Time slot to validate
 * @returns {boolean} True if valid
 */
export function isValidTimeSlot(slot) {
    if (!slot || typeof slot !== 'object') return false;
    if (typeof slot.key !== 'string') return false;
    if (typeof slot.label !== 'string') return false;
    if (!Array.isArray(slot.orders)) return false;
    return true;
}

/**
 * Validate array of time slots
 * @param {Array} slots - Time slots array to validate
 * @returns {boolean} True if valid
 */
export function isValidTimeSlotsArray(slots) {
    if (!Array.isArray(slots)) return false;
    return slots.every(isValidTimeSlot);
}

/**
 * Validate color string (Tailwind color name)
 * @param {string} color - Color to validate
 * @returns {boolean} True if valid
 */
export function isValidTailwindColor(color) {
    const validColors = [
        'blue', 'green', 'orange', 'red', 'yellow', 'purple',
        'pink', 'gray', 'amber', 'emerald', 'teal', 'cyan',
        'indigo', 'violet', 'fuchsia', 'rose', 'sky', 'lime',
    ];
    return validColors.includes(color);
}
