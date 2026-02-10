import http, { extractArray, extractData } from '../httpClient.js';
import type { Customer } from '@/shared/types';

interface CustomerAddress {
    id: number;
    address: string;
    [key: string]: unknown;
}

interface BonusHistoryEntry {
    id: number;
    amount: number;
    [key: string]: unknown;
}

const customers = {
    async getAll(params: Record<string, any> = {}): Promise<Customer[]> {
        const res = await http.get('/customers', { params });
        return extractArray<Customer>(res);
    },

    async search(query: string, limit = 10): Promise<Customer[]> {
        const res = await http.get('/customers/search', { params: { q: query, limit } });
        return extractArray<Customer>(res);
    },

    async get(id: number): Promise<Customer> {
        const res = await http.get(`/customers/${id}`);
        return extractData<Customer>(res);
    },

    async create(data: Record<string, any>): Promise<unknown> {
        return http.post('/customers', data);
    },

    async update(id: number, data: Record<string, any>): Promise<unknown> {
        return http.put(`/customers/${id}`, data);
    },

    async getOrders(id: number): Promise<any[]> {
        const res = await http.get(`/customers/${id}/orders`);
        return extractArray(res);
    },

    async getAddresses(id: number): Promise<CustomerAddress[]> {
        const res = await http.get(`/customers/${id}/addresses`);
        return extractArray<CustomerAddress>(res);
    },

    async getBonusHistory(id: number): Promise<BonusHistoryEntry[]> {
        const res = await http.get(`/customers/${id}/bonus-history`);
        return extractArray<BonusHistoryEntry>(res);
    },

    async toggleBlacklist(id: number): Promise<unknown> {
        return http.post(`/customers/${id}/toggle-blacklist`);
    },

    async saveDeliveryAddress(customerId: number, addressData: Record<string, any>): Promise<unknown> {
        return http.post(`/customers/${customerId}/save-delivery-address`, addressData);
    },

    async deleteAddress(customerId: number, addressId: number): Promise<unknown> {
        return http.delete(`/customers/${customerId}/addresses/${addressId}`);
    },

    async setDefaultAddress(customerId: number, addressId: number): Promise<unknown> {
        return http.post(`/customers/${customerId}/addresses/${addressId}/set-default`);
    }
};

export default customers;
