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
import type { CancellationData, StopListItem, DishInfo, OrderModifier } from '../types/index.js';

export const useUiStore = defineStore('kitchen-ui', {
    state: () => ({
        // Alerts
        showNewOrderAlert: false,
        newOrderNumber: '' as string,
        showCancellationAlert: false,
        cancellationData: {} as CancellationData,
        showStopListAlert: false,
        stopListData: {} as Record<string, any>,
        showOverdueAlert: false,
        overdueAlertData: {} as Record<string, any>,
        lastOverdueAlertTime: 0,

        // Toasts
        showWaiterCallSuccess: false,
        waiterCallData: {} as Record<string, any>,

        // Modals
        showDishModal: false,
        selectedDish: null as DishInfo | null,
        selectedItemModifiers: [] as OrderModifier[],
        selectedItemComment: '' as string,

        // Dropdowns
        showCalendarPicker: false,
        showStopListDropdown: false,

        // Stop list data
        stopList: [] as StopListItem[],

        // Current time display
        currentTime: '' as string,
        currentDate: '' as string,
    }),

    actions: {
        showNewOrder(orderNumber: string | number) {
            this.newOrderNumber = String(orderNumber);
            this.showNewOrderAlert = true;

            setTimeout(() => {
                this.dismissNewOrderAlert();
            }, ALERT_CONFIG.NEW_ORDER_ALERT_DURATION);
        },

        dismissNewOrderAlert() {
            this.showNewOrderAlert = false;
        },

        showCancellation(data: CancellationData) {
            this.cancellationData = data;
            this.showCancellationAlert = true;
        },

        dismissCancellationAlert() {
            this.showCancellationAlert = false;
        },

        showStopListChange(data: Record<string, any>) {
            this.stopListData = data;
            this.showStopListAlert = true;
        },

        dismissStopListAlert() {
            this.showStopListAlert = false;
        },

        toggleStopListDropdown() {
            this.showStopListDropdown = !this.showStopListDropdown;
        },

        setStopList(items: StopListItem[]) {
            this.stopList = items;
        },

        showOverdue(orderData: Record<string, any>) {
            const now = Date.now();

            if (now - this.lastOverdueAlertTime < ALERT_CONFIG.OVERDUE_ALERT_INTERVAL) {
                return;
            }

            this.overdueAlertData = orderData;
            this.showOverdueAlert = true;
            this.lastOverdueAlertTime = now;

            setTimeout(() => {
                this.dismissOverdueAlert();
            }, ALERT_CONFIG.OVERDUE_ALERT_DURATION);
        },

        dismissOverdueAlert() {
            this.showOverdueAlert = false;
        },

        showWaiterCallToast(data: Record<string, any>) {
            this.waiterCallData = data;
            this.showWaiterCallSuccess = true;

            setTimeout(() => {
                this.dismissWaiterCallToast();
            }, ALERT_CONFIG.TOAST_DURATION);
        },

        dismissWaiterCallToast() {
            this.showWaiterCallSuccess = false;
        },

        openDishModal(dish: DishInfo, modifiers: OrderModifier[] = [], comment = '') {
            this.selectedDish = dish;
            this.selectedItemModifiers = modifiers;
            this.selectedItemComment = comment;
            this.showDishModal = true;
        },

        closeDishModal() {
            this.showDishModal = false;
            this.selectedDish = null;
            this.selectedItemModifiers = [];
            this.selectedItemComment = '';
        },

        toggleCalendarPicker() {
            this.showCalendarPicker = !this.showCalendarPicker;
        },

        closeCalendarPicker() {
            this.showCalendarPicker = false;
        },

        updateTimeDisplay(time: string, date: string) {
            this.currentTime = time;
            this.currentDate = date;
        },

        closeAllDropdowns() {
            this.showCalendarPicker = false;
            this.showStopListDropdown = false;
        },

        dismissAllAlerts() {
            this.showNewOrderAlert = false;
            this.showCancellationAlert = false;
            this.showStopListAlert = false;
            this.showOverdueAlert = false;
        },
    },
});
