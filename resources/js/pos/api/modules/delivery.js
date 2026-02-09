import http, { extractArray, extractData } from '../httpClient';

const delivery = {
    async calculateDelivery({ address, total, lat, lng }) {
        return http.post('/delivery/calculate', { address, total, lat, lng });
    },

    async getZones() {
        const res = await http.get('/delivery/zones');
        return extractArray(res);
    },

    async getOrders(params = {}) {
        const res = await http.get('/delivery/orders', { params });
        return extractArray(res);
    },

    async assignCourier(orderId, courierId) {
        return http.post(`/delivery/orders/${orderId}/assign-courier`, { courier_id: courierId });
    },

    async getProblems(params = {}) {
        const res = await http.get('/delivery/problems', { params });
        return extractArray(res);
    },

    async resolveProblem(problemId, resolution) {
        return http.patch(`/delivery/problems/${problemId}/resolve`, { resolution });
    },

    async deleteProblem(problemId) {
        return http.delete(`/delivery/problems/${problemId}`);
    },

    async getMapData() {
        const res = await http.get('/delivery/map-data');
        return extractData(res);
    }
};

const couriers = {
    async getAll() {
        const res = await http.get('/delivery/couriers');
        return extractArray(res);
    },

    async assign(orderId, courierId) {
        return http.post(`/delivery/orders/${orderId}/assign-courier`, {
            courier_id: courierId
        });
    }
};

export { delivery, couriers };
