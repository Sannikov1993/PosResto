import http, { extractArray, extractData } from '../httpClient';

const customers = {
    async getAll(params = {}) {
        const res = await http.get('/customers', { params });
        return extractArray(res);
    },

    async search(query, limit = 10) {
        const res = await http.get('/customers/search', { params: { q: query, limit } });
        return extractArray(res);
    },

    async get(id) {
        const res = await http.get(`/customers/${id}`);
        return extractData(res);
    },

    async create(data) {
        return http.post('/customers', data);
    },

    async update(id, data) {
        return http.put(`/customers/${id}`, data);
    },

    async getOrders(id) {
        const res = await http.get(`/customers/${id}/orders`);
        return extractArray(res);
    },

    async getAddresses(id) {
        const res = await http.get(`/customers/${id}/addresses`);
        return extractArray(res);
    },

    async getBonusHistory(id) {
        const res = await http.get(`/customers/${id}/bonus-history`);
        return extractArray(res);
    },

    async toggleBlacklist(id) {
        return http.post(`/customers/${id}/toggle-blacklist`);
    },

    async saveDeliveryAddress(customerId, addressData) {
        return http.post(`/customers/${customerId}/save-delivery-address`, addressData);
    },

    async deleteAddress(customerId, addressId) {
        return http.delete(`/customers/${customerId}/addresses/${addressId}`);
    },

    async setDefaultAddress(customerId, addressId) {
        return http.post(`/customers/${customerId}/addresses/${addressId}/set-default`);
    }
};

export default customers;
