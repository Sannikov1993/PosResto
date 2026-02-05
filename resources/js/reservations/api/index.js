/**
 * Reservations API Module - Централизованные API вызовы для бронирований
 */

import { createHttpClient } from '../../shared/services/httpClient.js';

const { http, extractArray, extractData } = createHttpClient({ module: 'Reservations' });

const reservations = {
    async getBusinessDate() {
        try {
            const res = await http.get('/reservations/business-date');
            return extractData(res);
        } catch {
            return null;
        }
    },

    async getCalendar(month, year) {
        const res = await http.get('/reservations/calendar', { params: { month, year } });
        return extractData(res);
    },

    async getAll(params = {}) {
        const res = await http.get('/reservations', { params });
        return extractArray(res);
    },

    async getByDate(date) {
        const res = await http.get('/reservations', { params: { date } });
        return extractArray(res);
    },

    async getStats() {
        const res = await http.get('/reservations/stats');
        return extractData(res);
    },

    async create(data) {
        return http.post('/reservations', data);
    },

    async update(id, data) {
        return http.put(`/reservations/${id}`, data);
    },

    async updateStatus(id, action) {
        return http.post(`/reservations/${id}/${action}`);
    },

    async getPreorderItems(id) {
        return http.get(`/reservations/${id}/preorder-items`);
    },

    async addPreorderItem(id, data) {
        return http.post(`/reservations/${id}/preorder-items`, data);
    },

    async savePreorder(id) {
        return http.post(`/reservations/${id}/preorder`);
    },
};

const tables = {
    async getAll() {
        const res = await http.get('/tables');
        return extractArray(res);
    },
};

const menu = {
    async getCategories() {
        const res = await http.get('/menu/categories');
        return extractArray(res);
    },

    async getDishes(params = {}) {
        const res = await http.get('/menu/dishes', { params });
        return extractArray(res);
    },
};

export default { reservations, tables, menu };
