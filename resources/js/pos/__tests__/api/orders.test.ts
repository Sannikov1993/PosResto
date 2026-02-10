/**
 * POS Orders API Module Unit Tests
 */

import { describe, it, expect, beforeEach, vi } from 'vitest';

// Mock httpClient
const { mockHttp, mockExtractArray } = vi.hoisted(() => ({
    mockHttp: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
    mockExtractArray: vi.fn((res: any) => {
        if (Array.isArray(res?.data?.data)) return res.data.data;
        if (Array.isArray(res?.data)) return res.data;
        if (Array.isArray(res)) return res;
        return [];
    }),
}));

vi.mock('@/pos/api/httpClient.js', () => ({
    default: mockHttp,
    extractArray: mockExtractArray,
    extractData: vi.fn((res: any) => res?.data?.data || res?.data || res),
}));

import { orders, orderItems, cancellations } from '@/pos/api/modules/orders.js';

describe('POS Orders API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('orders.getAll', () => {
        it('should call GET /orders with params', async () => {
            const mockOrders = [{ id: 1 }, { id: 2 }];
            mockHttp.get.mockResolvedValue({ data: mockOrders });
            mockExtractArray.mockReturnValue(mockOrders);

            const result = await orders.getAll({ status: 'new' });

            expect(mockHttp.get).toHaveBeenCalledWith('/orders', { params: { status: 'new' } });
            expect(result).toEqual(mockOrders);
        });
    });

    describe('orders.getActive', () => {
        it('should fetch active dine-in orders', async () => {
            const mockOrders = [{ id: 1, status: 'new', type: 'dine_in' }];
            mockHttp.get.mockResolvedValue({ data: mockOrders });
            mockExtractArray.mockReturnValue(mockOrders);

            const result = await orders.getActive();

            expect(mockHttp.get).toHaveBeenCalledWith('/orders', {
                params: { status: 'new,confirmed,cooking,ready,served', type: 'dine_in' }
            });
            expect(result).toEqual(mockOrders);
        });
    });

    describe('orders.getPaidToday', () => {
        it('should fetch paid orders for today', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await orders.getPaidToday();

            expect(mockHttp.get).toHaveBeenCalledWith('/orders', { params: { paid_today: true } });
        });
    });

    describe('orders.create', () => {
        it('should POST order data', async () => {
            const orderData = {
                type: 'dine_in',
                table_id: 5,
                items: [{ dish_id: 1, quantity: 2 }],
            };
            mockHttp.post.mockResolvedValue({ data: { id: 100, ...orderData } });

            await orders.create(orderData);

            expect(mockHttp.post).toHaveBeenCalledWith('/orders', orderData);
        });
    });

    describe('orders.pay', () => {
        it('should POST payment data for order', async () => {
            const paymentData = { payment_method: 'cash', amount: 1500 };
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await orders.pay(1, paymentData);

            expect(mockHttp.post).toHaveBeenCalledWith('/orders/1/pay', paymentData);
        });
    });

    describe('orders.cancel', () => {
        it('should POST cancellation with reason', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await orders.cancel(1, 'Customer request', 5, true);

            expect(mockHttp.post).toHaveBeenCalledWith('/orders/1/cancel-with-writeoff', {
                reason: 'Customer request',
                manager_id: 5,
                is_write_off: true,
            });
        });
    });

    describe('orders.transfer', () => {
        it('should POST transfer to target table', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await orders.transfer(1, 10, true);

            expect(mockHttp.post).toHaveBeenCalledWith('/orders/1/transfer', {
                target_table_id: 10,
                force: true,
            });
        });
    });

    describe('orders.getDelivery', () => {
        it('should fetch delivery orders', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await orders.getDelivery();

            expect(mockHttp.get).toHaveBeenCalledWith('/delivery/orders');
        });
    });
});

describe('POS OrderItems API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('orderItems.cancel', () => {
        it('should POST cancel for item', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await orderItems.cancel(10, { reason: 'Out of stock' });

            expect(mockHttp.post).toHaveBeenCalledWith('/order-items/10/cancel', { reason: 'Out of stock' });
        });
    });

    describe('orderItems.requestCancellation', () => {
        it('should POST cancellation request', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await orderItems.requestCancellation(10, 'Bad quality');

            expect(mockHttp.post).toHaveBeenCalledWith('/order-items/10/request-cancellation', {
                reason: 'Bad quality',
            });
        });
    });
});

describe('POS Cancellations API', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('cancellations.getPending', () => {
        it('should fetch pending cancellations', async () => {
            mockHttp.get.mockResolvedValue({ data: [] });
            mockExtractArray.mockReturnValue([]);

            await cancellations.getPending();

            expect(mockHttp.get).toHaveBeenCalledWith('/cancellations/pending');
        });
    });

    describe('cancellations.approve', () => {
        it('should POST approval', async () => {
            mockHttp.post.mockResolvedValue({ data: { success: true } });

            await cancellations.approve(5);

            expect(mockHttp.post).toHaveBeenCalledWith('/cancellations/5/approve');
        });
    });
});
