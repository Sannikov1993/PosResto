import http, { extractArray } from '../httpClient';

const orders = {
    async getAll(params = {}) {
        const res = await http.get('/orders', { params });
        return extractArray(res);
    },

    async getActive() {
        const res = await http.get('/orders', {
            params: { status: 'new,confirmed,cooking,ready,served', type: 'dine_in' }
        });
        return extractArray(res);
    },

    async getPaidToday() {
        const res = await http.get('/orders', { params: { paid_today: true } });
        return extractArray(res);
    },

    async getDelivery() {
        const res = await http.get('/delivery/orders');
        return extractArray(res);
    },

    async createDelivery(orderData) {
        return http.post('/delivery/orders', orderData);
    },

    async updateDeliveryStatus(orderId, deliveryStatus) {
        return http.patch(`/delivery/orders/${orderId}/status`, {
            delivery_status: deliveryStatus
        });
    },

    async get(id) {
        return http.get(`/orders/${id}`);
    },

    async create(orderData) {
        return http.post('/orders', orderData);
    },

    async update(id, orderData) {
        return http.put(`/orders/${id}`, orderData);
    },

    async pay(id, paymentData) {
        return http.post(`/orders/${id}/pay`, paymentData);
    },

    async cancel(id, reason, managerId, isWriteOff = false) {
        return http.post(`/orders/${id}/cancel-with-writeoff`, {
            reason,
            manager_id: managerId,
            is_write_off: isWriteOff
        });
    },

    async requestCancellation(id, reason, requestedBy = null) {
        return http.post(`/orders/${id}/request-cancellation`, {
            reason,
            requested_by: requestedBy
        });
    },

    // Печать
    async printReceipt(id) {
        return http.post(`/orders/${id}/print/receipt`);
    },

    async printPrecheck(id) {
        return http.post(`/orders/${id}/print/precheck`);
    },

    async printToKitchen(id) {
        return http.post(`/orders/${id}/print/kitchen`);
    },

    async getReceiptData(id) {
        return http.get(`/orders/${id}/print/data`);
    },

    // Перенос заказа
    async transfer(id, targetTableId, force = false) {
        return http.post(`/orders/${id}/transfer`, { target_table_id: targetTableId, force });
    },

    // Оплата (v1 API)
    async payV1(id, paymentData) {
        return http.post(`/v1/orders/${id}/pay`, paymentData);
    },

    async printReceiptV1(id) {
        return http.post(`/v1/orders/${id}/print/receipt`);
    },

    async getPaymentSplitPreview(id) {
        return http.get(`/v1/orders/${id}/payment-split-preview`);
    }
};

const orderItems = {
    async cancel(itemId, data) {
        return http.post(`/order-items/${itemId}/cancel`, data);
    },

    async requestCancellation(itemId, reason) {
        return http.post(`/order-items/${itemId}/request-cancellation`, { reason });
    },

    async approveCancellation(itemId) {
        return http.post(`/order-items/${itemId}/approve-cancellation`);
    },

    async rejectCancellation(itemId, reason = null) {
        return http.post(`/order-items/${itemId}/reject-cancellation`, { reason });
    }
};

const cancellations = {
    async getPending() {
        const res = await http.get('/cancellations/pending');
        return extractArray(res);
    },

    async approve(id) {
        return http.post(`/cancellations/${id}/approve`);
    },

    async reject(id, reason = null) {
        return http.post(`/cancellations/${id}/reject`, { reason });
    }
};

export { orders, orderItems, cancellations };
