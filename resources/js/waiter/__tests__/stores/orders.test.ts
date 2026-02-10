/**
 * Orders Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useOrdersStore } from '@/waiter/stores/orders';

// Mock the orders API
vi.mock('@/waiter/services', () => ({
  ordersApi: {
    getTodayOrders: vi.fn(),
    getOrder: vi.fn(),
    addItem: vi.fn(),
    updateItemQuantity: vi.fn(),
    removeItem: vi.fn(),
    sendToKitchen: vi.fn(),
    payOrder: vi.fn(),
    cancelOrder: vi.fn(),
    markItemServed: vi.fn(),
    markAllServed: vi.fn(),
  },
}));

// Mock tables store
vi.mock('@/waiter/stores/tables', () => ({
  useTablesStore: vi.fn(() => ({
    tables: [],
    getTableById: vi.fn(),
    updateTable: vi.fn(),
  })),
}));

import { ordersApi } from '@/waiter/services';

describe('Orders Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const store = useOrdersStore();

      expect(store.orders).toEqual([]);
      expect(store.currentOrder).toBeNull();
      expect(store.isLoading).toBe(false);
      expect(store.isSaving).toBe(false);
      expect(store.error).toBeNull();
    });
  });

  describe('fetchOrders', () => {
    it('should fetch today orders', async () => {
      const mockOrders = [
        { id: 1, status: 'new', total: 1000, items: [], created_at: new Date().toISOString() },
        { id: 2, status: 'cooking', total: 2000, items: [], created_at: new Date().toISOString() },
      ];

      vi.mocked(ordersApi.getTodayOrders).mockResolvedValue({
        success: true,
        data: mockOrders,
      } as any);

      const store = useOrdersStore();
      await store.fetchOrders();

      expect(store.orders).toEqual(mockOrders);
      expect(store.isLoading).toBe(false);
    });

    it('should handle fetch error', async () => {
      vi.mocked(ordersApi.getTodayOrders).mockRejectedValue(new Error('Network error'));

      const store = useOrdersStore();
      await store.fetchOrders();

      expect(store.error).toBe('Network error');
      expect(store.orders).toEqual([]);
    });
  });

  describe('Getters', () => {
    it('activeOrders should filter out paid and cancelled', () => {
      const store = useOrdersStore();
      store.orders = [
        { id: 1, status: 'new' },
        { id: 2, status: 'cooking' },
        { id: 3, status: 'paid' },
        { id: 4, status: 'cancelled' },
        { id: 5, status: 'ready' },
      ] as any;

      expect(store.activeOrders).toHaveLength(3);
      expect(store.activeOrders.map(o => o.id)).toEqual([1, 2, 5]);
    });

    it('todayOrders should filter by today date', () => {
      const today = new Date().toISOString().split('T')[0];
      const yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0];

      const store = useOrdersStore();
      store.orders = [
        { id: 1, created_at: `${today}T10:00:00` },
        { id: 2, created_at: `${today}T12:00:00` },
        { id: 3, created_at: `${yesterday}T10:00:00` },
      ] as any;

      expect(store.todayOrders).toHaveLength(2);
    });

    it('paidOrders should filter by paid status', () => {
      const store = useOrdersStore();
      store.orders = [
        { id: 1, status: 'paid' },
        { id: 2, status: 'new' },
        { id: 3, status: 'paid' },
      ] as any;

      expect(store.paidOrders).toHaveLength(2);
    });

    it('currentOrderTotal should return order total', () => {
      const store = useOrdersStore();
      store.currentOrder = { id: 1, total: 1500 } as any;

      expect(store.currentOrderTotal).toBe(1500);
    });

    it('currentOrderTotal should return 0 if no order', () => {
      const store = useOrdersStore();
      expect(store.currentOrderTotal).toBe(0);
    });

    it('currentOrderItems should return items array', () => {
      const store = useOrdersStore();
      store.currentOrder = {
        id: 1,
        items: [
          { id: 1, name: 'Pizza' },
          { id: 2, name: 'Pasta' },
        ],
      } as any;

      expect(store.currentOrderItems).toHaveLength(2);
    });

    it('newItemsCount should count new status items', () => {
      const store = useOrdersStore();
      store.currentOrder = {
        id: 1,
        items: [
          { id: 1, status: 'new' },
          { id: 2, status: 'new' },
          { id: 3, status: 'cooking' },
        ],
      } as any;

      expect(store.newItemsCount).toBe(2);
    });

    it('readyItemsCount should count ready status items', () => {
      const store = useOrdersStore();
      store.currentOrder = {
        id: 1,
        items: [
          { id: 1, status: 'ready' },
          { id: 2, status: 'cooking' },
          { id: 3, status: 'ready' },
        ],
      } as any;

      expect(store.readyItemsCount).toBe(2);
    });

    it('hasNewItems should be true when new items exist', () => {
      const store = useOrdersStore();
      store.currentOrder = {
        id: 1,
        items: [{ id: 1, status: 'new' }],
      } as any;

      expect(store.hasNewItems).toBe(true);
    });

    it('todayStats should calculate correctly', () => {
      const today = new Date().toISOString().split('T')[0];

      const store = useOrdersStore();
      store.orders = [
        { id: 1, status: 'paid', total: 1000, created_at: `${today}T10:00:00` },
        { id: 2, status: 'paid', total: 2000, created_at: `${today}T12:00:00` },
        { id: 3, status: 'new', total: 500, created_at: `${today}T14:00:00` },
      ] as any;

      expect(store.todayStats.ordersCount).toBe(2); // Only paid
      expect(store.todayStats.totalSales).toBe(3000);
      expect(store.todayStats.avgCheck).toBe(1500);
    });
  });

  describe('Actions', () => {
    it('addItem should add item to order', async () => {
      const mockOrder = {
        id: 1,
        items: [{ id: 1, dish_id: 10, quantity: 1 }],
        total: 500,
      };

      vi.mocked(ordersApi.addItem).mockResolvedValue({
        success: true,
        data: mockOrder,
      } as any);

      const store = useOrdersStore();
      const result = await store.addItem(1, { dish_id: 10, quantity: 1 });

      expect(result).toBe(true);
      expect(store.currentOrder).toEqual(mockOrder);
    });

    it('addItem should handle error', async () => {
      vi.mocked(ordersApi.addItem).mockResolvedValue({
        success: false,
        message: 'Dish not available',
      } as any);

      const store = useOrdersStore();
      const result = await store.addItem(1, { dish_id: 10, quantity: 1 });

      expect(result).toBe(false);
    });

    it('sendToKitchen should send order', async () => {
      const mockOrder = { id: 1, status: 'cooking', items: [] };

      vi.mocked(ordersApi.sendToKitchen).mockResolvedValue({
        success: true,
        data: mockOrder,
      } as any);

      const store = useOrdersStore();
      store.currentOrder = { id: 1, status: 'new', items: [] } as any;

      const result = await store.sendToKitchen(1);

      expect(result).toBe(true);
      expect(store.currentOrder?.status).toBe('cooking');
    });

    it('payOrder should process payment and clear order', async () => {
      vi.mocked(ordersApi.payOrder).mockResolvedValue({
        success: true,
        data: { id: 1, status: 'paid' },
      } as any);

      const store = useOrdersStore();
      store.orders = [{ id: 1, status: 'ready' }] as any;
      store.currentOrder = { id: 1, status: 'ready' } as any;

      const result = await store.payOrder(1, { payment_method: 'cash' });

      expect(result).toBe(true);
      expect(store.currentOrder).toBeNull();
      expect(store.orders.find(o => o.id === 1)).toBeUndefined();
    });

    it('setCurrentOrder should update current order', () => {
      const store = useOrdersStore();
      const order = { id: 1, total: 1000 } as any;

      store.setCurrentOrder(order);

      expect(store.currentOrder).toEqual(order);
    });

    it('updateOrderInList should update or add order', () => {
      const store = useOrdersStore();
      store.orders = [
        { id: 1, total: 1000 },
        { id: 2, total: 2000 },
      ] as any;

      // Update existing
      store.updateOrderInList({ id: 1, total: 1500 } as any);
      expect(store.orders[0].total).toBe(1500);

      // Add new
      store.updateOrderInList({ id: 3, total: 3000 } as any);
      expect(store.orders).toHaveLength(3);
    });

    it('getOrderById should return order', () => {
      const store = useOrdersStore();
      store.orders = [
        { id: 1, total: 1000 },
        { id: 2, total: 2000 },
      ] as any;

      expect(store.getOrderById(2)?.total).toBe(2000);
      expect(store.getOrderById(999)).toBeUndefined();
    });
  });

  describe('$reset', () => {
    it('should reset store to initial state', () => {
      const store = useOrdersStore();

      store.orders = [{ id: 1 }] as any;
      store.currentOrder = { id: 1 } as any;
      store.error = 'Error';

      store.$reset();

      expect(store.orders).toEqual([]);
      expect(store.currentOrder).toBeNull();
      expect(store.error).toBeNull();
    });
  });
});
