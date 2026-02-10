/**
 * Order API Service
 *
 * API methods for order-related operations.
 *
 * @module kitchen/services/api/orderApi
 */

import { kitchenApi } from './kitchenApi.js';
import { API_ENDPOINTS } from '../../constants/api.js';
import { safeValidate, OrdersArraySchema, OrderSchema } from '../../utils/apiSchemas.js';
import type { Order, ApiResponse, OrderCountsByDate } from '../../types/index.js';

interface GetOrdersParams {
    deviceId: string;
    date?: string;
    station?: string;
}

interface GetOrderCountsParams {
    deviceId: string;
    startDate: string;
    endDate: string;
    station?: string;
}

interface UpdateOrderStatusData {
    status: string;
    deviceId: string;
    station?: string;
}

interface UpdateItemStatusData {
    status: string;
    deviceId: string;
}

class OrderApiService {
    async getOrders({ deviceId, date, station }: GetOrdersParams): Promise<Order[]> {
        const params: Record<string, string> = { device_id: deviceId };
        if (date) params.date = date;
        if (station) params.station = station;

        const response = await kitchenApi.get<ApiResponse<Order[]>>(API_ENDPOINTS.ORDERS, params, {
            dedupeKey: `orders-${deviceId}-${date}-${station}`,
        });

        if (!response.success) {
            throw new Error(response.message || 'Failed to fetch orders');
        }

        const orders = response.data || [];
        safeValidate(orders, OrdersArraySchema, 'getOrders');
        return orders;
    }

    async getOrderCountsByDate({ deviceId, startDate, endDate, station }: GetOrderCountsParams): Promise<OrderCountsByDate> {
        const params: Record<string, string> = {
            device_id: deviceId,
            start_date: startDate,
            end_date: endDate,
        };
        if (station) params.station = station;

        const response = await kitchenApi.get<ApiResponse<OrderCountsByDate>>(API_ENDPOINTS.ORDER_COUNTS, params);

        if (!response.success) {
            throw new Error(response.message || 'Failed to fetch order counts');
        }

        return response.data || {};
    }

    async updateOrderStatus(orderId: number, { status, deviceId, station }: UpdateOrderStatusData): Promise<ApiResponse> {
        const payload: Record<string, string> = {
            status,
            device_id: deviceId,
        };
        if (station) payload.station = station;

        const response = await kitchenApi.patch<ApiResponse>(
            API_ENDPOINTS.ORDER_STATUS(orderId),
            payload
        );

        if (!response.success) {
            throw new Error(response.message || 'Failed to update order status');
        }

        return response;
    }

    async startCooking(orderId: number, deviceId: string, station?: string): Promise<ApiResponse> {
        return this.updateOrderStatus(orderId, { status: 'cooking', deviceId, station });
    }

    async markReady(orderId: number, deviceId: string, station?: string): Promise<ApiResponse> {
        return this.updateOrderStatus(orderId, { status: 'ready', deviceId, station });
    }

    async returnToNew(orderId: number, deviceId: string, station?: string): Promise<ApiResponse> {
        return this.updateOrderStatus(orderId, { status: 'return_to_new', deviceId, station });
    }

    async returnToCooking(orderId: number, deviceId: string, station?: string): Promise<ApiResponse> {
        return this.updateOrderStatus(orderId, { status: 'return_to_cooking', deviceId, station });
    }

    async updateItemStatus(itemId: number, { status, deviceId }: UpdateItemStatusData): Promise<ApiResponse> {
        const response = await kitchenApi.patch<ApiResponse>(
            API_ENDPOINTS.ITEM_STATUS(itemId),
            { status, device_id: deviceId }
        );

        if (!response.success) {
            throw new Error(response.message || 'Failed to update item status');
        }

        return response;
    }

    async markItemReady(itemId: number, deviceId: string): Promise<ApiResponse> {
        return this.updateItemStatus(itemId, { status: 'ready', deviceId });
    }

    async callWaiter(orderId: number, deviceId: string): Promise<ApiResponse> {
        const response = await kitchenApi.post<ApiResponse>(
            API_ENDPOINTS.CALL_WAITER(orderId),
            { device_id: deviceId }
        );

        if (!response.success) {
            throw new Error(response.message || 'Failed to call waiter');
        }

        return response;
    }
}

export const orderApi = new OrderApiService();
