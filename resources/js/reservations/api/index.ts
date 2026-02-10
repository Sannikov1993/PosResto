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

    async getCalendar(month: number, year: number) {
        const res = await http.get('/reservations/calendar', { params: { month, year } });
        return extractData(res);
    },

    async getAll(params: Record<string, any> = {}) {
        const res = await http.get('/reservations', { params });
        return extractArray(res);
    },

    async getByDate(date: string) {
        const res = await http.get('/reservations', { params: { date } });
        return extractArray(res);
    },

    async getStats() {
        const res = await http.get('/reservations/stats');
        return extractData(res);
    },

    async create(data: Record<string, any>) {
        return http.post('/reservations', data);
    },

    async update(id: number, data: Record<string, any>) {
        return http.put(`/reservations/${id}`, data);
    },

    async updateStatus(id: number, action: string) {
        return http.post(`/reservations/${id}/${action}`);
    },

    async getPreorderItems(id: number) {
        return http.get(`/reservations/${id}/preorder-items`);
    },

    async addPreorderItem(id: number, data: { dish_id: number; quantity: number }) {
        return http.post(`/reservations/${id}/preorder-items`, data);
    },

    async savePreorder(id: number) {
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

    async getDishes(params: Record<string, any> = {}) {
        const res = await http.get('/menu/dishes', { params });
        return extractArray(res);
    },
};

export default { reservations, tables, menu };
