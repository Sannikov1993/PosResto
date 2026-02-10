import http, { extractArray } from '../httpClient.js';
import type { Order, OrderItem } from '@/shared/types';

interface OrderParams {
    status?: string;
    type?: string;
    paid_today?: boolean;
    [key: string]: unknown;
}

interface CancelData {
    reason?: string;
    manager_id?: number;
    [key: string]: unknown;
}

const orders = {
    async getAll(params: OrderParams = {}): Promise<Order[]> {
        const res = await http.get('/orders', { params });
        return extractArray<Order>(res);
    },

    async getActive(): Promise<Order[]> {
        const res = await http.get('/orders', {
            params: { status: 'new,confirmed,cooking,ready,served', type: 'dine_in' }
        });
        return extractArray<Order>(res);
    },

    async getPaidToday(): Promise<Order[]> {
        const res = await http.get('/orders', { params: { paid_today: true } });
        return extractArray<Order>(res);
    },

    async getDelivery(): Promise<Order[]> {
        const res = await http.get('/delivery/orders');
        return extractArray<Order>(res);
    },

    async createDelivery(orderData: Record<string, any>): Promise<unknown> {
        return http.post('/delivery/orders', orderData);
    },

    async updateDeliveryStatus(orderId: number, deliveryStatus: string): Promise<unknown> {
        return http.patch(`/delivery/orders/${orderId}/status`, {
            delivery_status: deliveryStatus
        });
    },

    async get(id: number): Promise<unknown> {
        return http.get(`/orders/${id}`);
    },

    async create(orderData: Record<string, any>): Promise<unknown> {
        return http.post('/orders', orderData);
    },

    async update(id: number, orderData: Record<string, any>): Promise<unknown> {
        return http.put(`/orders/${id}`, orderData);
    },

    async pay(id: number, paymentData: Record<string, any>): Promise<unknown> {
        return http.post(`/orders/${id}/pay`, paymentData);
    },

    async cancel(id: number, reason: string, managerId: number | null, isWriteOff = false): Promise<unknown> {
        return http.post(`/orders/${id}/cancel-with-writeoff`, {
            reason,
            manager_id: managerId,
            is_write_off: isWriteOff
        });
    },

    async requestCancellation(id: number, reason: string, requestedBy: number | null = null): Promise<unknown> {
        return http.post(`/orders/${id}/request-cancellation`, {
            reason,
            requested_by: requestedBy
        });
    },

    // Печать
    async printReceipt(id: number): Promise<unknown> {
        return http.post(`/orders/${id}/print/receipt`);
    },

    async printPrecheck(id: number): Promise<unknown> {
        return http.post(`/orders/${id}/print/precheck`);
    },

    async printToKitchen(id: number): Promise<unknown> {
        return http.post(`/orders/${id}/print/kitchen`);
    },

    async getReceiptData(id: number): Promise<unknown> {
        return http.get(`/orders/${id}/print/data`);
    },

    // Перенос заказа
    async transfer(id: number, targetTableId: number, force = false): Promise<unknown> {
        return http.post(`/orders/${id}/transfer`, { target_table_id: targetTableId, force });
    },

    // Оплата (v1 API)
    async payV1(id: number, paymentData: Record<string, any>): Promise<unknown> {
        return http.post(`/v1/orders/${id}/pay`, paymentData);
    },

    async printReceiptV1(id: number): Promise<unknown> {
        return http.post(`/v1/orders/${id}/print/receipt`);
    },

    async getPaymentSplitPreview(id: number): Promise<unknown> {
        return http.get(`/v1/orders/${id}/payment-split-preview`);
    }
};

const orderItems = {
    async cancel(itemId: number, data: CancelData): Promise<unknown> {
        return http.post(`/order-items/${itemId}/cancel`, data);
    },

    async requestCancellation(itemId: number, reason: string): Promise<unknown> {
        return http.post(`/order-items/${itemId}/request-cancellation`, { reason });
    },

    async approveCancellation(itemId: number): Promise<unknown> {
        return http.post(`/order-items/${itemId}/approve-cancellation`);
    },

    async rejectCancellation(itemId: number, reason: string | null = null): Promise<unknown> {
        return http.post(`/order-items/${itemId}/reject-cancellation`, { reason });
    }
};

const cancellations = {
    async getPending(): Promise<any[]> {
        const res = await http.get('/cancellations/pending');
        return extractArray(res);
    },

    async approve(id: number): Promise<unknown> {
        return http.post(`/cancellations/${id}/approve`);
    },

    async reject(id: number, reason: string | null = null): Promise<unknown> {
        return http.post(`/cancellations/${id}/reject`, { reason });
    }
};

export { orders, orderItems, cancellations };
