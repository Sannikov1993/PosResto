import http, { extractArray, extractData } from '../httpClient.js';
import type { Table, Zone } from '@/shared/types';

const tables = {
    async getAll(): Promise<Table[]> {
        const res = await http.get('/tables');
        return extractArray<Table>(res);
    },

    async get(id: number): Promise<Table> {
        const res = await http.get(`/tables/${id}`);
        return extractData<Table>(res);
    },

    async getOrders(id: number): Promise<any[]> {
        const res = await http.get(`/tables/${id}/orders`);
        return extractArray(res);
    },

    async getOrderData(id: number, params: Record<string, any> = {}): Promise<unknown> {
        return http.get(`/tables/${id}/order-data`, { params });
    }
};

const zones = {
    async getAll(): Promise<Zone[]> {
        const res = await http.get('/zones');
        return extractArray<Zone>(res);
    }
};

export { tables, zones };
