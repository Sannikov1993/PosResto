import http, { extractArray, extractData } from '../httpClient';

const reservations = {
    async getAll(params = {}) {
        const res = await http.get('/reservations', { params });
        return extractArray(res);
    },

    async getByDate(date) {
        const res = await http.get('/reservations', { params: { date } });
        return extractArray(res);
    },

    async getByTable(tableId, date) {
        const res = await http.get('/reservations', { params: { table_id: tableId, date } });
        return extractArray(res);
    },

    async getCalendar(year, month) {
        const res = await http.get('/reservations/calendar', { params: { year, month } });
        return extractData(res);
    },

    async create(data) {
        return http.post('/reservations', data);
    },

    async update(id, data) {
        return http.put(`/reservations/${id}`, data);
    },

    async cancel(id, reason = null, refundDeposit = false, refundMethod = 'cash') {
        return http.post(`/reservations/${id}/cancel`, {
            reason,
            refund_deposit: refundDeposit,
            refund_method: refundMethod
        });
    },

    async seat(id) {
        return http.post(`/reservations/${id}/seat`);
    },

    async seatWithOrder(id) {
        return http.post(`/reservations/${id}/seat-with-order`);
    },

    async unseat(id) {
        return http.post(`/reservations/${id}/unseat`);
    },

    async delete(id) {
        return http.delete(`/reservations/${id}`);
    },

    async checkConflict(tableId, date, timeFrom, timeTo, excludeId = null) {
        return http.post('/reservations/check-conflict', {
            table_id: tableId,
            date,
            time_from: timeFrom,
            time_to: timeTo,
            exclude_id: excludeId
        });
    },

    // Депозит
    async payDeposit(id, method, amount = null) {
        const payload = { method };
        if (amount !== null) payload.amount = amount;
        return http.post(`/reservations/${id}/deposit/pay`, payload);
    },

    async refundDeposit(id, reason = null) {
        return http.post(`/reservations/${id}/deposit/refund`, { reason });
    },

    async getBusinessDate() {
        try {
            return await http.get('/reservations/business-date');
        } catch {
            return null;
        }
    },

    // Preorder items
    async getPreorderItems(reservationId) {
        return http.get(`/reservations/${reservationId}/preorder-items`);
    },

    async addPreorderItem(reservationId, data) {
        return http.post(`/reservations/${reservationId}/preorder-items`, data);
    },

    async updatePreorderItem(reservationId, itemId, data) {
        return http.patch(`/reservations/${reservationId}/preorder-items/${itemId}`, data);
    },

    async deletePreorderItem(reservationId, itemId) {
        return http.delete(`/reservations/${reservationId}/preorder-items/${itemId}`);
    },

    async printPreorder(reservationId) {
        return http.post(`/reservations/${reservationId}/print-preorder`);
    }
};

export default reservations;
