import http, { extractArray, extractData } from '../httpClient.js';
import type { DeliveryOrder, DeliveryZone, DeliveryProblem, User } from '@/shared/types';

interface CalculateDeliveryParams {
    address: string;
    total: number;
    lat?: number;
    lng?: number;
}

interface MapData {
    orders?: DeliveryOrder[];
    couriers?: User[];
    [key: string]: unknown;
}

const delivery = {
    async calculateDelivery({ address, total, lat, lng }: CalculateDeliveryParams): Promise<unknown> {
        return http.post('/delivery/calculate', { address, total, lat, lng });
    },

    async getZones(): Promise<DeliveryZone[]> {
        const res = await http.get('/delivery/zones');
        return extractArray<DeliveryZone>(res);
    },

    async getOrders(params: Record<string, any> = {}): Promise<DeliveryOrder[]> {
        const res = await http.get('/delivery/orders', { params });
        return extractArray<DeliveryOrder>(res);
    },

    async assignCourier(orderId: number, courierId: number): Promise<unknown> {
        return http.post(`/delivery/orders/${orderId}/assign-courier`, { courier_id: courierId });
    },

    async getProblems(params: Record<string, any> = {}): Promise<DeliveryProblem[]> {
        const res = await http.get('/delivery/problems', { params });
        return extractArray<DeliveryProblem>(res);
    },

    async resolveProblem(problemId: number, resolution: string): Promise<unknown> {
        return http.patch(`/delivery/problems/${problemId}/resolve`, { resolution });
    },

    async deleteProblem(problemId: number): Promise<unknown> {
        return http.delete(`/delivery/problems/${problemId}`);
    },

    async getMapData(): Promise<MapData> {
        const res = await http.get('/delivery/map-data');
        return extractData<MapData>(res);
    }
};

const couriers = {
    async getAll(): Promise<User[]> {
        const res = await http.get('/delivery/couriers');
        return extractArray<User>(res);
    },

    async assign(orderId: number, courierId: number): Promise<unknown> {
        return http.post(`/delivery/orders/${orderId}/assign-courier`, {
            courier_id: courierId
        });
    }
};

export { delivery, couriers };
