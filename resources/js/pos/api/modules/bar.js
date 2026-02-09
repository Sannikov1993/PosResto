import http from '../httpClient';

const bar = {
    async check() {
        try {
            return await http.get('/bar/check');
        } catch {
            return { has_bar: false };
        }
    },

    async getOrders() {
        try {
            const response = await http.get('/bar/orders');
            return {
                items: response.data || [],
                station: response.station || null,
                counts: response.counts || { new: 0, in_progress: 0, ready: 0 }
            };
        } catch {
            return { items: [], station: null, counts: { new: 0, in_progress: 0, ready: 0 } };
        }
    },

    async updateItemStatus(itemId, status) {
        return http.post('/bar/item-status', { item_id: itemId, status });
    }
};

export default bar;
