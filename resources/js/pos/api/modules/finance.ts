import http, { extractArray, extractData } from '../httpClient.js';
import type { CashShift, CashOperation, PaymentMethod } from '@/shared/types';

interface LastBalance {
    closing_amount: number;
}

const shifts = {
    async getAll(): Promise<CashShift[]> {
        const res = await http.get('/finance/shifts');
        return extractArray<CashShift>(res);
    },

    async getCurrent(): Promise<CashShift | null> {
        try {
            const response = await http.get('/finance/shifts/current');
            const data = extractData<CashShift>(response);
            return data?.id ? data : null;
        } catch {
            return null;
        }
    },

    async getLastBalance(): Promise<LastBalance> {
        try {
            const res = await http.get('/finance/shifts/last-balance');
            return extractData<LastBalance>(res) || { closing_amount: 0 };
        } catch {
            return { closing_amount: 0 };
        }
    },

    async get(id: number): Promise<CashShift> {
        const res = await http.get(`/finance/shifts/${id}`);
        return extractData<CashShift>(res);
    },

    async getOrders(id: number): Promise<any[]> {
        const res = await http.get(`/finance/shifts/${id}/orders`);
        return extractArray(res);
    },

    async getPrepayments(id: number): Promise<any[]> {
        const res = await http.get(`/finance/shifts/${id}/prepayments`);
        return extractArray(res);
    },

    async open(openingAmount: number, cashierId: number | null = null): Promise<unknown> {
        return http.post('/finance/shifts/open', {
            opening_cash: openingAmount,
            cashier_id: cashierId
        });
    },

    async close(id: number, closingAmount: number): Promise<unknown> {
        return http.post(`/finance/shifts/${id}/close`, {
            closing_amount: closingAmount
        });
    }
};

const cashOperations = {
    async deposit(amount: number, description: string | null = null): Promise<unknown> {
        return http.post('/finance/operations/deposit', {
            amount,
            description
        });
    },

    async withdrawal(amount: number, category: string, description: string | null = null): Promise<unknown> {
        return http.post('/finance/operations/withdrawal', {
            amount,
            category,
            description
        });
    },

    async orderPrepayment(
        amount: number,
        paymentMethod: PaymentMethod,
        customerName: string | null = null,
        orderType = 'delivery',
        orderId: number | null = null,
        orderNumber: string | null = null
    ): Promise<unknown> {
        return http.post('/finance/operations/order-prepayment', {
            amount,
            payment_method: paymentMethod,
            customer_name: customerName,
            order_type: orderType,
            order_id: orderId,
            order_number: orderNumber
        });
    },

    async refund(
        amount: number,
        refundMethod: PaymentMethod,
        orderId: number | null = null,
        orderNumber: string | null = null,
        reason: string | null = null
    ): Promise<unknown> {
        return http.post('/finance/operations/refund', {
            amount,
            refund_method: refundMethod,
            order_id: orderId,
            order_number: orderNumber,
            reason
        });
    },

    async getAll(params: Record<string, any> = {}): Promise<CashOperation[]> {
        const res = await http.get('/finance/operations', { params });
        return extractArray<CashOperation>(res);
    }
};

export { shifts, cashOperations };
