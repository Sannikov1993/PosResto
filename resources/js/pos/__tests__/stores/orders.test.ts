/**
 * POS Orders Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock POS API
const { mockApi } = vi.hoisted(() => ({
    mockApi: {
        orders: {
            getActive: vi.fn(),
            getPaidToday: vi.fn(),
        },
    },
}));

vi.mock('@/pos/api/index.js', () => ({
    default: mockApi,
}));

import { useOrdersStore } from '@/pos/stores/orders.js';

describe('POS Orders Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('Initial State', () => {
        it('should have empty orders arrays', () => {
            const store = useOrdersStore();

            expect(store.orders).toEqual([]);
            expect(store.activeOrders).toEqual([]);
            expect(store.paidOrders).toEqual([]);
        });

        it('should have empty activeOrdersMap', () => {
            const store = useOrdersStore();
            expect(store.activeOrdersMap.size).toBe(0);
        });
    });

    describe('loadActiveOrders', () => {
        it('should fetch and set active orders', async () => {
            const mockOrders = [
                { id: 1, table_id: 5, status: 'new', total: 1500 },
                { id: 2, table_id: 8, status: 'cooking', total: 2500 },
            ];

            mockApi.orders.getActive.mockResolvedValue(mockOrders);

            const store = useOrdersStore();
            await store.loadActiveOrders(true);

            expect(store.activeOrders).toEqual(mockOrders);
            expect(mockApi.orders.getActive).toHaveBeenCalledOnce();
        });

        it('should use cache on subsequent calls', async () => {
            mockApi.orders.getActive.mockResolvedValue([]);

            const store = useOrdersStore();
            await store.loadActiveOrders(true);
            await store.loadActiveOrders(); // should use cache

            expect(mockApi.orders.getActive).toHaveBeenCalledOnce();
        });

        it('should skip cache when forced', async () => {
            mockApi.orders.getActive.mockResolvedValue([]);

            const store = useOrdersStore();
            await store.loadActiveOrders(true);
            await store.loadActiveOrders(true);

            expect(mockApi.orders.getActive).toHaveBeenCalledTimes(2);
        });
    });

    describe('loadPaidOrders', () => {
        it('should fetch and set paid orders', async () => {
            const mockOrders = [
                { id: 3, status: 'completed', payment_status: 'paid', total: 3000 },
            ];

            mockApi.orders.getPaidToday.mockResolvedValue(mockOrders);

            const store = useOrdersStore();
            await store.loadPaidOrders(true);

            expect(store.paidOrders).toEqual(mockOrders);
        });
    });

    describe('activeOrdersMap', () => {
        it('should build map from active orders by table_id', async () => {
            const mockOrders = [
                { id: 1, table_id: 5, status: 'new', total: 1500 },
                { id: 2, table_id: 8, status: 'cooking', total: 2500 },
                { id: 3, table_id: null, status: 'new', total: 800 }, // delivery, no table
            ];

            mockApi.orders.getActive.mockResolvedValue(mockOrders);

            const store = useOrdersStore();
            await store.loadActiveOrders(true);

            expect(store.activeOrdersMap.size).toBe(2);
            expect(store.activeOrdersMap.get(5)?.id).toBe(1);
            expect(store.activeOrdersMap.get(8)?.id).toBe(2);
        });
    });

    describe('getTableOrder', () => {
        it('should return order for given table', async () => {
            mockApi.orders.getActive.mockResolvedValue([
                { id: 1, table_id: 5, status: 'new', total: 1500 },
            ]);

            const store = useOrdersStore();
            await store.loadActiveOrders(true);

            const order = store.getTableOrder(5);
            expect(order).not.toBeNull();
            expect(order!.id).toBe(1);
        });

        it('should return null for table without order', async () => {
            mockApi.orders.getActive.mockResolvedValue([]);

            const store = useOrdersStore();
            await store.loadActiveOrders(true);

            expect(store.getTableOrder(999)).toBeNull();
        });
    });
});
