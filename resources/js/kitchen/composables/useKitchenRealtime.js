/**
 * Kitchen Realtime Composable
 *
 * Provides real-time event handling via centralized RealtimeStore.
 * Uses Enterprise Architecture with single WebSocket per application.
 *
 * Features:
 * - Uses centralized RealtimeStore for WebSocket management
 * - Automatic reconnection with exponential backoff
 * - Connection state tracking
 * - Debounced API refresh
 * - Proper cleanup on unmount
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

/**
 * Kitchen realtime composable
 * @param {Object} [options] - Configuration options
 * @param {boolean} [options.autoConnect=true] - Auto-connect when device is configured
 * @param {Function} [options.onConnected] - Callback when connected
 * @param {Function} [options.onDisconnected] - Callback when disconnected
 * @returns {Object} Realtime composable
 */
export function useKitchenRealtime(options = {}) {
    const {
        autoConnect = true,
        onConnected = null,
        onDisconnected = null,
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

    // Use refs from realtime store
    const { connected, connecting } = storeToRefs(realtimeStore);

    // Debounced refresh to prevent rapid API calls
    const debouncedRefreshOrders = debounce(() => {
        if (deviceStore.isConfigured && deviceStore.deviceId) {
            ordersStore.fetchOrders(deviceStore.deviceId, deviceStore.stationSlug);
        }
    }, DEBOUNCE_CONFIG.apiRefresh);

    // Track if we've set up event handlers
    let eventHandlersSetup = false;

    /**
     * Get restaurant ID from device data
     */
    function getRestaurantId() {
        return deviceStore.deviceData?.restaurant_id;
    }

    /**
     * Initialize connection and set up event handlers
     */
    function connect() {
        const restaurantId = getRestaurantId();

        if (!restaurantId) {
            log.warn('No restaurant ID available');
            return;
        }

        // Initialize centralized real-time store with kitchen-specific channels
        realtimeStore.init(restaurantId, {
            channels: ['kitchen', 'orders', 'global'],
        });

        // Set up event handlers only once
        if (!eventHandlersSetup) {
            setupEventHandlers();
            eventHandlersSetup = true;
        }

        log.debug('Initialized with restaurant:', restaurantId);
    }

    /**
     * Set up event handlers using useRealtimeEvents composable
     */
    function setupEventHandlers() {
        const { on } = useRealtimeEvents();

        // Kitchen-specific events
        on('kitchen_new', handleKitchenNew);
        on('kitchen_ready', handleKitchenReady);
        on('item_cancelled', handleItemCancelled);

        // General order events
        on('new_order', handleNewOrder);
        on('order_status', handleOrderUpdate);
        on('order_updated', handleOrderUpdate);
        on('order_cancelled', handleOrderUpdate);

        // Global events
        on('stop_list_changed', handleStopListChanged);

        // Connection events
        on('connection:established', () => {
            log.debug('Connection established');
            onConnected?.();
        });

        on('connection:lost', () => {
            log.debug('Connection lost');
            onDisconnected?.();
        });

        log.debug('Event handlers set up');
    }

    /**
     * Handle new order arriving at kitchen
     */
    function handleKitchenNew(data) {
        // Show notification
        uiStore.showNewOrder(data.order_number || data.orderNumber);
        playNewOrderSound();

        // Refresh orders list
        refreshOrders();
    }

    /**
     * Handle order ready event
     */
    function handleKitchenReady(data) {
        // Refresh orders to move to ready column
        refreshOrders();
    }

    /**
     * Handle item cancellation
     */
    function handleItemCancelled(data) {
        // Show cancellation alert
        showCancellation({
            orderNumber: data.order_number || data.orderNumber,
            tableNumber: data.table_number || data.tableNumber,
            itemName: data.item_name || data.itemName,
            quantity: data.quantity,
            reason: data.reason_label || data.reasonLabel || data.reason_type,
            comment: data.reason_comment || data.reasonComment,
        });

        // Refresh orders
        refreshOrders();
    }

    /**
     * Handle new order created (general)
     */
    function handleNewOrder(data) {
        // The kitchen_new event is more specific, but we can refresh on general new_order too
        refreshOrders();
    }

    /**
     * Handle order update (status change, etc)
     */
    function handleOrderUpdate(data) {
        refreshOrders();
    }

    /**
     * Handle stop list changes
     */
    function handleStopListChanged(data) {
        showStopListChange(data);

        // Refresh stop list in UI store
        uiStore.fetchStopList?.();
    }

    /**
     * Refresh orders from API (debounced to prevent rapid calls)
     */
    function refreshOrders() {
        debouncedRefreshOrders();
    }

    /**
     * Disconnect and cleanup
     */
    function disconnect() {
        debouncedRefreshOrders.cancel();
        realtimeStore.destroy();
        eventHandlersSetup = false;
    }

    /**
     * Reconnect
     */
    function reconnect() {
        disconnect();
        connect();
    }

    // Watch for device configuration changes
    watch(
        () => deviceStore.isConfigured,
        (isConfigured) => {
            if (isConfigured && autoConnect) {
                // Small delay to ensure everything is ready
                setTimeout(connect, 500);
            } else if (!isConfigured) {
                disconnect();
            }
        },
        { immediate: true }
    );

    // Also watch for restaurant ID changes
    watch(
        () => deviceStore.deviceData?.restaurant_id,
        (newId, oldId) => {
            if (newId && newId !== oldId && deviceStore.isConfigured) {
                reconnect();
            }
        }
    );

    // Cleanup on unmount - only if called within component context
    const instance = getCurrentInstance();
    if (instance) {
        onBeforeUnmount(() => {
            debouncedRefreshOrders.cancel();
            // Note: Don't call disconnect() here as it would destroy the shared store
            // The useRealtimeEvents composable handles cleanup automatically
        });
    }

    return {
        // State (from centralized store)
        connected,
        connecting,
        connectionError: computed(() => null), // Deprecated, kept for compatibility

        // Actions
        connect,
        disconnect,
        reconnect,
    };
}
