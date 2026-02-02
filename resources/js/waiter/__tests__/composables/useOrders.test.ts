/**
 * useOrders Composable Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock stores
vi.mock('@/waiter/stores/orders', () => ({
  useOrdersStore: vi.fn(() => ({
    orders: [],
    currentOrder: null,
    activeOrders: [],
    todayOrders: [],
    paidOrders: [],
    ordersWithReadyItems: [],
    currentOrderTotal: 0,
    currentOrderItems: [],
    newItemsCount: 0,
    readyItemsCount: 0,
    hasNewItems: false,
    hasReadyItems: false,
    todayStats: { ordersCount: 0, totalSales: 0, avgCheck: 0 },
    isLoading: false,
    isSaving: false,
    error: null,
    fetchOrders: vi.fn(),
    addItem: vi.fn(),
    updateItemQuantity: vi.fn(),
    removeItem: vi.fn(),
    sendToKitchen: vi.fn(),
    markItemServed: vi.fn(),
    markAllServed: vi.fn(),
    payOrder: vi.fn(),
    cancelOrder: vi.fn(),
    getOrderById: vi.fn(),
    clearError: vi.fn(),
  })),
}));

vi.mock('@/waiter/stores/tables', () => ({
  useTablesStore: vi.fn(() => ({
    selectedTable: { id: 1, number: '1' },
  })),
}));

vi.mock('@/waiter/stores/menu', () => ({
  useMenuStore: vi.fn(() => ({})),
}));

vi.mock('@/waiter/stores/ui', () => ({
  useUiStore: vi.fn(() => ({
    showSuccess: vi.fn(),
    showError: vi.fn(),
    showWarning: vi.fn(),
    openPaymentModal: vi.fn(),
    closePaymentModal: vi.fn(),
    goToTables: vi.fn(),
  })),
}));

import { useOrders } from '@/waiter/composables/useOrders';
import { useOrdersStore } from '@/waiter/stores/orders';
import { useUiStore } from '@/waiter/stores/ui';

describe('useOrders Composable', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  describe('addDish', () => {
    it('should add dish successfully', async () => {
      const ordersStore = useOrdersStore();
      const uiStore = useUiStore();

      vi.mocked(ordersStore.addItem).mockResolvedValue(true);

      const { addDish } = useOrders();

      const dish = { id: 1, name: 'Pizza', price: 500, is_available: true, in_stop_list: false } as any;
      const result = await addDish(dish);

      expect(result).toBe(true);
      expect(ordersStore.addItem).toHaveBeenCalledWith(1, {
        dish_id: 1,
        quantity: 1,
        comment: undefined,
      });
      expect(uiStore.showSuccess).toHaveBeenCalledWith('Pizza добавлено');
    });

    it('should show warning for dish in stop list', async () => {
      const uiStore = useUiStore();

      const { addDish } = useOrders();

      const dish = { id: 1, name: 'Pizza', in_stop_list: true } as any;
      const result = await addDish(dish);

      expect(result).toBe(false);
      expect(uiStore.showWarning).toHaveBeenCalledWith('Pizza в стоп-листе');
    });

    it('should show warning for unavailable dish', async () => {
      const uiStore = useUiStore();

      const { addDish } = useOrders();

      const dish = { id: 1, name: 'Pizza', is_available: false, in_stop_list: false } as any;
      const result = await addDish(dish);

      expect(result).toBe(false);
      expect(uiStore.showWarning).toHaveBeenCalledWith('Pizza недоступно');
    });
  });

  describe('sendToKitchen', () => {
    it('should send order to kitchen successfully', async () => {
      const ordersStore = useOrdersStore();
      const uiStore = useUiStore();

      // Mock having a current order with new items
      Object.defineProperty(ordersStore, 'currentOrder', { value: { id: 1 }, writable: true });
      Object.defineProperty(ordersStore, 'newItemsCount', { value: 2, writable: true });

      vi.mocked(ordersStore.sendToKitchen).mockResolvedValue(true);

      const { sendToKitchen } = useOrders();
      const result = await sendToKitchen();

      expect(result).toBe(true);
      expect(ordersStore.sendToKitchen).toHaveBeenCalledWith(1);
      expect(uiStore.showSuccess).toHaveBeenCalledWith('Отправлено на кухню');
    });

    it('should show warning if no order', async () => {
      const ordersStore = useOrdersStore();
      const uiStore = useUiStore();

      Object.defineProperty(ordersStore, 'currentOrder', { value: null, writable: true });

      const { sendToKitchen } = useOrders();
      const result = await sendToKitchen();

      expect(result).toBe(false);
      expect(uiStore.showWarning).toHaveBeenCalledWith('Нет заказа');
    });
  });

  describe('pay', () => {
    it('should process payment successfully', async () => {
      const ordersStore = useOrdersStore();
      const uiStore = useUiStore();

      Object.defineProperty(ordersStore, 'currentOrder', { value: { id: 1, total: 1000 }, writable: true });
      vi.mocked(ordersStore.payOrder).mockResolvedValue(true);

      const { pay } = useOrders();
      const result = await pay('cash');

      expect(result).toBe(true);
      expect(ordersStore.payOrder).toHaveBeenCalledWith(1, { payment_method: 'cash' });
      expect(uiStore.showSuccess).toHaveBeenCalledWith('Заказ оплачен');
      expect(uiStore.closePaymentModal).toHaveBeenCalled();
      expect(uiStore.goToTables).toHaveBeenCalled();
    });

    it('should show error on payment failure', async () => {
      const ordersStore = useOrdersStore();
      const uiStore = useUiStore();

      Object.defineProperty(ordersStore, 'currentOrder', { value: { id: 1 }, writable: true });
      Object.defineProperty(ordersStore, 'error', { value: 'Payment failed', writable: true });
      vi.mocked(ordersStore.payOrder).mockResolvedValue(false);

      const { pay } = useOrders();
      const result = await pay('card');

      expect(result).toBe(false);
      expect(uiStore.showError).toHaveBeenCalledWith('Payment failed');
    });
  });

  describe('computed properties', () => {
    it('canSendToKitchen should check conditions', () => {
      const ordersStore = useOrdersStore();

      Object.defineProperty(ordersStore, 'currentOrder', { value: { id: 1 }, writable: true });
      Object.defineProperty(ordersStore, 'hasNewItems', { value: true, writable: true });
      Object.defineProperty(ordersStore, 'isSaving', { value: false, writable: true });

      const { canSendToKitchen } = useOrders();

      expect(canSendToKitchen.value).toBe(true);
    });

    it('canPay should check conditions', () => {
      const ordersStore = useOrdersStore();

      Object.defineProperty(ordersStore, 'currentOrder', { value: { id: 1, items: [{ id: 1 }] }, writable: true });
      Object.defineProperty(ordersStore, 'hasNewItems', { value: false, writable: true });

      const { canPay } = useOrders();

      expect(canPay.value).toBe(true);
    });
  });
});
