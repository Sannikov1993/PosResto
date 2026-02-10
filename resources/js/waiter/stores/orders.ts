/**
 * Waiter App - Orders Store
 * Manages orders and order items state
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { ordersApi } from '@/waiter/services';
import { useTablesStore } from './tables';
import type {
  Order,
  OrderItem,
  OrderStatus,
  AddOrderItemRequest,
  PayOrderRequest,
  PaymentMethod,
} from '@/waiter/types';

export const useOrdersStore = defineStore('waiter-orders', () => {
  // === State ===
  const orders = ref<Order[]>([]);
  const currentOrder = ref<Order | null>(null);
  const isLoading = ref(false);
  const isSaving = ref(false);
  const error = ref<string | null>(null);
  const lastFetchTime = ref<number>(0);

  // === Cache Settings ===
  const CACHE_TTL = 15000; // 15 seconds

  // === Getters ===

  /**
   * Active orders (not paid/cancelled)
   */
  const activeOrders = computed((): Order[] => {
    return orders.value.filter((o: any) =>
      !['paid', 'cancelled'].includes(o.status)
    );
  });

  /**
   * Today's orders
   */
  const todayOrders = computed((): Order[] => {
    const today = new Date().toISOString().split('T')[0];
    return orders.value.filter((o: any) => o.created_at.startsWith(today));
  });

  /**
   * Paid orders
   */
  const paidOrders = computed((): Order[] => {
    return orders.value.filter((o: any) => o.status === 'paid');
  });

  /**
   * Orders with ready items
   */
  const ordersWithReadyItems = computed((): Order[] => {
    return activeOrders.value.filter((o: any) =>
      o.items.some((item: any) => item.status === 'ready')
    );
  });

  /**
   * Current order total
   */
  const currentOrderTotal = computed((): number => {
    return currentOrder.value?.total || 0;
  });

  /**
   * Current order items
   */
  const currentOrderItems = computed((): OrderItem[] => {
    return currentOrder.value?.items || [];
  });

  /**
   * New items count (not sent to kitchen)
   */
  const newItemsCount = computed((): number => {
    return currentOrderItems.value.filter((i: any) => i.status === 'new').length;
  });

  /**
   * Ready items count (ready to serve)
   */
  const readyItemsCount = computed((): number => {
    return currentOrderItems.value.filter((i: any) => i.status === 'ready').length;
  });

  /**
   * Has new items to send
   */
  const hasNewItems = computed((): boolean => {
    return newItemsCount.value > 0;
  });

  /**
   * Has ready items to serve
   */
  const hasReadyItems = computed((): boolean => {
    return readyItemsCount.value > 0;
  });

  /**
   * Today's statistics
   */
  const todayStats = computed(() => {
    const paid = todayOrders.value.filter((o: any) => o.status === 'paid');
    return {
      ordersCount: paid.length,
      totalSales: paid.reduce((sum: any, o: any) => sum + o.total, 0),
      avgCheck: paid.length > 0
        ? Math.round(paid.reduce((sum: any, o: any) => sum + o.total, 0) / paid.length)
        : 0,
    };
  });

  // === Actions ===

  /**
   * Fetch waiter's orders
   */
  async function fetchOrders(force = false): Promise<void> {
    // Check cache
    const now = Date.now();
    if (!force && lastFetchTime.value && (now - lastFetchTime.value) < CACHE_TTL) {
      return;
    }

    isLoading.value = true;
    error.value = null;

    try {
      const response = await ordersApi.getTodayOrders();
      if (response.success) {
        orders.value = response.data;
        lastFetchTime.value = now;
      }
    } catch (e: any) {
      error.value = e.message || 'Ошибка загрузки заказов';
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Fetch single order by ID
   */
  async function fetchOrder(orderId: number): Promise<Order | null> {
    isLoading.value = true;

    try {
      const response = await ordersApi.getOrder(orderId);
      if (response.success) {
        // Update in orders list
        const index = orders.value.findIndex((o: any) => o.id === orderId);
        if (index !== -1) {
          orders.value[index] = response.data;
        } else {
          orders.value.push(response.data);
        }
        return response.data;
      }
    } catch (e: any) {
      error.value = e.message;
    } finally {
      isLoading.value = false;
    }

    return null;
  }

  /**
   * Add item to order
   */
  async function addItem(tableId: number, data: AddOrderItemRequest): Promise<boolean> {
    isSaving.value = true;
    error.value = null;

    try {
      const response = await ordersApi.addItem(tableId, data);
      if (response.success) {
        currentOrder.value = response.data;
        updateOrderInList(response.data);

        // Update table status
        const tablesStore = useTablesStore();
        tablesStore.updateTable({
          ...tablesStore.getTableById(tableId)!,
          status: 'occupied',
          current_order: response.data,
          current_order_id: response.data.id,
        });

        return true;
      }
    } catch (e: any) {
      error.value = e.message || 'Ошибка добавления';
    } finally {
      isSaving.value = false;
    }

    return false;
  }

  /**
   * Update item quantity
   */
  async function updateItemQuantity(
    tableId: number,
    itemId: number,
    quantity: number
  ): Promise<boolean> {
    if (quantity <= 0) {
      return removeItem(tableId, itemId);
    }

    isSaving.value = true;

    try {
      const response = await ordersApi.updateItemQuantity(tableId, itemId, quantity);
      if (response.success) {
        currentOrder.value = response.data;
        updateOrderInList(response.data);
        return true;
      }
    } catch (e: any) {
      error.value = e.message || 'Ошибка обновления';
    } finally {
      isSaving.value = false;
    }

    return false;
  }

  /**
   * Remove item from order
   */
  async function removeItem(tableId: number, itemId: number): Promise<boolean> {
    isSaving.value = true;

    try {
      const response = await ordersApi.removeItem(tableId, itemId);
      if (response.success) {
        currentOrder.value = response.data;
        updateOrderInList(response.data);
        return true;
      }
    } catch (e: any) {
      error.value = e.message || 'Ошибка удаления';
    } finally {
      isSaving.value = false;
    }

    return false;
  }

  /**
   * Send order to kitchen
   */
  async function sendToKitchen(orderId: number): Promise<boolean> {
    isSaving.value = true;

    try {
      const response = await ordersApi.sendToKitchen(orderId);
      if (response.success) {
        currentOrder.value = response.data;
        updateOrderInList(response.data);
        return true;
      }
    } catch (e: any) {
      error.value = e.message || 'Ошибка отправки';
    } finally {
      isSaving.value = false;
    }

    return false;
  }

  /**
   * Send current order to kitchen
   */
  async function sendCurrentToKitchen(): Promise<boolean> {
    if (!currentOrder.value) return false;
    return sendToKitchen(currentOrder.value.id);
  }

  /**
   * Mark item as served
   */
  async function markItemServed(orderId: number, itemId: number): Promise<boolean> {
    try {
      const response = await ordersApi.markItemServed(orderId, itemId);
      if (response.success) {
        if (currentOrder.value?.id === orderId) {
          currentOrder.value = response.data;
        }
        updateOrderInList(response.data);
        return true;
      }
    } catch (e: any) {
      error.value = e.message;
    }
    return false;
  }

  /**
   * Mark all ready items as served
   */
  async function markAllServed(orderId: number): Promise<boolean> {
    try {
      const response = await ordersApi.markAllServed(orderId);
      if (response.success) {
        if (currentOrder.value?.id === orderId) {
          currentOrder.value = response.data;
        }
        updateOrderInList(response.data);
        return true;
      }
    } catch (e: any) {
      error.value = e.message;
    }
    return false;
  }

  /**
   * Pay order
   */
  async function payOrder(orderId: number, data: PayOrderRequest): Promise<boolean> {
    isSaving.value = true;

    try {
      const response = await ordersApi.payOrder(orderId, data);
      if (response.success) {
        // Remove from active orders
        orders.value = orders.value.filter((o: any) => o.id !== orderId);

        // Clear current order if it's the one being paid
        if (currentOrder.value?.id === orderId) {
          currentOrder.value = null;
        }

        // Update table status
        const tablesStore = useTablesStore();
        const table = tablesStore.tables.find((t: any) => t.current_order_id === orderId);
        if (table) {
          tablesStore.updateTable({
            ...table,
            status: 'free',
            current_order: undefined,
            current_order_id: undefined,
          });
        }

        return true;
      }
    } catch (e: any) {
      error.value = e.message || 'Ошибка оплаты';
    } finally {
      isSaving.value = false;
    }

    return false;
  }

  /**
   * Pay with cash
   */
  async function payWithCash(orderId: number): Promise<boolean> {
    return payOrder(orderId, { payment_method: 'cash' });
  }

  /**
   * Pay with card
   */
  async function payWithCard(orderId: number): Promise<boolean> {
    return payOrder(orderId, { payment_method: 'card' });
  }

  /**
   * Cancel order
   */
  async function cancelOrder(orderId: number, reason?: string): Promise<boolean> {
    isSaving.value = true;

    try {
      const response = await ordersApi.cancelOrder(orderId, { reason });
      if (response.success) {
        orders.value = orders.value.filter((o: any) => o.id !== orderId);
        if (currentOrder.value?.id === orderId) {
          currentOrder.value = null;
        }
        return true;
      }
    } catch (e: any) {
      error.value = e.message || 'Ошибка отмены';
    } finally {
      isSaving.value = false;
    }

    return false;
  }

  /**
   * Set current order
   */
  function setCurrentOrder(order: Order | null): void {
    currentOrder.value = order;
  }

  /**
   * Update order in list
   */
  function updateOrderInList(order: Order): void {
    const index = orders.value.findIndex((o: any) => o.id === order.id);
    if (index !== -1) {
      orders.value[index] = order;
    } else {
      orders.value.push(order);
    }
  }

  /**
   * Get order by ID
   */
  function getOrderById(orderId: number): Order | undefined {
    return orders.value.find((o: any) => o.id === orderId);
  }

  /**
   * Clear error
   */
  function clearError(): void {
    error.value = null;
  }

  /**
   * Reset store
   */
  function $reset(): void {
    orders.value = [];
    currentOrder.value = null;
    isLoading.value = false;
    isSaving.value = false;
    error.value = null;
    lastFetchTime.value = 0;
  }

  return {
    // State
    orders,
    currentOrder,
    isLoading,
    isSaving,
    error,

    // Getters
    activeOrders,
    todayOrders,
    paidOrders,
    ordersWithReadyItems,
    currentOrderTotal,
    currentOrderItems,
    newItemsCount,
    readyItemsCount,
    hasNewItems,
    hasReadyItems,
    todayStats,

    // Actions
    fetchOrders,
    fetchOrder,
    addItem,
    updateItemQuantity,
    removeItem,
    sendToKitchen,
    sendCurrentToKitchen,
    markItemServed,
    markAllServed,
    payOrder,
    payWithCash,
    payWithCard,
    cancelOrder,
    setCurrentOrder,
    updateOrderInList,
    getOrderById,
    clearError,
    $reset,
  };
});
