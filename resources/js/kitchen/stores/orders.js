/**
 * Orders Store
 *
 * Pinia store for managing kitchen orders state.
 * Handles fetching, filtering, and order status updates.
 *
 * @module kitchen/stores/orders
 */

import { defineStore } from 'pinia';
import { orderApi } from '../services/api/orderApi.js';
import {
    ACTIVE_ORDER_STATUSES,
    ITEM_STATUS,
    isPreorder,
    getOrderPriority,
} from '../constants/orderStatus.js';
import {
    getTimeSlotKey,
    getTimeSlotLabel,
    getSlotUrgency,
    getCookingMinutes,
    getTodayString,
} from '../utils/time.js';
import { OVERDUE_THRESHOLDS } from '../constants/thresholds.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('KitchenOrders');

/**
 * @typedef {import('../types').Order} Order
 * @typedef {import('../types').ProcessedOrder} ProcessedOrder
 * @typedef {import('../types').TimeSlot} TimeSlot
 */

export const useOrdersStore = defineStore('kitchen-orders', {
    state: () => ({
        /** @type {Order[]} */
        orders: [],

        /**
         * Selected date (YYYY-MM-DD)
         * Initially null - will be computed lazily when timezone is set
         * This prevents race condition where date is calculated with UTC
         * before restaurant's timezone is loaded from API
         * @type {string|null}
         */
        _selectedDate: null,

        /** @type {boolean} Flag indicating if date has been explicitly set by user */
        _dateExplicitlySet: false,

        /** @type {Object.<string, number>} Order counts by date for calendar */
        orderCountsByDate: {},

        /** @type {Set<number>} IDs of orders we've seen (to detect new orders) */
        seenOrderIds: new Set(),

        /** @type {Object.<string, boolean>} Item done states (key: orderId-itemId) */
        itemDoneState: {},

        /** @type {Set<number>} Order IDs where waiter has been called */
        waiterCalledOrders: new Set(),

        /** @type {boolean} */
        isLoading: false,

        /** @type {Error|null} */
        error: null,

        /** @type {number|null} */
        lastFetchTime: null,
    }),

    getters: {
        /**
         * New orders (ASAP, not started cooking)
         * @returns {ProcessedOrder[]}
         */
        newOrders: (state) => {
            return state.orders
                .filter(o => ACTIVE_ORDER_STATUSES.includes(o.status))
                .filter(o => !isPreorder(o))
                .map(o => ({
                    ...o,
                    items: (o.items || []).filter(i =>
                        i.status === ITEM_STATUS.COOKING && !i.cooking_started_at
                    ),
                }))
                .filter(o => o.items.length > 0)
                .sort((a, b) => {
                    // Sort by wait time first, then by priority
                    const waitA = a.created_at ? Date.now() - new Date(a.created_at).getTime() : 0;
                    const waitB = b.created_at ? Date.now() - new Date(b.created_at).getTime() : 0;

                    const waitDiffMinutes = Math.abs(waitA - waitB) / 60000;
                    if (waitDiffMinutes > 5) {
                        return waitB - waitA; // Older orders first
                    }

                    const priorityA = getOrderPriority(a);
                    const priorityB = getOrderPriority(b);
                    if (priorityA !== priorityB) {
                        return priorityB - priorityA;
                    }

                    return waitB - waitA;
                });
        },

        /**
         * Orders currently being cooked
         * @returns {ProcessedOrder[]}
         */
        cookingOrders: (state) => {
            return state.orders
                .filter(o => ACTIVE_ORDER_STATUSES.includes(o.status))
                .map(o => ({
                    ...o,
                    items: (o.items || [])
                        .filter(i => i.status === ITEM_STATUS.COOKING && i.cooking_started_at)
                        .map(item => ({
                            ...item,
                            done: state.itemDoneState[`${o.id}-${item.id}`] || false,
                        })),
                    cookingMinutes: getCookingMinutes(o),
                }))
                .filter(o => o.items.length > 0)
                .sort((a, b) => {
                    // Oldest cooking orders first
                    const startA = a.cooking_started_at || a.updated_at;
                    const startB = b.cooking_started_at || b.updated_at;
                    if (!startA || !startB) return 0;
                    return new Date(startA) - new Date(startB);
                });
        },

        /**
         * Ready orders
         * @returns {ProcessedOrder[]}
         */
        readyOrders: (state) => {
            return state.orders
                .filter(o => ACTIVE_ORDER_STATUSES.includes(o.status))
                .map(o => ({
                    ...o,
                    items: (o.items || []).filter(i => i.status === ITEM_STATUS.READY),
                }))
                .filter(o => o.items.length > 0);
        },

        /**
         * Preorders (scheduled orders not yet started)
         * @returns {Order[]}
         */
        preorderOrders: (state) => {
            return state.orders
                .filter(o => isPreorder(o))
                .filter(o => !['completed', 'cancelled'].includes(o.status))
                .filter(o => {
                    const items = o.items || [];
                    if (items.length === 0) return true;
                    const cookingStarted = items.some(i => i.cooking_started_at);
                    const allDone = items.length > 0 &&
                        items.every(i => ['ready', 'served', 'cancelled'].includes(i.status));
                    return !cookingStarted && !allDone;
                })
                .map(o => ({
                    ...o,
                    items: (o.items || []).filter(i => i.status !== ITEM_STATUS.CANCELLED),
                }))
                .filter(o => !o.items || o.items.length > 0)
                .sort((a, b) => {
                    const timeA = a.scheduled_at || '';
                    const timeB = b.scheduled_at || '';
                    return timeA.localeCompare(timeB);
                });
        },

        /**
         * Total new orders count (preorders + ASAP)
         * @returns {number}
         */
        totalNewOrders() {
            return this.preorderOrders.length + this.newOrders.length;
        },

        /**
         * Preorders grouped by 30-minute time slots
         * @returns {TimeSlot[]}
         */
        preorderTimeSlots() {
            const slots = {};

            this.preorderOrders.forEach(order => {
                const slotKey = getTimeSlotKey(order.scheduled_at);
                if (!slotKey) return;

                if (!slots[slotKey]) {
                    slots[slotKey] = {
                        key: slotKey,
                        label: getTimeSlotLabel(slotKey),
                        orders: [],
                        urgency: 'normal',
                    };
                }
                slots[slotKey].orders.push(order);
            });

            return Object.values(slots)
                .map(slot => ({
                    ...slot,
                    urgency: getSlotUrgency(slot.key),
                }))
                .sort((a, b) => a.key.localeCompare(b.key));
        },

        /**
         * Overdue cooking orders
         * @returns {ProcessedOrder[]}
         */
        overdueOrders() {
            return this.cookingOrders
                .filter(o => o.cookingMinutes >= OVERDUE_THRESHOLDS.WARNING)
                .map(o => ({
                    ...o,
                    isWarning: o.cookingMinutes >= OVERDUE_THRESHOLDS.WARNING &&
                               o.cookingMinutes < OVERDUE_THRESHOLDS.CRITICAL,
                    isCritical: o.cookingMinutes >= OVERDUE_THRESHOLDS.CRITICAL &&
                                o.cookingMinutes < OVERDUE_THRESHOLDS.ALERT,
                    isAlert: o.cookingMinutes >= OVERDUE_THRESHOLDS.ALERT,
                }));
        },

        /**
         * Get selected date with lazy initialization
         * Returns stored date if explicitly set, otherwise computes today in restaurant's timezone
         * This ensures correct date even when store is created before timezone is loaded
         * @returns {string} Date in YYYY-MM-DD format
         */
        selectedDate: (state) => {
            // If date was explicitly set by user, use it
            if (state._dateExplicitlySet && state._selectedDate) {
                return state._selectedDate;
            }
            // Otherwise, compute "today" dynamically using current timezone
            // This will be correct once timezone is set from API
            return state._selectedDate || getTodayString();
        },

        /**
         * Check if selected date is today
         * Uses the selectedDate getter for consistency
         * @returns {boolean}
         */
        isSelectedDateToday() {
            return this.selectedDate === getTodayString();
        },
    },

    actions: {
        /**
         * Fetch orders from API
         * @param {string} deviceId - Device identifier
         * @param {string} [stationSlug] - Station filter
         */
        async fetchOrders(deviceId, stationSlug) {
            this.isLoading = true;
            this.error = null;

            try {
                const orders = await orderApi.getOrders({
                    deviceId,
                    date: this.selectedDate,
                    station: stationSlug,
                });

                const result = this._processOrders(orders);
                this.lastFetchTime = Date.now();
                return result;
            } catch (error) {
                this.error = error;
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Process fetched orders and detect new ones
         * @param {Order[]} allOrders
         * @returns {{newOrders: Order[]}} Object containing truly new orders
         */
        _processOrders(allOrders) {
            const newOrdersDetected = [];

            // Filter to active/preorder statuses
            const filtered = allOrders.filter(o => {
                if (isPreorder(o) && !['completed', 'cancelled'].includes(o.status)) {
                    return true;
                }
                return ACTIVE_ORDER_STATUSES.includes(o.status);
            });

            // Detect truly new orders
            filtered.forEach(order => {
                if (order.status === 'confirmed' && !this.seenOrderIds.has(order.id)) {
                    newOrdersDetected.push(order);
                }
                this.seenOrderIds.add(order.id);
            });

            this.orders = filtered;

            return { newOrders: newOrdersDetected };
        },

        /**
         * Fetch order counts by date for calendar
         * @param {string} deviceId
         * @param {string} startDate
         * @param {string} endDate
         * @param {string} [stationSlug]
         */
        async fetchOrderCounts(deviceId, startDate, endDate, stationSlug) {
            try {
                const counts = await orderApi.getOrderCountsByDate({
                    deviceId,
                    startDate,
                    endDate,
                    station: stationSlug,
                });
                this.orderCountsByDate = counts;
            } catch (error) {
                log.error('Failed to fetch order counts:', error);
            }
        },

        /**
         * Start cooking an order
         * @param {number} orderId
         * @param {string} deviceId
         * @param {string} [stationSlug]
         */
        async startCooking(orderId, deviceId, stationSlug) {
            await orderApi.startCooking(orderId, deviceId, stationSlug);
            await this.fetchOrders(deviceId, stationSlug);
        },

        /**
         * Mark order as ready
         * @param {number} orderId
         * @param {string} deviceId
         * @param {string} [stationSlug]
         */
        async markReady(orderId, deviceId, stationSlug) {
            await orderApi.markReady(orderId, deviceId, stationSlug);
            this._clearItemDoneState(orderId);
            await this.fetchOrders(deviceId, stationSlug);
        },

        /**
         * Return order to new state
         * @param {number} orderId
         * @param {string} deviceId
         * @param {string} [stationSlug]
         */
        async returnToNew(orderId, deviceId, stationSlug) {
            await orderApi.returnToNew(orderId, deviceId, stationSlug);
            this._clearItemDoneState(orderId);
            await this.fetchOrders(deviceId, stationSlug);
        },

        /**
         * Return order to cooking state
         * @param {number} orderId
         * @param {string} deviceId
         * @param {string} [stationSlug]
         */
        async returnToCooking(orderId, deviceId, stationSlug) {
            await orderApi.returnToCooking(orderId, deviceId, stationSlug);
            await this.fetchOrders(deviceId, stationSlug);
        },

        /**
         * Mark individual item as ready
         * @param {number} orderId
         * @param {number} itemId
         * @param {string} deviceId
         * @param {string} [stationSlug]
         */
        async markItemReady(orderId, itemId, deviceId, stationSlug) {
            await orderApi.markItemReady(itemId, deviceId);
            this.itemDoneState[`${orderId}-${itemId}`] = true;
            await this.fetchOrders(deviceId, stationSlug);
        },

        /**
         * Toggle item done state (UI only)
         * @param {number} orderId
         * @param {number} itemId
         */
        toggleItemDone(orderId, itemId) {
            const key = `${orderId}-${itemId}`;
            this.itemDoneState[key] = !this.itemDoneState[key];
        },

        /**
         * Clear item done states for an order
         * @param {number} orderId
         */
        _clearItemDoneState(orderId) {
            Object.keys(this.itemDoneState).forEach(key => {
                if (key.startsWith(`${orderId}-`)) {
                    delete this.itemDoneState[key];
                }
            });
        },

        /**
         * Call waiter for an order
         * @param {number} orderId
         * @param {string} deviceId
         */
        async callWaiter(orderId, deviceId) {
            const response = await orderApi.callWaiter(orderId, deviceId);
            this.waiterCalledOrders.add(orderId);
            return response;
        },

        /**
         * Set selected date explicitly
         * @param {string} date - Date in YYYY-MM-DD format
         */
        setSelectedDate(date) {
            this._selectedDate = date;
            this._dateExplicitlySet = true;
        },

        /**
         * Go to previous day
         */
        goToPreviousDay() {
            const currentDate = this.selectedDate; // Use getter
            const [year, month, day] = currentDate.split('-').map(Number);
            const date = new Date(year, month - 1, day - 1);
            const newYear = date.getFullYear();
            const newMonth = String(date.getMonth() + 1).padStart(2, '0');
            const newDay = String(date.getDate()).padStart(2, '0');
            this._selectedDate = `${newYear}-${newMonth}-${newDay}`;
            this._dateExplicitlySet = true;
        },

        /**
         * Go to next day
         */
        goToNextDay() {
            const currentDate = this.selectedDate; // Use getter
            const [year, month, day] = currentDate.split('-').map(Number);
            const date = new Date(year, month - 1, day + 1);
            const newYear = date.getFullYear();
            const newMonth = String(date.getMonth() + 1).padStart(2, '0');
            const newDay = String(date.getDate()).padStart(2, '0');
            this._selectedDate = `${newYear}-${newMonth}-${newDay}`;
            this._dateExplicitlySet = true;
        },

        /**
         * Reset to today (in restaurant's timezone)
         * Clears explicit date to force lazy recomputation
         * This is called when timezone changes to ensure correct date
         */
        resetToToday() {
            this._selectedDate = null;
            this._dateExplicitlySet = false;
            // Force immediate recalculation for any watchers
            // The getter will now return getTodayString() with correct timezone
        },
    },
});
