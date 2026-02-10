import http, { extractArray, extractData } from '../httpClient.js';
import type { Reservation } from '@/shared/types';

interface CalendarData {
    [date: string]: unknown;
}

const reservations = {
    async getAll(params: Record<string, any> = {}): Promise<Reservation[]> {
        const res = await http.get('/reservations', { params });
        return extractArray<Reservation>(res);
    },

    async getByDate(date: string): Promise<Reservation[]> {
        const res = await http.get('/reservations', { params: { date } });
        return extractArray<Reservation>(res);
    },

    async getByTable(tableId: number, date: string): Promise<Reservation[]> {
        const res = await http.get('/reservations', { params: { table_id: tableId, date } });
        return extractArray<Reservation>(res);
    },

    async getCalendar(year: number, month: number): Promise<CalendarData> {
        const res = await http.get('/reservations/calendar', { params: { year, month } });
        return extractData<CalendarData>(res);
    },

    async create(data: Record<string, any>): Promise<unknown> {
        return http.post('/reservations', data);
    },

    async update(id: number, data: Record<string, any>): Promise<unknown> {
        return http.put(`/reservations/${id}`, data);
    },

    async cancel(id: number, reason: string | null = null, refundDeposit = false, refundMethod = 'cash'): Promise<unknown> {
        return http.post(`/reservations/${id}/cancel`, {
            reason,
            refund_deposit: refundDeposit,
            refund_method: refundMethod
        });
    },

    async seat(id: number): Promise<unknown> {
        return http.post(`/reservations/${id}/seat`);
    },

    async seatWithOrder(id: number): Promise<unknown> {
        return http.post(`/reservations/${id}/seat-with-order`);
    },

    async unseat(id: number): Promise<unknown> {
        return http.post(`/reservations/${id}/unseat`);
    },

    async delete(id: number): Promise<unknown> {
        return http.delete(`/reservations/${id}`);
    },

    async checkConflict(
        tableId: number,
        date: string,
        timeFrom: string,
        timeTo: string,
        excludeId: number | null = null
    ): Promise<unknown> {
        return http.post('/reservations/check-conflict', {
            table_id: tableId,
            date,
            time_from: timeFrom,
            time_to: timeTo,
            exclude_id: excludeId
        });
    },

    // Депозит
    async payDeposit(id: number, method: string, amount: number | null = null): Promise<unknown> {
        const payload: Record<string, any> = { method };
        if (amount !== null) payload.amount = amount;
        return http.post(`/reservations/${id}/deposit/pay`, payload);
    },

    async refundDeposit(id: number, reason: string | null = null): Promise<unknown> {
        return http.post(`/reservations/${id}/deposit/refund`, { reason });
    },

    async getBusinessDate(): Promise<unknown> {
        try {
            return await http.get('/reservations/business-date');
        } catch {
            return null;
        }
    },

    // Preorder items
    async getPreorderItems(reservationId: number): Promise<unknown> {
        return http.get(`/reservations/${reservationId}/preorder-items`);
    },

    async addPreorderItem(reservationId: number, data: Record<string, any>): Promise<unknown> {
        return http.post(`/reservations/${reservationId}/preorder-items`, data);
    },

    async updatePreorderItem(reservationId: number, itemId: number, data: Record<string, any>): Promise<unknown> {
        return http.patch(`/reservations/${reservationId}/preorder-items/${itemId}`, data);
    },

    async deletePreorderItem(reservationId: number, itemId: number): Promise<unknown> {
        return http.delete(`/reservations/${reservationId}/preorder-items/${itemId}`);
    },

    async printPreorder(reservationId: number): Promise<unknown> {
        return http.post(`/reservations/${reservationId}/print-preorder`);
    }
};

export default reservations;
