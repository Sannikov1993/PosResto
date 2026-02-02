/**
 * Waiter App - useOrders Composable
 * Orders and order items logic
 */

import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useOrdersStore } from '@/waiter/stores/orders';
import { useTablesStore } from '@/waiter/stores/tables';
import { useMenuStore } from '@/waiter/stores/menu';
import { useUiStore } from '@/waiter/stores/ui';
import type { Dish, PaymentMethod, OrderItem } from '@/waiter/types';

export function useOrders() {
  const ordersStore = useOrdersStore();
  const tablesStore = useTablesStore();
  const menuStore = useMenuStore();
  const uiStore = useUiStore();

  const {
    orders,
    currentOrder,
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
    isLoading,
    isSaving,
    error,
  } = storeToRefs(ordersStore);

  const { selectedTable } = storeToRefs(tablesStore);

  // === Computed ===

  /**
   * Can send order to kitchen
   */
  const canSendToKitchen = computed((): boolean => {
    return !!currentOrder.value && hasNewItems.value && !isSaving.value;
  });

  /**
   * Can pay order
   */
  const canPay = computed((): boolean => {
    return !!currentOrder.value &&
           currentOrder.value.items.length > 0 &&
           !hasNewItems.value;
  });

  /**
   * New items (not sent to kitchen)
   */
  const newItems = computed((): OrderItem[] => {
    return currentOrderItems.value.filter(i => i.status === 'new');
  });

  /**
   * Sent items (in kitchen or ready)
   */
  const sentItems = computed((): OrderItem[] => {
    return currentOrderItems.value.filter(i => i.status !== 'new');
  });

  /**
   * Ready items
   */
  const readyItems = computed((): OrderItem[] => {
    return currentOrderItems.value.filter(i => i.status === 'ready');
  });

  // === Methods ===

  /**
   * Fetch orders
   */
  async function fetchOrders(force = false): Promise<void> {
    await ordersStore.fetchOrders(force);
  }

  /**
   * Add dish to current order
   */
  async function addDish(dish: Dish, quantity = 1, comment?: string): Promise<boolean> {
    if (!selectedTable.value) {
      uiStore.showWarning('Выберите стол');
      return false;
    }

    if (dish.in_stop_list) {
      uiStore.showWarning(`${dish.name} в стоп-листе`);
      return false;
    }

    if (!dish.is_available) {
      uiStore.showWarning(`${dish.name} недоступно`);
      return false;
    }

    const success = await ordersStore.addItem(selectedTable.value.id, {
      dish_id: dish.id,
      quantity,
      comment,
    });

    if (success) {
      uiStore.showSuccess(`${dish.name} добавлено`);
    } else {
      uiStore.showError(error.value || 'Ошибка добавления');
    }

    return success;
  }

  /**
   * Update item quantity
   */
  async function updateQuantity(itemId: number, quantity: number): Promise<boolean> {
    if (!selectedTable.value) return false;

    if (quantity <= 0) {
      return removeItem(itemId);
    }

    const success = await ordersStore.updateItemQuantity(
      selectedTable.value.id,
      itemId,
      quantity
    );

    if (!success) {
      uiStore.showError(error.value || 'Ошибка обновления');
    }

    return success;
  }

  /**
   * Increment item quantity
   */
  async function incrementQuantity(itemId: number): Promise<boolean> {
    const item = currentOrderItems.value.find(i => i.id === itemId);
    if (!item) return false;
    return updateQuantity(itemId, item.quantity + 1);
  }

  /**
   * Decrement item quantity
   */
  async function decrementQuantity(itemId: number): Promise<boolean> {
    const item = currentOrderItems.value.find(i => i.id === itemId);
    if (!item) return false;
    return updateQuantity(itemId, item.quantity - 1);
  }

  /**
   * Remove item from order
   */
  async function removeItem(itemId: number): Promise<boolean> {
    if (!selectedTable.value) return false;

    const success = await ordersStore.removeItem(selectedTable.value.id, itemId);

    if (success) {
      uiStore.showSuccess('Позиция удалена');
    } else {
      uiStore.showError(error.value || 'Ошибка удаления');
    }

    return success;
  }

  /**
   * Send order to kitchen
   */
  async function sendToKitchen(): Promise<boolean> {
    if (!currentOrder.value) {
      uiStore.showWarning('Нет заказа');
      return false;
    }

    if (newItemsCount.value === 0) {
      uiStore.showWarning('Нет новых позиций');
      return false;
    }

    const success = await ordersStore.sendToKitchen(currentOrder.value.id);

    if (success) {
      uiStore.showSuccess('Отправлено на кухню');
    } else {
      uiStore.showError(error.value || 'Ошибка отправки');
    }

    return success;
  }

  /**
   * Mark item as served
   */
  async function markItemServed(itemId: number): Promise<boolean> {
    if (!currentOrder.value) return false;

    const success = await ordersStore.markItemServed(currentOrder.value.id, itemId);

    if (!success) {
      uiStore.showError(error.value || 'Ошибка');
    }

    return success;
  }

  /**
   * Mark all ready items as served
   */
  async function markAllServed(): Promise<boolean> {
    if (!currentOrder.value) return false;

    const success = await ordersStore.markAllServed(currentOrder.value.id);

    if (success) {
      uiStore.showSuccess('Все блюда поданы');
    } else {
      uiStore.showError(error.value || 'Ошибка');
    }

    return success;
  }

  /**
   * Pay order
   */
  async function pay(method: PaymentMethod): Promise<boolean> {
    if (!currentOrder.value) return false;

    const success = await ordersStore.payOrder(currentOrder.value.id, {
      payment_method: method,
    });

    if (success) {
      uiStore.showSuccess('Заказ оплачен');
      uiStore.closePaymentModal();
      uiStore.goToTables();
    } else {
      uiStore.showError(error.value || 'Ошибка оплаты');
    }

    return success;
  }

  /**
   * Pay with cash
   */
  async function payWithCash(): Promise<boolean> {
    return pay('cash');
  }

  /**
   * Pay with card
   */
  async function payWithCard(): Promise<boolean> {
    return pay('card');
  }

  /**
   * Cancel order
   */
  async function cancelOrder(reason?: string): Promise<boolean> {
    if (!currentOrder.value) return false;

    const success = await ordersStore.cancelOrder(currentOrder.value.id, reason);

    if (success) {
      uiStore.showSuccess('Заказ отменён');
      uiStore.goToTables();
    } else {
      uiStore.showError(error.value || 'Ошибка отмены');
    }

    return success;
  }

  /**
   * Open payment modal
   */
  function openPayment(): void {
    if (!canPay.value) {
      if (hasNewItems.value) {
        uiStore.showWarning('Сначала отправьте заказ на кухню');
      } else {
        uiStore.showWarning('Добавьте позиции в заказ');
      }
      return;
    }
    uiStore.openPaymentModal();
  }

  /**
   * Get order by ID
   */
  function getOrderById(orderId: number) {
    return ordersStore.getOrderById(orderId);
  }

  /**
   * Clear error
   */
  function clearError(): void {
    ordersStore.clearError();
  }

  return {
    // State
    orders,
    currentOrder,
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
    isLoading,
    isSaving,
    error,

    // Computed
    canSendToKitchen,
    canPay,
    newItems,
    sentItems,
    readyItems,

    // Methods
    fetchOrders,
    addDish,
    updateQuantity,
    incrementQuantity,
    decrementQuantity,
    removeItem,
    sendToKitchen,
    markItemServed,
    markAllServed,
    pay,
    payWithCash,
    payWithCard,
    cancelOrder,
    openPayment,
    getOrderById,
    clearError,
  };
}
