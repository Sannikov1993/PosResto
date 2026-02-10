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
import type { Order, OrderItem } from '../types/index.js';

const log = createLogger('KitchenOrders');

interface KitchenOrdersOptions {
    autoFetch?: boolean;
    autoPoll?: boolean;
    pollInterval?: number;
    useFallbackInterval?: boolean;
}

export function useKitchenOrders(options: KitchenOrdersOptions = {}) {
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

    const pollIntervalId = ref<ReturnType<typeof setInterval> | null>(null);
    const isPolling = ref(false);

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

    async function fetchOrders(): Promise<void> {
        if (!deviceStore.isConfigured || !deviceStore.deviceId) {
            return;
        }

        try {
            const result = await ordersStore.fetchOrders(
                deviceStore.deviceId,
                deviceStore.stationSlug || undefined
            );

            if (result?.newOrders?.length > 0) {
                const firstNew = result.newOrders[0];
                uiStore.showNewOrder(firstNew.order_number);
                playNewOrderSound();
            }
        } catch (err: any) {
            log.error('Failed to fetch orders:', err);
        }
    }

    function startPolling(fallbackMode = false): void {
        if (isPolling.value) return;

        const interval = fallbackMode
            ? POLLING_CONFIG.ORDERS_FALLBACK_INTERVAL
            : currentPollInterval;

        isPolling.value = true;
        pollIntervalId.value = setInterval(fetchOrders, interval);
        log.debug(`Polling started with ${interval}ms interval`);
    }

    function stopPolling(): void {
        if (pollIntervalId.value) {
            clearInterval(pollIntervalId.value);
            pollIntervalId.value = null;
            log.debug('Polling stopped');
        }
        isPolling.value = false;
    }

    function restartPolling(): void {
        stopPolling();
        fetchOrders();
        if (autoPoll) {
            startPolling();
        }
    }

    async function startCooking(order: Order): Promise<void> {
        try {
            await withRetry(
                () => ordersStore.startCooking(
                    order.id,
                    deviceStore.deviceId!,
                    deviceStore.stationSlug || undefined
                ),
                { context: 'startCooking', maxRetries: 2 }
            );
        } catch (err: any) {
            handleError(err as Error, { context: 'startCooking' });
            throw err;
        }
    }

    async function markReady(order: Order): Promise<void> {
        try {
            await withRetry(
                () => ordersStore.markReady(
                    order.id,
                    deviceStore.deviceId!,
                    deviceStore.stationSlug || undefined
                ),
                { context: 'markReady', maxRetries: 2 }
            );
            playReadySound();
        } catch (err: any) {
            handleError(err as Error, { context: 'markReady' });
            throw err;
        }
    }

    async function returnToNew(order: Order): Promise<void> {
        try {
            await withRetry(
                () => ordersStore.returnToNew(
                    order.id,
                    deviceStore.deviceId!,
                    deviceStore.stationSlug || undefined
                ),
                { context: 'returnToNew', maxRetries: 2 }
            );
        } catch (err: any) {
            handleError(err as Error, { context: 'returnToNew' });
            throw err;
        }
    }

    async function returnToCooking(order: Order): Promise<void> {
        try {
            await withRetry(
                () => ordersStore.returnToCooking(
                    order.id,
                    deviceStore.deviceId!,
                    deviceStore.stationSlug || undefined
                ),
                { context: 'returnToCooking', maxRetries: 2 }
            );
        } catch (err: any) {
            handleError(err as Error, { context: 'returnToCooking' });
            throw err;
        }
    }

    async function markItemReady(order: Order, item: OrderItem): Promise<void> {
        try {
            await withRetry(
                () => ordersStore.markItemReady(
                    order.id,
                    item.id,
                    deviceStore.deviceId!,
                    deviceStore.stationSlug || undefined
                ),
                { context: 'markItemReady', maxRetries: 2 }
            );
        } catch (err: any) {
            handleError(err as Error, { context: 'markItemReady' });
            throw err;
        }
    }

    function toggleItemDone(order: Order, item: OrderItem): void {
        ordersStore.toggleItemDone(order.id, item.id);
    }

    async function callWaiter(order: Order): Promise<void> {
        try {
            const response = await withRetry(
                () => ordersStore.callWaiter(order.id, deviceStore.deviceId!),
                { context: 'callWaiter', maxRetries: 2 }
            );
            uiStore.showWaiterCallToast({
                waiterName: (response as any).data?.waiter_name || 'Официант',
                orderNumber: order.order_number,
                tableName: order.table?.name || order.table?.number,
            });
        } catch (err: any) {
            handleError(err as Error, { context: 'callWaiter' });
            throw err;
        }
    }

    function setDate(date: string): void {
        ordersStore.setSelectedDate(date);
        fetchOrders();
    }

    function goToPreviousDay(): void {
        if (isSelectedDateToday.value) return;
        ordersStore.goToPreviousDay();
        fetchOrders();
    }

    function goToNextDay(): void {
        ordersStore.goToNextDay();
        fetchOrders();
    }

    function resetToToday(): void {
        ordersStore.resetToToday();
        fetchOrders();
    }

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
        setDate,
        goToPreviousDay,
        goToNextDay,
        resetToToday,
    };
}
