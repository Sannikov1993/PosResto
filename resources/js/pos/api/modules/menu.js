import http, { extractArray, extractData } from '../httpClient';

const menu = {
    async getAll(priceListId = null) {
        const params = {};
        if (priceListId) params.price_list_id = priceListId;
        const res = await http.get('/menu', { params });
        return extractData(res);
    },

    async getCategories() {
        const res = await http.get('/categories');
        return extractArray(res);
    },

    async getDishes(categoryId = null, priceListId = null) {
        const params = { available: 1 };
        if (categoryId) {
            params.category_id = categoryId;
        }
        if (priceListId) {
            params.price_list_id = priceListId;
        }
        const res = await http.get('/dishes', { params });
        return extractArray(res);
    },

    async getDish(id) {
        const res = await http.get(`/dishes/${id}`);
        return extractData(res);
    }
};

const priceLists = {
    async getAll() {
        const res = await http.get('/price-lists');
        return extractArray(res);
    },

    async getActive() {
        const list = await this.getAll();
        return list.filter(pl => pl.is_active);
    }
};

const stopList = {
    async getAll() {
        const res = await http.get('/stop-list');
        return extractArray(res);
    },

    async searchDishes(query) {
        const res = await http.get('/stop-list/search-dishes', { params: { q: query } });
        return extractArray(res);
    },

    async add(dishId, reason, resumeAt = null) {
        return http.post('/stop-list', {
            dish_id: dishId,
            reason,
            resume_at: resumeAt
        });
    },

    async remove(dishId) {
        return http.delete(`/stop-list/${dishId}`);
    },

    async update(dishId, reason, resumeAt) {
        return http.put(`/stop-list/${dishId}`, { reason, resume_at: resumeAt });
    }
};

export { menu, priceLists, stopList };
