import http from '../httpClient.js';
import type { BarOrder, BarCounts, BarStation } from '@/shared/types';

interface BarCheckResponse {
    has_bar: boolean;
    [key: string]: unknown;
}

interface BarOrdersResponse {
    items: BarOrder[];
    station: BarStation | null;
    counts: BarCounts;
}

const bar = {
    async check(): Promise<BarCheckResponse> {
        try {
            return await http.get('/bar/check');
        } catch {
            return { has_bar: false };
        }
    },

    async getOrders(): Promise<BarOrdersResponse> {
        try {
            const response = await http.get('/bar/orders') as Record<string, any>;
            return {
                items: (response.data || []) as BarOrder[],
                station: (response.station || null) as BarStation | null,
                counts: (response.counts || { new: 0, in_progress: 0, ready: 0 }) as BarCounts
            };
        } catch {
            return { items: [], station: null, counts: { new: 0, in_progress: 0, ready: 0 } };
        }
    },

    async updateItemStatus(itemId: number, status: string): Promise<unknown> {
        return http.post('/bar/item-status', { item_id: itemId, status });
    }
};

export default bar;
