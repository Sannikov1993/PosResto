/**
 * POS Delivery Store Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Mock POS API
const { mockApi } = vi.hoisted(() => ({
    mockApi: {
        orders: {
            getDelivery: vi.fn(),
        },
        couriers: {
            getAll: vi.fn(),
        },
    },
}));

vi.mock('@/pos/api/index.js', () => ({
    default: mockApi,
}));

import { useDeliveryStore } from '@/pos/stores/delivery.js';

describe('POS Delivery Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('Initial State', () => {
        it('should have empty deliveryOrders', () => {
            const store = useDeliveryStore();
            expect(store.deliveryOrders).toEqual([]);
        });

        it('should have empty couriers', () => {
            const store = useDeliveryStore();
            expect(store.couriers).toEqual([]);
        });

        it('should have zero pendingDeliveryCount', () => {
            const store = useDeliveryStore();
            expect(store.pendingDeliveryCount).toBe(0);
        });
    });

    describe('pendingDeliveryCount', () => {
        it('should count only orders with delivery_status pending', async () => {
            const mockOrders = [
                { id: 1, delivery_status: 'pending', total: 1000 },
                { id: 2, delivery_status: 'pending', total: 2000 },
                { id: 3, delivery_status: 'delivered', total: 1500 },
                { id: 4, delivery_status: 'in_transit', total: 3000 },
            ];

            mockApi.orders.getDelivery.mockResolvedValue(mockOrders);

            const store = useDeliveryStore();
            await store.loadDeliveryOrders(true);

            expect(store.pendingDeliveryCount).toBe(2);
        });

        it('should return zero when no orders are pending', async () => {
            const mockOrders = [
                { id: 1, delivery_status: 'delivered', total: 1000 },
                { id: 2, delivery_status: 'in_transit', total: 2000 },
            ];

            mockApi.orders.getDelivery.mockResolvedValue(mockOrders);

            const store = useDeliveryStore();
            await store.loadDeliveryOrders(true);

            expect(store.pendingDeliveryCount).toBe(0);
        });

        it('should return zero when deliveryOrders is empty', () => {
            const store = useDeliveryStore();
            expect(store.pendingDeliveryCount).toBe(0);
        });
    });

    describe('loadDeliveryOrders', () => {
        it('should fetch and set delivery orders', async () => {
            const mockOrders = [
                { id: 1, delivery_status: 'pending', total: 1000 },
                { id: 2, delivery_status: 'in_transit', total: 2500 },
            ];

            mockApi.orders.getDelivery.mockResolvedValue(mockOrders);

            const store = useDeliveryStore();
            await store.loadDeliveryOrders(true);

            expect(store.deliveryOrders).toEqual(mockOrders);
            expect(mockApi.orders.getDelivery).toHaveBeenCalledOnce();
        });

        it('should use cache on subsequent calls', async () => {
            mockApi.orders.getDelivery.mockResolvedValue([]);

            const store = useDeliveryStore();
            await store.loadDeliveryOrders(true);
            await store.loadDeliveryOrders(); // should use cache

            expect(mockApi.orders.getDelivery).toHaveBeenCalledOnce();
        });

        it('should skip cache when forced', async () => {
            mockApi.orders.getDelivery.mockResolvedValue([]);

            const store = useDeliveryStore();
            await store.loadDeliveryOrders(true);
            await store.loadDeliveryOrders(true);

            expect(mockApi.orders.getDelivery).toHaveBeenCalledTimes(2);
        });

        it('should update pendingDeliveryCount after load', async () => {
            mockApi.orders.getDelivery.mockResolvedValue([
                { id: 1, delivery_status: 'pending' },
            ]);

            const store = useDeliveryStore();
            await store.loadDeliveryOrders(true);
            expect(store.pendingDeliveryCount).toBe(1);

            mockApi.orders.getDelivery.mockResolvedValue([
                { id: 1, delivery_status: 'pending' },
                { id: 2, delivery_status: 'pending' },
                { id: 3, delivery_status: 'delivered' },
            ]);

            await store.loadDeliveryOrders(true);
            expect(store.pendingDeliveryCount).toBe(2);
        });
    });

    describe('loadCouriers', () => {
        it('should fetch and set couriers', async () => {
            const mockCouriers = [
                { id: 1, name: 'John Doe', role: 'courier' },
                { id: 2, name: 'Jane Smith', role: 'courier' },
            ];

            mockApi.couriers.getAll.mockResolvedValue(mockCouriers);

            const store = useDeliveryStore();
            await store.loadCouriers();

            expect(store.couriers).toEqual(mockCouriers);
            expect(mockApi.couriers.getAll).toHaveBeenCalledOnce();
        });

        it('should replace previous couriers on reload', async () => {
            mockApi.couriers.getAll.mockResolvedValue([
                { id: 1, name: 'John Doe' },
            ]);

            const store = useDeliveryStore();
            await store.loadCouriers();
            expect(store.couriers).toHaveLength(1);

            mockApi.couriers.getAll.mockResolvedValue([
                { id: 1, name: 'John Doe' },
                { id: 2, name: 'Jane Smith' },
            ]);

            await store.loadCouriers();
            expect(store.couriers).toHaveLength(2);
        });
    });
});
