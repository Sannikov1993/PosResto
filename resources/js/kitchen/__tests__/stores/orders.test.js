/**
 * Orders Store Unit Tests
 *
 * @group unit
 * @group kitchen
 * @group stores
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useOrdersStore } from '../../stores/orders.js';

// Mock the API
vi.mock('../../services/api/orderApi.js', () => ({
    orderApi: {
        getOrders: vi.fn(),
        getOrderCountsByDate: vi.fn(),
        startCooking: vi.fn(),
        markReady: vi.fn(),
        returnToNew: vi.fn(),
        markItemReady: vi.fn(),
        callWaiter: vi.fn(),
    },
}));

import { orderApi } from '../../services/api/orderApi.js';

describe('Orders Store', () => {
    let store;

    const mockOrders = [
        {
            id: 1,
            order_number: 'A-001',
            status: 'confirmed',
            type: 'dine_in',
            created_at: '2024-01-15T12:00:00',
            items: [
                { id: 101, name: 'Pizza', status: 'cooking', cooking_started_at: null },
                { id: 102, name: 'Pasta', status: 'cooking', cooking_started_at: null },
            ],
        },
        {
            id: 2,
            order_number: 'A-002',
            status: 'cooking',
            type: 'delivery',
            created_at: '2024-01-15T11:30:00',
            cooking_started_at: '2024-01-15T11:35:00',
            items: [
                { id: 201, name: 'Burger', status: 'cooking', cooking_started_at: '2024-01-15T11:35:00' },
            ],
        },
        {
            id: 3,
            order_number: 'A-003',
            status: 'ready',
            type: 'pickup',
            items: [
                { id: 301, name: 'Salad', status: 'ready' },
            ],
        },
    ];

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useOrdersStore();
        vi.clearAllMocks();
    });

    // ==================== Initial State ====================

    describe('initial state', () => {
        it('should have empty orders', () => {
            expect(store.orders).toEqual([]);
        });

        it('should have today as selected date', () => {
            const today = new Date().toISOString().split('T')[0];
            expect(store.selectedDate).toBe(today);
        });

        it('should not be loading initially', () => {
            expect(store.isLoading).toBe(false);
        });
    });

    // ==================== Getters ====================

    describe('getters', () => {
        beforeEach(() => {
            store.orders = mockOrders;
        });

        it('newOrders should return orders with unstarted cooking items', () => {
            const newOrders = store.newOrders;

            expect(newOrders).toHaveLength(1);
            expect(newOrders[0].id).toBe(1);
            expect(newOrders[0].items).toHaveLength(2);
        });

        it('cookingOrders should return orders with started cooking items', () => {
            const cookingOrders = store.cookingOrders;

            expect(cookingOrders).toHaveLength(1);
            expect(cookingOrders[0].id).toBe(2);
        });

        it('readyOrders should return orders with ready items', () => {
            const readyOrders = store.readyOrders;

            expect(readyOrders).toHaveLength(1);
            expect(readyOrders[0].id).toBe(3);
        });

        it('totalNewOrders should count preorders + new orders', () => {
            expect(store.totalNewOrders).toBe(1);
        });
    });

    // ==================== Actions ====================

    describe('actions', () => {
        describe('fetchOrders', () => {
            it('should fetch and process orders', async () => {
                orderApi.getOrders.mockResolvedValue(mockOrders);

                await store.fetchOrders('device-123', 'hot-station');

                expect(orderApi.getOrders).toHaveBeenCalledWith({
                    deviceId: 'device-123',
                    date: store.selectedDate,
                    station: 'hot-station',
                });
                expect(store.orders).toHaveLength(3);
                expect(store.isLoading).toBe(false);
            });

            it('should set isLoading during fetch', async () => {
                orderApi.getOrders.mockImplementation(() =>
                    new Promise(resolve => setTimeout(() => resolve(mockOrders), 100))
                );

                const promise = store.fetchOrders('device-123');

                expect(store.isLoading).toBe(true);

                await promise;

                expect(store.isLoading).toBe(false);
            });

            it('should handle fetch error', async () => {
                const error = new Error('Network error');
                orderApi.getOrders.mockRejectedValue(error);

                await expect(store.fetchOrders('device-123')).rejects.toThrow('Network error');
                expect(store.error).toBe(error);
            });
        });

        describe('toggleItemDone', () => {
            it('should toggle item done state', () => {
                store.toggleItemDone(1, 101);
                expect(store.itemDoneState['1-101']).toBe(true);

                store.toggleItemDone(1, 101);
                expect(store.itemDoneState['1-101']).toBe(false);
            });
        });

        describe('setSelectedDate', () => {
            it('should update selected date', () => {
                store.setSelectedDate('2024-02-01');
                expect(store.selectedDate).toBe('2024-02-01');
            });
        });
    });

    // ==================== Seen Order IDs ====================

    describe('seen order detection', () => {
        it('should track seen order IDs', () => {
            orderApi.getOrders.mockResolvedValue([
                { id: 1, status: 'confirmed', items: [] },
            ]);

            store._processOrders([
                { id: 1, status: 'confirmed', items: [] },
            ]);

            expect(store.seenOrderIds.has(1)).toBe(true);
        });

        it('should detect new orders', () => {
            const result = store._processOrders([
                { id: 1, status: 'confirmed', items: [] },
            ]);

            expect(result.newOrders).toHaveLength(1);
            expect(result.newOrders[0].id).toBe(1);
        });

        it('should not detect already seen orders as new', () => {
            store.seenOrderIds.add(1);

            const result = store._processOrders([
                { id: 1, status: 'confirmed', items: [] },
            ]);

            expect(result.newOrders).toHaveLength(0);
        });
    });
});
