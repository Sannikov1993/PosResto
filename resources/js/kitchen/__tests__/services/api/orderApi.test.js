/**
 * Order API Service Unit Tests
 *
 * @group unit
 * @group kitchen
 * @group api
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { orderApi } from '../../../services/api/orderApi.js';
import { kitchenApi } from '../../../services/api/kitchenApi.js';
import { API_ENDPOINTS } from '../../../constants/api.js';

// Mock kitchenApi
vi.mock('../../../services/api/kitchenApi.js', () => ({
    kitchenApi: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
    },
}));

describe('OrderApiService', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // ==================== getOrders ====================

    describe('getOrders()', () => {
        it('should fetch orders with required params', async () => {
            const mockOrders = [
                { id: 1, order_number: 101, status: 'confirmed', items: [] },
                { id: 2, order_number: 102, status: 'cooking', items: [] },
            ];

            kitchenApi.get.mockResolvedValue({
                success: true,
                data: mockOrders,
            });

            const result = await orderApi.getOrders({
                deviceId: 'device-123',
            });

            expect(kitchenApi.get).toHaveBeenCalledWith(
                API_ENDPOINTS.ORDERS,
                { device_id: 'device-123' },
                expect.any(Object)
            );
            expect(result).toEqual(mockOrders);
        });

        it('should include optional params', async () => {
            kitchenApi.get.mockResolvedValue({
                success: true,
                data: [],
            });

            await orderApi.getOrders({
                deviceId: 'device-123',
                date: '2024-01-15',
                station: 'hot',
            });

            expect(kitchenApi.get).toHaveBeenCalledWith(
                API_ENDPOINTS.ORDERS,
                {
                    device_id: 'device-123',
                    date: '2024-01-15',
                    station: 'hot',
                },
                expect.any(Object)
            );
        });

        it('should throw error on failure', async () => {
            kitchenApi.get.mockResolvedValue({
                success: false,
                message: 'Server error',
            });

            await expect(
                orderApi.getOrders({ deviceId: 'device-123' })
            ).rejects.toThrow('Server error');
        });

        it('should return empty array when data is null', async () => {
            kitchenApi.get.mockResolvedValue({
                success: true,
                data: null,
            });

            const result = await orderApi.getOrders({ deviceId: 'device-123' });
            expect(result).toEqual([]);
        });
    });

    // ==================== getOrderCountsByDate ====================

    describe('getOrderCountsByDate()', () => {
        it('should fetch order counts', async () => {
            const mockCounts = {
                '2024-01-15': 5,
                '2024-01-16': 3,
            };

            kitchenApi.get.mockResolvedValue({
                success: true,
                data: mockCounts,
            });

            const result = await orderApi.getOrderCountsByDate({
                deviceId: 'device-123',
                startDate: '2024-01-01',
                endDate: '2024-01-31',
            });

            expect(kitchenApi.get).toHaveBeenCalledWith(
                API_ENDPOINTS.ORDER_COUNTS,
                {
                    device_id: 'device-123',
                    start_date: '2024-01-01',
                    end_date: '2024-01-31',
                }
            );
            expect(result).toEqual(mockCounts);
        });

        it('should include station filter', async () => {
            kitchenApi.get.mockResolvedValue({
                success: true,
                data: {},
            });

            await orderApi.getOrderCountsByDate({
                deviceId: 'device-123',
                startDate: '2024-01-01',
                endDate: '2024-01-31',
                station: 'cold',
            });

            expect(kitchenApi.get).toHaveBeenCalledWith(
                API_ENDPOINTS.ORDER_COUNTS,
                expect.objectContaining({ station: 'cold' })
            );
        });
    });

    // ==================== updateOrderStatus ====================

    describe('updateOrderStatus()', () => {
        it('should update order status', async () => {
            kitchenApi.patch.mockResolvedValue({
                success: true,
                data: { id: 1, status: 'cooking' },
            });

            const result = await orderApi.updateOrderStatus(1, {
                status: 'cooking',
                deviceId: 'device-123',
            });

            expect(kitchenApi.patch).toHaveBeenCalledWith(
                API_ENDPOINTS.ORDER_STATUS(1),
                {
                    status: 'cooking',
                    device_id: 'device-123',
                }
            );
            expect(result.success).toBe(true);
        });

        it('should include station in payload', async () => {
            kitchenApi.patch.mockResolvedValue({
                success: true,
            });

            await orderApi.updateOrderStatus(1, {
                status: 'cooking',
                deviceId: 'device-123',
                station: 'hot',
            });

            expect(kitchenApi.patch).toHaveBeenCalledWith(
                expect.any(String),
                expect.objectContaining({ station: 'hot' })
            );
        });

        it('should throw on failure', async () => {
            kitchenApi.patch.mockResolvedValue({
                success: false,
                message: 'Order not found',
            });

            await expect(
                orderApi.updateOrderStatus(999, {
                    status: 'cooking',
                    deviceId: 'device-123',
                })
            ).rejects.toThrow('Order not found');
        });
    });

    // ==================== Convenience Methods ====================

    describe('startCooking()', () => {
        it('should call updateOrderStatus with cooking status', async () => {
            const updateSpy = vi.spyOn(orderApi, 'updateOrderStatus')
                .mockResolvedValue({ success: true });

            await orderApi.startCooking(1, 'device-123', 'hot');

            expect(updateSpy).toHaveBeenCalledWith(1, {
                status: 'cooking',
                deviceId: 'device-123',
                station: 'hot',
            });
        });
    });

    describe('markReady()', () => {
        it('should call updateOrderStatus with ready status', async () => {
            const updateSpy = vi.spyOn(orderApi, 'updateOrderStatus')
                .mockResolvedValue({ success: true });

            await orderApi.markReady(1, 'device-123');

            expect(updateSpy).toHaveBeenCalledWith(1, {
                status: 'ready',
                deviceId: 'device-123',
                station: undefined,
            });
        });
    });

    describe('returnToNew()', () => {
        it('should call updateOrderStatus with return_to_new status', async () => {
            const updateSpy = vi.spyOn(orderApi, 'updateOrderStatus')
                .mockResolvedValue({ success: true });

            await orderApi.returnToNew(1, 'device-123');

            expect(updateSpy).toHaveBeenCalledWith(1, {
                status: 'return_to_new',
                deviceId: 'device-123',
                station: undefined,
            });
        });
    });

    describe('returnToCooking()', () => {
        it('should call updateOrderStatus with return_to_cooking status', async () => {
            const updateSpy = vi.spyOn(orderApi, 'updateOrderStatus')
                .mockResolvedValue({ success: true });

            await orderApi.returnToCooking(1, 'device-123');

            expect(updateSpy).toHaveBeenCalledWith(1, {
                status: 'return_to_cooking',
                deviceId: 'device-123',
                station: undefined,
            });
        });
    });

    // ==================== Item Operations ====================

    describe('updateItemStatus()', () => {
        it('should update item status', async () => {
            kitchenApi.patch.mockResolvedValue({
                success: true,
                data: { id: 10, status: 'ready' },
            });

            const result = await orderApi.updateItemStatus(10, {
                status: 'ready',
                deviceId: 'device-123',
            });

            expect(kitchenApi.patch).toHaveBeenCalledWith(
                API_ENDPOINTS.ITEM_STATUS(10),
                {
                    status: 'ready',
                    device_id: 'device-123',
                }
            );
            expect(result.success).toBe(true);
        });
    });

    describe('markItemReady()', () => {
        it('should call updateItemStatus with ready status', async () => {
            const updateSpy = vi.spyOn(orderApi, 'updateItemStatus')
                .mockResolvedValue({ success: true });

            await orderApi.markItemReady(10, 'device-123');

            expect(updateSpy).toHaveBeenCalledWith(10, {
                status: 'ready',
                deviceId: 'device-123',
            });
        });
    });

    // ==================== Call Waiter ====================

    describe('callWaiter()', () => {
        it('should call waiter endpoint', async () => {
            kitchenApi.post.mockResolvedValue({
                success: true,
                data: { waiter_name: 'John' },
            });

            const result = await orderApi.callWaiter(1, 'device-123');

            expect(kitchenApi.post).toHaveBeenCalledWith(
                API_ENDPOINTS.CALL_WAITER(1),
                { device_id: 'device-123' }
            );
            expect(result.success).toBe(true);
        });

        it('should throw on failure', async () => {
            kitchenApi.post.mockResolvedValue({
                success: false,
                message: 'Waiter not available',
            });

            await expect(
                orderApi.callWaiter(1, 'device-123')
            ).rejects.toThrow('Waiter not available');
        });
    });
});
