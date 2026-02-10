/**
 * Kitchen Realtime Composable
 *
 * Provides real-time event handling via centralized RealtimeStore.
 *
 * @module kitchen/composables/useKitchenRealtime
 */

import { ref, watch, onBeforeUnmount, getCurrentInstance, computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useDeviceStore } from '../stores/device.js';
import { useOrdersStore } from '../stores/orders.js';
import { useUiStore } from '../stores/ui.js';
import { useKitchenNotifications } from './useKitchenNotifications.js';
import { useRealtimeStore } from '../../shared/stores/realtime.js';
import { useRealtimeEvents } from '../../shared/composables/useRealtimeEvents.js';
import { DEBOUNCE_CONFIG, debounce } from '../../shared/config/realtimeConfig.js';
import { createLogger } from '../../shared/services/logger.js';

const log = createLogger('KitchenRealtime');

// Initialize Laravel Echo for WebSocket
import '../../echo.js';

interface KitchenRealtimeOptions {
    autoConnect?: boolean;
    onConnected?: (() => void) | null;
    onDisconnected?: (() => void) | null;
}

export function useKitchenRealtime(options: KitchenRealtimeOptions = {}) {
    const {
        autoConnect = true,
        onConnected: onConnectedCb = null,
        onDisconnected: onDisconnectedCb = null,
    } = options;

    const deviceStore = useDeviceStore();
    const ordersStore = useOrdersStore();
    const uiStore = useUiStore();
    const realtimeStore = useRealtimeStore();

    const {
        playNewOrderSound,
        showCancellation,
        showStopListChange,
    } = useKitchenNotifications();

    const { connected, connecting } = storeToRefs(realtimeStore);

    const debouncedRefreshOrders = debounce(() => {
        if (deviceStore.isConfigured && deviceStore.deviceId) {
            ordersStore.fetchOrders(deviceStore.deviceId, deviceStore.stationSlug || undefined);
        }
    }, DEBOUNCE_CONFIG.apiRefresh);

    let eventHandlersSetup = false;

    function getRestaurantId(): number | undefined {
        return deviceStore.deviceData?.restaurant_id;
    }

    function connect(): void {
        const restaurantId = getRestaurantId();

        if (!restaurantId) {
            log.warn('No restaurant ID available');
            return;
        }

        realtimeStore.init(restaurantId, {
            channels: ['kitchen', 'orders', 'global'],
        });

        if (!eventHandlersSetup) {
            setupEventHandlers();
            eventHandlersSetup = true;
        }

        log.debug('Initialized with restaurant:', restaurantId);
    }

    function setupEventHandlers(): void {
        const { on } = useRealtimeEvents();

        on('kitchen_new', handleKitchenNew as any);
        on('kitchen_ready', handleKitchenReady as any);
        on('item_cancelled', handleItemCancelled as any);

        on('new_order', handleNewOrder as any);
        on('order_status', handleOrderUpdate as any);
        on('order_updated', handleOrderUpdate as any);
        on('order_cancelled', handleOrderUpdate as any);

        on('stop_list_changed', handleStopListChanged as any);

        on('connection:established', () => {
            log.debug('Connection established');
            onConnectedCb?.();
        });

        on('connection:lost', () => {
            log.debug('Connection lost');
            onDisconnectedCb?.();
        });

        log.debug('Event handlers set up');
    }

    function handleKitchenNew(data: Record<string, any>): void {
        uiStore.showNewOrder((data.order_number || data.orderNumber) as string);
        playNewOrderSound();
        refreshOrders();
    }

    function handleKitchenReady(_data: Record<string, any>): void {
        refreshOrders();
    }

    function handleItemCancelled(data: Record<string, any>): void {
        showCancellation({
            orderNumber: (data.order_number || data.orderNumber) as string,
            tableNumber: (data.table_number || data.tableNumber) as string,
            itemName: (data.item_name || data.itemName) as string,
            quantity: data.quantity as number,
            reason_label: (data.reason_label || data.reasonLabel || data.reason_type) as string,
            reason_comment: (data.reason_comment || data.reasonComment) as string,
        });
        refreshOrders();
    }

    function handleNewOrder(_data: Record<string, any>): void {
        refreshOrders();
    }

    function handleOrderUpdate(_data: Record<string, any>): void {
        refreshOrders();
    }

    function handleStopListChanged(data: Record<string, any>): void {
        showStopListChange(data);
        (uiStore as any).fetchStopList?.();
    }

    function refreshOrders(): void {
        debouncedRefreshOrders();
    }

    function disconnect(): void {
        debouncedRefreshOrders.cancel();
        realtimeStore.destroy();
        eventHandlersSetup = false;
    }

    function reconnect(): void {
        disconnect();
        connect();
    }

    watch(
        () => deviceStore.isConfigured,
        (isConfigured) => {
            if (isConfigured && autoConnect) {
                setTimeout(connect, 500);
            } else if (!isConfigured) {
                disconnect();
            }
        },
        { immediate: true }
    );

    watch(
        () => deviceStore.deviceData?.restaurant_id,
        (newId, oldId) => {
            if (newId && newId !== oldId && deviceStore.isConfigured) {
                reconnect();
            }
        }
    );

    const instance = getCurrentInstance();
    if (instance) {
        onBeforeUnmount(() => {
            debouncedRefreshOrders.cancel();
        });
    }

    return {
        connected,
        connecting,
        connectionError: computed(() => null),
        connect,
        disconnect,
        reconnect,
    };
}
