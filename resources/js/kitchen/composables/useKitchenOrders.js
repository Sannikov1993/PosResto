/**
 * Kitchen Orders Composable
 *
 * Provides order management functionality with automatic polling.
 *
 * @module kitchen/composables/useKitchenOrders
 */

import { computed, onMounted, onUnmounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useOrdersStore } from '../stores/orders.js';
import { useDeviceStore } from '../stores/device.js';
import { useUiStore } from '../stores/ui.js';
import { useKitchenNotifications } from './useKitchenNotifications.js';
import { useErrorHandler } from './useErrorHandler.js';
import { POLLING_CONFIG } from '../constants/thresholds.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('KitchenOrders');

/**
 * Kitchen orders composable
 * @param {Object} [options] - Configuration options
 * @param {boolean} [options.autoFetch=true] - Auto-fetch on mount
 * @param {boolean} [options.autoPoll=true] - Auto-start polling
 * @param {number} [options.pollInterval] - Custom poll interval
 * @param {boolean} [options.useFallbackInterval=false] - Use slower fallback interval
 * @returns {Object} Orders composable
 */
export function useKitchenOrders(options = {}) {
    const {
        autoFetch = true,
        autoPoll = true,
        pollInterval = POLLING_CONFIG.ORDERS_INTERVAL,
        useFallbackInterval = false,
    } = options;

    const currentPollInterval = useFallbackInterval
        ? POLLING_CONFIG.ORDERS_FALLBACK_INTERVAL
        : pollInterval;

    const ordersStore = useOrdersStore();
    const deviceStore = useDeviceStore();
    const uiStore = useUiStore();
    const { playNewOrderSound, playReadySound } = useKitchenNotifications();
    const { handleError, withRetry } = useErrorHandler();

    // Refs
    const pollIntervalId = ref(null);
    const isPolling = ref(false);

    // Store refs
    const {
        orders,
        newOrders,
        cookingOrders,
        readyOrders,
        preorderOrders,
        totalNewOrders,
        preorderTimeSlots,
        overdueOrders,
        selectedDate,
        isSelectedDateToday,
        isLoading,
        error,
        itemDoneState,
        waiterCalledOrders,
    } = storeToRefs(ordersStore);

    /**
     * Fetch orders from API
     */
    async function fetchOrders() {
        if (!deviceStore.isConfigured || !deviceStore.deviceId) {
            return;
        }

        try {
            const result = await ordersStore.fetchOrders(
                deviceStore.deviceId,
                deviceStore.stationSlug
            );

            // Check for new orders and show notification
            if (result?.newOrders?.length > 0) {
                const firstNew = result.newOrders[0];
                uiStore.showNewOrder(firstNew.order_number);
                playNewOrderSound();
            }
        } catch (err) {
            log.error('Failed to fetch orders:', err);
        }
    }

    /**
     * Start polling for orders
     * @param {boolean} [fallbackMode=false] - Use slower fallback interval
     */
    function startPolling(fallbackMode = false) {
        if (isPolling.value) return;

        const interval = fallbackMode
            ? POLLING_CONFIG.ORDERS_FALLBACK_INTERVAL
            : currentPollInterval;

        isPolling.value = true;
        pollIntervalId.value = setInterval(fetchOrders, interval);
        log.debug(`Polling started with ${interval}ms interval`);
    }

    /**
     * Stop polling
     */
    function stopPolling() {
        if (pollIntervalId.value) {
            clearInterval(pollIntervalId.value);
            pollIntervalId.value = null;
            log.debug('Polling stopped');
        }
        isPolling.value = false;
    }

    /**
     * Restart polling (e.g., after date change)
     */
    function restartPolling() {
        stopPolling();
        fetchOrders();
        if (autoPoll) {
            startPolling();
        }
    }

    // ==================== Order Actions ====================

    /**
     * Start cooking an order
     * @param {Object} order - Order to start
     */
    async function startCooking(order) {
        try {
            await withRetry(
                () => ordersStore.startCooking(
                    order.id,
                    deviceStore.deviceId,
                    deviceStore.stationSlug
                ),
                { context: 'startCooking', maxRetries: 2 }
            );
        } catch (err) {
            handleError(err, { context: 'startCooking' });
            throw err;
        }
    }

    /**
     * Mark order as ready
     * @param {Object} order - Order to mark ready
     */
    async function markReady(order) {
        try {
            await withRetry(
                () => ordersStore.markReady(
                    order.id,
                    deviceStore.deviceId,
                    deviceStore.stationSlug
                ),
                { context: 'markReady', maxRetries: 2 }
            );
            playReadySound();
        } catch (err) {
            handleError(err, { context: 'markReady' });
            throw err;
        }
    }

    /**
     * Return order to new state
     * @param {Object} order
     */
    async function returnToNew(order) {
        try {
            await withRetry(
                () => ordersStore.returnToNew(
                    order.id,
                    deviceStore.deviceId,
                    deviceStore.stationSlug
                ),
                { context: 'returnToNew', maxRetries: 2 }
            );
        } catch (err) {
            handleError(err, { context: 'returnToNew' });
            throw err;
        }
    }

    /**
     * Return order to cooking state
     * @param {Object} order
     */
    async function returnToCooking(order) {
        try {
            await withRetry(
                () => ordersStore.returnToCooking(
                    order.id,
                    deviceStore.deviceId,
                    deviceStore.stationSlug
                ),
                { context: 'returnToCooking', maxRetries: 2 }
            );
        } catch (err) {
            handleError(err, { context: 'returnToCooking' });
            throw err;
        }
    }

    /**
     * Mark individual item as ready
     * @param {Object} order
     * @param {Object} item
     */
    async function markItemReady(order, item) {
        try {
            await withRetry(
                () => ordersStore.markItemReady(
                    order.id,
                    item.id,
                    deviceStore.deviceId,
                    deviceStore.stationSlug
                ),
                { context: 'markItemReady', maxRetries: 2 }
            );
        } catch (err) {
            handleError(err, { context: 'markItemReady' });
            throw err;
        }
    }

    /**
     * Toggle item done state (UI only)
     * @param {Object} order
     * @param {Object} item
     */
    function toggleItemDone(order, item) {
        ordersStore.toggleItemDone(order.id, item.id);
    }

    /**
     * Call waiter for order
     * @param {Object} order
     */
    async function callWaiter(order) {
        try {
            const response = await withRetry(
                () => ordersStore.callWaiter(order.id, deviceStore.deviceId),
                { context: 'callWaiter', maxRetries: 2 }
            );
            uiStore.showWaiterCallToast({
                waiterName: response.data?.waiter_name || 'Официант',
                orderNumber: order.order_number,
                tableName: order.table?.name || order.table?.number,
            });
        } catch (err) {
            handleError(err, { context: 'callWaiter' });
            throw err;
        }
    }

    // ==================== Date Management ====================

    /**
     * Set selected date and refetch
     * @param {string} date - Date in YYYY-MM-DD format
     */
    function setDate(date) {
        ordersStore.setSelectedDate(date);
        fetchOrders();
    }

    /**
     * Go to previous day
     */
    function goToPreviousDay() {
        if (isSelectedDateToday.value) return;
        ordersStore.goToPreviousDay();
        fetchOrders();
    }

    /**
     * Go to next day
     */
    function goToNextDay() {
        ordersStore.goToNextDay();
        fetchOrders();
    }

    /**
     * Reset to today
     */
    function resetToToday() {
        ordersStore.resetToToday();
        fetchOrders();
    }

    // ==================== Lifecycle ====================

    onMounted(() => {
        if (autoFetch && deviceStore.isConfigured) {
            fetchOrders();
        }
        if (autoPoll && deviceStore.isConfigured) {
            startPolling();
        }
    });

    onUnmounted(() => {
        stopPolling();
    });

    return {
        // State
        orders,
        newOrders,
        cookingOrders,
        readyOrders,
        preorderOrders,
        totalNewOrders,
        preorderTimeSlots,
        overdueOrders,
        selectedDate,
        isSelectedDateToday,
        isLoading,
        error,
        itemDoneState,
        waiterCalledOrders,
        isPolling,

        // Actions
        fetchOrders,
        startPolling,
        stopPolling,
        restartPolling,
        startCooking,
        markReady,
        returnToNew,
        returnToCooking,
        markItemReady,
        toggleItemDone,
        callWaiter,

        // Date management
        setDate,
        goToPreviousDay,
        goToNextDay,
        resetToToday,
    };
}
