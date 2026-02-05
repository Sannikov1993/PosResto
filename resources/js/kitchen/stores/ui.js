/**
 * UI Store
 *
 * Pinia store for managing UI state including alerts,
 * modals, and notifications.
 *
 * @module kitchen/stores/ui
 */

import { defineStore } from 'pinia';
import { ALERT_CONFIG } from '../constants/thresholds.js';

/**
 * @typedef {import('../types').Order} Order
 * @typedef {import('../types').CancellationData} CancellationData
 * @typedef {import('../types').StopListItem} StopListItem
 */

export const useUiStore = defineStore('kitchen-ui', {
    state: () => ({
        // Alerts
        /** @type {boolean} */
        showNewOrderAlert: false,

        /** @type {string} */
        newOrderNumber: '',

        /** @type {boolean} */
        showCancellationAlert: false,

        /** @type {CancellationData} */
        cancellationData: {},

        /** @type {boolean} */
        showStopListAlert: false,

        /** @type {Object} */
        stopListData: {},

        /** @type {boolean} */
        showOverdueAlert: false,

        /** @type {Object} */
        overdueAlertData: {},

        /** @type {number} */
        lastOverdueAlertTime: 0,

        // Toasts
        /** @type {boolean} */
        showWaiterCallSuccess: false,

        /** @type {Object} */
        waiterCallData: {},

        // Modals
        /** @type {boolean} */
        showDishModal: false,

        /** @type {Object|null} */
        selectedDish: null,

        /** @type {Array} */
        selectedItemModifiers: [],

        /** @type {string} */
        selectedItemComment: '',

        // Dropdowns
        /** @type {boolean} */
        showCalendarPicker: false,

        /** @type {boolean} */
        showStopListDropdown: false,

        // Stop list data
        /** @type {StopListItem[]} */
        stopList: [],

        // Current time display
        /** @type {string} */
        currentTime: '',

        /** @type {string} */
        currentDate: '',
    }),

    actions: {
        // ==================== NEW ORDER ALERT ====================

        /**
         * Show new order alert
         * @param {string} orderNumber - Order number to display
         */
        showNewOrder(orderNumber) {
            this.newOrderNumber = orderNumber;
            this.showNewOrderAlert = true;

            // Auto-dismiss
            setTimeout(() => {
                this.dismissNewOrderAlert();
            }, ALERT_CONFIG.NEW_ORDER_ALERT_DURATION);
        },

        /**
         * Dismiss new order alert
         */
        dismissNewOrderAlert() {
            this.showNewOrderAlert = false;
        },

        // ==================== CANCELLATION ALERT ====================

        /**
         * Show cancellation alert
         * @param {CancellationData} data - Cancellation data
         */
        showCancellation(data) {
            this.cancellationData = data;
            this.showCancellationAlert = true;
        },

        /**
         * Dismiss cancellation alert
         */
        dismissCancellationAlert() {
            this.showCancellationAlert = false;
        },

        // ==================== STOP LIST ALERT ====================

        /**
         * Show stop list alert
         * @param {Object} data - Stop list data
         */
        showStopListChange(data) {
            this.stopListData = data;
            this.showStopListAlert = true;
        },

        /**
         * Dismiss stop list alert
         */
        dismissStopListAlert() {
            this.showStopListAlert = false;
        },

        /**
         * Toggle stop list dropdown
         */
        toggleStopListDropdown() {
            this.showStopListDropdown = !this.showStopListDropdown;
        },

        /**
         * Set stop list data
         * @param {StopListItem[]} items
         */
        setStopList(items) {
            this.stopList = items;
        },

        // ==================== OVERDUE ALERT ====================

        /**
         * Show overdue order alert
         * @param {Object} orderData - Overdue order data
         */
        showOverdue(orderData) {
            const now = Date.now();

            // Throttle alerts
            if (now - this.lastOverdueAlertTime < ALERT_CONFIG.OVERDUE_ALERT_INTERVAL) {
                return;
            }

            this.overdueAlertData = orderData;
            this.showOverdueAlert = true;
            this.lastOverdueAlertTime = now;

            // Auto-dismiss
            setTimeout(() => {
                this.dismissOverdueAlert();
            }, ALERT_CONFIG.OVERDUE_ALERT_DURATION);
        },

        /**
         * Dismiss overdue alert
         */
        dismissOverdueAlert() {
            this.showOverdueAlert = false;
        },

        // ==================== WAITER CALL TOAST ====================

        /**
         * Show waiter call success toast
         * @param {Object} data - Waiter call data
         */
        showWaiterCallToast(data) {
            this.waiterCallData = data;
            this.showWaiterCallSuccess = true;

            setTimeout(() => {
                this.dismissWaiterCallToast();
            }, ALERT_CONFIG.TOAST_DURATION);
        },

        /**
         * Dismiss waiter call toast
         */
        dismissWaiterCallToast() {
            this.showWaiterCallSuccess = false;
        },

        // ==================== DISH MODAL ====================

        /**
         * Open dish detail modal
         * @param {Object} dish - Dish object
         * @param {Array} [modifiers] - Item modifiers
         * @param {string} [comment] - Item comment
         */
        openDishModal(dish, modifiers = [], comment = '') {
            this.selectedDish = dish;
            this.selectedItemModifiers = modifiers;
            this.selectedItemComment = comment;
            this.showDishModal = true;
        },

        /**
         * Close dish detail modal
         */
        closeDishModal() {
            this.showDishModal = false;
            this.selectedDish = null;
            this.selectedItemModifiers = [];
            this.selectedItemComment = '';
        },

        // ==================== CALENDAR ====================

        /**
         * Toggle calendar picker
         */
        toggleCalendarPicker() {
            this.showCalendarPicker = !this.showCalendarPicker;
        },

        /**
         * Close calendar picker
         */
        closeCalendarPicker() {
            this.showCalendarPicker = false;
        },

        // ==================== TIME DISPLAY ====================

        /**
         * Update time display
         * @param {string} time - Current time string
         * @param {string} date - Current date string
         */
        updateTimeDisplay(time, date) {
            this.currentTime = time;
            this.currentDate = date;
        },

        // ==================== CLOSE ALL ====================

        /**
         * Close all dropdowns
         */
        closeAllDropdowns() {
            this.showCalendarPicker = false;
            this.showStopListDropdown = false;
        },

        /**
         * Dismiss all alerts
         */
        dismissAllAlerts() {
            this.showNewOrderAlert = false;
            this.showCancellationAlert = false;
            this.showStopListAlert = false;
            this.showOverdueAlert = false;
        },
    },
});
