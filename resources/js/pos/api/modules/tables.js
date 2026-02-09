import http, { extractArray, extractData } from '../httpClient';

const tables = {
    async getAll() {
        const res = await http.get('/tables');
        return extractArray(res);
    },

    async get(id) {
        const res = await http.get(`/tables/${id}`);
        return extractData(res);
    },

    async getOrders(id) {
        const res = await http.get(`/tables/${id}/orders`);
        return extractArray(res);
    },

    async getOrderData(id, params = {}) {
        return http.get(`/tables/${id}/order-data`, { params });
    }
};

const zones = {
    async getAll() {
        const res = await http.get('/zones');
        return extractArray(res);
    }
};

export { tables, zones };
