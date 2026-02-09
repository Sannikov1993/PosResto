import http, { extractArray } from '../httpClient';

const writeOffs = {
    async getAll(params = {}) {
        const res = await http.get('/write-offs', { params });
        return extractArray(res);
    },

    async getCancelledOrders(params = {}) {
        const res = await http.get('/write-offs/cancelled-orders', { params });
        return extractArray(res);
    },

    async create(data) {
        const formData = new FormData();

        formData.append('type', data.type);
        if (data.description) formData.append('description', data.description);
        if (data.warehouse_id) formData.append('warehouse_id', data.warehouse_id);
        if (data.manager_id) formData.append('manager_id', data.manager_id);
        if (data.photo) formData.append('photo', data.photo);

        if (data.items && data.items.length > 0) {
            formData.append('items', JSON.stringify(data.items));
        } else if (data.amount) {
            formData.append('amount', data.amount);
        }

        return http.post('/write-offs', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
    },

    async get(id) {
        return http.get(`/write-offs/${id}`);
    },

    async getSettings() {
        return http.get('/write-offs/settings');
    },

    async verifyManager(pin) {
        return http.post('/write-offs/verify-manager', { pin });
    }
};

export default writeOffs;
