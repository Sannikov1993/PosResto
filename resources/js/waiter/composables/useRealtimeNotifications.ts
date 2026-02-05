/**
 * Waiter App - Realtime Notifications Composable
 *
 * Uses Enterprise Real-time Architecture with centralized RealtimeStore.
 *
 * Features:
 * - Single WebSocket connection via RealtimeStore
 * - Automatic subscription cleanup on unmount
 * - Shared notification sounds
 * - Debounced API refresh
 * - Connection state tracking
 *
 * @module waiter/composables/useRealtimeNotifications
 */

import { ref, onMounted, onUnmounted, watch, getCurrentInstance, computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/waiter/stores/auth';
import { useOrdersStore } from '@/waiter/stores/orders';
import { useTablesStore } from '@/waiter/stores/tables';
import { useUiStore } from '@/waiter/stores/ui';
import { useRealtimeStore } from '../../shared/stores/realtime.js';
import { useRealtimeEvents } from '../../shared/composables/useRealtimeEvents.js';
import { playSound } from '../../shared/services/notificationSound.js';
import { DEBOUNCE_CONFIG, debounce } from '../../shared/config/realtimeConfig.js';

// Initialize Laravel Echo for WebSocket
import '../../echo.js';

// Event interfaces
interface KitchenReadyEvent {
  order_id: number;
  order_number?: string;
  item_id?: number;
  dish_name?: string;
  table_number?: string;
  message?: string;
}

interface TableStatusEvent {
  table_id: number;
  status: string;
  order_id?: number;
}

interface OrderEvent {
  order_id: number;
  order_number?: string;
  status?: string;
  old_status?: string;
  new_status?: string;
  table_id?: number;
  message?: string;
}

interface StopListEvent {
  message?: string;
}

export function useRealtimeNotifications() {
  const authStore = useAuthStore();
  const ordersStore = useOrdersStore();
  const tablesStore = useTablesStore();
  const uiStore = useUiStore();
  const realtimeStore = useRealtimeStore();

  // State from centralized store
  const { connected: isConnected, connecting: isConnecting } = storeToRefs(realtimeStore);

  // Local state
  const readyItemsCount = ref(0);
  const connectionError = ref<string | null>(null);

  // Track if handlers are set up
  let handlersSetup = false;

  // Debounced API refresh functions
  const debouncedFetchOrders = debounce(() => {
    ordersStore.fetchOrders(true);
  }, DEBOUNCE_CONFIG.apiRefresh);

  const debouncedFetchTables = debounce(() => {
    tablesStore.fetchAll();
  }, DEBOUNCE_CONFIG.apiRefresh);

  /**
   * Get restaurant ID from auth store
   */
  function getRestaurantId(): number | null {
    // Try auth store first
    if (authStore.restaurantId) {
      return authStore.restaurantId;
    }

    // Fallback to localStorage
    try {
      const session = JSON.parse(localStorage.getItem('menulab_session') || '{}');
      if (session?.user?.restaurant_id) {
        return session.user.restaurant_id;
      }
    } catch {
      // ignore
    }
    return null;
  }

  /**
   * Initialize connection and set up event handlers
   */
  function connect(): void {
    const restaurantId = getRestaurantId();
    if (!restaurantId) {
      console.warn('[Waiter Realtime] No restaurant_id, skipping connection');
      return;
    }

    // Initialize centralized real-time store
    realtimeStore.init(restaurantId, {
      channels: ['kitchen', 'orders', 'tables', 'global'],
    });

    // Set up event handlers only once
    if (!handlersSetup) {
      setupEventHandlers();
      handlersSetup = true;
    }

    console.log('[Waiter Realtime] Initialized with restaurant:', restaurantId);
  }

  /**
   * Set up event handlers using useRealtimeEvents composable
   */
  function setupEventHandlers(): void {
    const { on } = useRealtimeEvents();

    // Kitchen events
    on('kitchen_ready', handleKitchenReady);
    on('item_cancelled', handleItemCancelled);

    // Order events
    on('new_order', handleNewOrder);
    on('order_status', handleOrderStatus);
    on('order_updated', handleOrderUpdate);
    on('order_paid', handleOrderUpdate);
    on('order_cancelled', handleOrderUpdate);

    // Table events
    on('table_status', handleTableStatus);

    // Global events
    on('stop_list_changed', handleStopListChanged);

    console.log('[Waiter Realtime] Event handlers set up');
  }

  /**
   * Handle kitchen ready event
   */
  function handleKitchenReady(data: KitchenReadyEvent): void {
    // Play notification sound
    playNotificationSound('ready');

    // Show toast notification
    const message = data.message || `${data.dish_name || 'Блюдо'} готово`;
    const tableInfo = data.table_number ? ` (Стол ${data.table_number})` : '';
    uiStore.showSuccess(message + tableInfo, 5000);

    // Refresh orders (debounced)
    debouncedFetchOrders();
    updateReadyCount();
  }

  /**
   * Handle item cancelled event
   */
  function handleItemCancelled(data: any): void {
    playNotificationSound('alert');

    const message = `Отменено: ${data.item_name || 'позиция'}`;
    const tableInfo = data.table_number ? ` (Стол ${data.table_number})` : '';
    uiStore.showWarning(message + tableInfo, 5000);

    debouncedFetchOrders();
  }

  /**
   * Handle new order event
   */
  function handleNewOrder(data: OrderEvent): void {
    // Refresh orders (debounced)
    debouncedFetchOrders();
  }

  /**
   * Handle order status change
   */
  function handleOrderStatus(data: OrderEvent): void {
    const { new_status, order_number } = data;

    // Show notification for important status changes
    if (new_status === 'ready') {
      playNotificationSound('ready');
      uiStore.showSuccess(`Заказ #${order_number} готов!`, 5000);
    }

    debouncedFetchOrders();
    updateReadyCount();
  }

  /**
   * Handle general order update
   */
  function handleOrderUpdate(data: OrderEvent): void {
    debouncedFetchOrders();
  }

  /**
   * Handle table status change
   */
  function handleTableStatus(data: TableStatusEvent): void {
    // Refresh tables list (debounced)
    debouncedFetchTables();
  }

  /**
   * Handle stop list changed
   */
  function handleStopListChanged(data: StopListEvent): void {
    uiStore.showWarning(data.message || 'Стоп-лист обновлён', 4000);
  }

  /**
   * Play notification sound using shared audio service
   */
  function playNotificationSound(type: 'ready' | 'alert' | 'new' = 'ready'): void {
    const soundMap: Record<string, string> = {
      ready: 'ready',
      alert: 'alert',
      new: 'newOrder',
    };
    playSound(soundMap[type] || 'beep');
  }

  /**
   * Update ready items count
   */
  function updateReadyCount(): void {
    readyItemsCount.value = ordersStore.ordersWithReadyItems?.length || 0;
  }

  /**
   * Disconnect and cleanup
   */
  function disconnect(): void {
    debouncedFetchOrders.cancel();
    debouncedFetchTables.cancel();
    realtimeStore.destroy();
    handlersSetup = false;
  }

  /**
   * Reconnect to channels
   */
  function reconnect(): void {
    disconnect();
    connect();
  }

  // Watch for auth changes to reconnect
  watch(
    () => authStore.restaurantId,
    (newId, oldId) => {
      if (newId && newId !== oldId) {
        reconnect();
      } else if (!newId) {
        disconnect();
      }
    }
  );

  // Lifecycle - only register hooks if called within component context
  const instance = getCurrentInstance();
  if (instance) {
    onMounted(() => {
      if (authStore.isAuthenticated) {
        connect();
      }
      updateReadyCount();
    });

    onUnmounted(() => {
      debouncedFetchOrders.cancel();
      debouncedFetchTables.cancel();
      // Note: Don't call disconnect() as it would destroy shared store
      // useRealtimeEvents handles cleanup automatically
    });
  }

  return {
    // State
    isConnected,
    isConnecting,
    readyItemsCount,
    connectionError: computed(() => connectionError.value),
    retryCount: computed(() => realtimeStore.reconnectCount),

    // Actions
    connect,
    disconnect,
    reconnect,
    updateReadyCount,
  };
}

// Declare Echo on window for TypeScript
declare global {
  interface Window {
    Echo: any;
    AudioContext: typeof AudioContext;
    webkitAudioContext: typeof AudioContext;
  }
}
