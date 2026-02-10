import http, { extractArray, extractData } from '../httpClient.js';
import type { Category, Dish, PriceList, StopListItem } from '@/shared/types';

interface MenuData {
    categories?: Category[];
    dishes?: Dish[];
    [key: string]: unknown;
}

const menu = {
    async getAll(priceListId: number | null = null): Promise<MenuData> {
        const params: Record<string, any> = {};
        if (priceListId) params.price_list_id = priceListId;
        const res = await http.get('/menu', { params });
        return extractData<MenuData>(res);
    },

    async getCategories(): Promise<Category[]> {
        const res = await http.get('/categories');
        return extractArray<Category>(res);
    },

    async getDishes(categoryId: number | null = null, priceListId: number | null = null): Promise<Dish[]> {
        const params: Record<string, any> = { available: 1 };
        if (categoryId) {
            params.category_id = categoryId;
        }
        if (priceListId) {
            params.price_list_id = priceListId;
        }
        const res = await http.get('/dishes', { params });
        return extractArray<Dish>(res);
    },

    async getDish(id: number): Promise<Dish> {
        const res = await http.get(`/dishes/${id}`);
        return extractData<Dish>(res);
    }
};

const priceLists = {
    async getAll(): Promise<PriceList[]> {
        const res = await http.get('/price-lists');
        return extractArray<PriceList>(res);
    },

    async getActive(): Promise<PriceList[]> {
        const list = await priceLists.getAll();
        return list.filter((pl: PriceList) => pl.is_active);
    }
};

const stopList = {
    async getAll(): Promise<StopListItem[]> {
        const res = await http.get('/stop-list');
        return extractArray<StopListItem>(res);
    },

    async searchDishes(query: string): Promise<Dish[]> {
        const res = await http.get('/stop-list/search-dishes', { params: { q: query } });
        return extractArray<Dish>(res);
    },

    async add(dishId: number, reason: string, resumeAt: string | null = null): Promise<unknown> {
        return http.post('/stop-list', {
            dish_id: dishId,
            reason,
            resume_at: resumeAt
        });
    },

    async remove(dishId: number): Promise<unknown> {
        return http.delete(`/stop-list/${dishId}`);
    },

    async update(dishId: number, reason: string, resumeAt: string | null): Promise<unknown> {
        return http.put(`/stop-list/${dishId}`, { reason, resume_at: resumeAt });
    }
};

export { menu, priceLists, stopList };
