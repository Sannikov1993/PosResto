import http, { extractArray } from '../httpClient.js';
import type { WriteOff } from '@/shared/types';

interface WriteOffCreateData {
    type: string;
    description?: string;
    warehouse_id?: number;
    manager_id?: number;
    photo?: File;
    items?: Array<Record<string, any>>;
    amount?: number;
}

interface WriteOffSettings {
    [key: string]: unknown;
}

interface VerifyManagerResult {
    success: boolean;
    manager_id?: number;
    [key: string]: unknown;
}

const writeOffs = {
    async getAll(params: Record<string, any> = {}): Promise<WriteOff[]> {
        const res = await http.get('/write-offs', { params });
        return extractArray<WriteOff>(res);
    },

    async getCancelledOrders(params: Record<string, any> = {}): Promise<any[]> {
        const res = await http.get('/write-offs/cancelled-orders', { params });
        return extractArray(res);
    },

    async create(data: WriteOffCreateData): Promise<unknown> {
        const formData = new FormData();

        formData.append('type', data.type);
        if (data.description) formData.append('description', data.description);
        if (data.warehouse_id) formData.append('warehouse_id', String(data.warehouse_id));
        if (data.manager_id) formData.append('manager_id', String(data.manager_id));
        if (data.photo) formData.append('photo', data.photo);

        if (data.items && data.items.length > 0) {
            formData.append('items', JSON.stringify(data.items));
        } else if (data.amount) {
            formData.append('amount', String(data.amount));
        }

        return http.post('/write-offs', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
    },

    async get(id: number): Promise<unknown> {
        return http.get(`/write-offs/${id}`);
    },

    async getSettings(): Promise<WriteOffSettings> {
        return http.get('/write-offs/settings') as Promise<WriteOffSettings>;
    },

    async verifyManager(pin: string): Promise<VerifyManagerResult> {
        return http.post('/write-offs/verify-manager', { pin }) as Promise<VerifyManagerResult>;
    }
};

export default writeOffs;
