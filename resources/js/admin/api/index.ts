/**
 * Admin API Module - Централизованные API вызовы для админ-панели
 */

import { createHttpClient } from '../../shared/services/httpClient.js';

const { http, extractArray, extractData } = createHttpClient({ module: 'Admin' });

const auth = {
    async login(email: string, password: string) {
        const res = await http.post('/auth/login', { email, password });
        return extractData(res);
    },
};

const admin = {
    async getStats() {
        const res = await http.get('/admin/stats');
        return extractData(res);
    },
};

const menu = {
    async getCategories() {
        const res = await http.get('/menu/categories');
        return extractArray(res);
    },

    async getDishes() {
        const res = await http.get('/menu/dishes');
        return extractArray(res);
    },

    async saveCategory(data: Record<string, any>) {
        const method = data.id ? 'put' : 'post';
        const url = data.id ? `/menu/categories/${data.id}` : '/menu/categories';
        return http[method](url, data);
    },

    async deleteCategory(id: number) {
        return http.delete(`/menu/categories/${id}`);
    },

    async saveDish(data: Record<string, any>) {
        const method = data.id ? 'put' : 'post';
        const url = data.id ? `/menu/dishes/${data.id}` : '/menu/dishes';
        return http[method](url, data);
    },

    async deleteDish(id: number) {
        return http.delete(`/menu/dishes/${id}`);
    },
};

const staff = {
    async getAll() {
        const res = await http.get('/staff');
        return extractArray(res);
    },

    async save(data: Record<string, any>) {
        const method = data.id ? 'put' : 'post';
        const url = data.id ? `/staff/${data.id}` : '/staff';
        return http[method](url, data);
    },
};

const tables = {
    async getAll() {
        const res = await http.get('/tables');
        return extractArray(res);
    },

    async getZones() {
        const res = await http.get('/tables/zones');
        return extractArray(res);
    },
};

const settings = {
    async get() {
        const res = await http.get('/settings');
        return extractData(res);
    },

    async save(data: Record<string, any>) {
        return http.put('/settings', data);
    },
};

export default {
    auth,
    admin,
    menu,
    staff,
    tables,
    settings,
};
