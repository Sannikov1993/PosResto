import http, { extractArray, extractData } from '../httpClient';

const shifts = {
    async getAll() {
        const res = await http.get('/finance/shifts');
        return extractArray(res);
    },

    async getCurrent() {
        try {
            const response = await http.get('/finance/shifts/current');
            const data = extractData(response);
            return data?.id ? data : null;
        } catch {
            return null;
        }
    },

    async getLastBalance() {
        try {
            const res = await http.get('/finance/shifts/last-balance');
            return extractData(res) || { closing_amount: 0 };
        } catch {
            return { closing_amount: 0 };
        }
    },

    async get(id) {
        const res = await http.get(`/finance/shifts/${id}`);
        return extractData(res);
    },

    async getOrders(id) {
        const res = await http.get(`/finance/shifts/${id}/orders`);
        return extractArray(res);
    },

    async getPrepayments(id) {
        const res = await http.get(`/finance/shifts/${id}/prepayments`);
        return extractArray(res);
    },

    async open(openingAmount, cashierId = null) {
        return http.post('/finance/shifts/open', {
            opening_cash: openingAmount,
            cashier_id: cashierId
        });
    },

    async close(id, closingAmount) {
        return http.post(`/finance/shifts/${id}/close`, {
            closing_amount: closingAmount
        });
    }
};

const cashOperations = {
    async deposit(amount, description = null) {
        return http.post('/finance/operations/deposit', {
            amount,
            description
        });
    },

    async withdrawal(amount, category, description = null) {
        return http.post('/finance/operations/withdrawal', {
            amount,
            category,
            description
        });
    },

    async orderPrepayment(amount, paymentMethod, customerName = null, orderType = 'delivery', orderId = null, orderNumber = null) {
        return http.post('/finance/operations/order-prepayment', {
            amount,
            payment_method: paymentMethod,
            customer_name: customerName,
            order_type: orderType,
            order_id: orderId,
            order_number: orderNumber
        });
    },

    async refund(amount, refundMethod, orderId = null, orderNumber = null, reason = null) {
        return http.post('/finance/operations/refund', {
            amount,
            refund_method: refundMethod,
            order_id: orderId,
            order_number: orderNumber,
            reason
        });
    },

    async getAll(params = {}) {
        const res = await http.get('/finance/operations', { params });
        return extractArray(res);
    }
};

export { shifts, cashOperations };
