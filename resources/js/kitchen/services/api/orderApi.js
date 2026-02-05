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

/**
 * @typedef {import('../../types').Order} Order
 * @typedef {import('../../types').ApiResponse} ApiResponse
 */

/**
 * Order API service
 */
class OrderApiService {
    /**
     * Fetch orders for a device
     * @param {Object} params - Query parameters
     * @param {string} params.deviceId - Device identifier
     * @param {string} [params.date] - Date filter (YYYY-MM-DD)
     * @param {string} [params.station] - Station slug filter
     * @returns {Promise<Order[]>}
     */
    async getOrders({ deviceId, date, station }) {
        const params = { device_id: deviceId };
        if (date) params.date = date;
        if (station) params.station = station;

        const response = await kitchenApi.get(API_ENDPOINTS.ORDERS, params, {
            dedupeKey: `orders-${deviceId}-${date}-${station}`,
        });

        if (!response.success) {
            throw new Error(response.message || 'Failed to fetch orders');
        }

        const orders = response.data || [];

        // Validate response in development
        safeValidate(orders, OrdersArraySchema, 'getOrders');

        return orders;
    }

    /**
     * Get order counts by date for calendar
     * @param {Object} params - Query parameters
     * @param {string} params.deviceId - Device identifier
     * @param {string} params.startDate - Start date (YYYY-MM-DD)
     * @param {string} params.endDate - End date (YYYY-MM-DD)
     * @param {string} [params.station] - Station slug filter
     * @returns {Promise<Object.<string, number>>} Map of date to order count
     */
    async getOrderCountsByDate({ deviceId, startDate, endDate, station }) {
        const params = {
            device_id: deviceId,
            start_date: startDate,
            end_date: endDate,
        };
        if (station) params.station = station;

        const response = await kitchenApi.get(API_ENDPOINTS.ORDER_COUNTS, params);

        if (!response.success) {
            throw new Error(response.message || 'Failed to fetch order counts');
        }

        return response.data || {};
    }

    /**
     * Update order status
     * @param {number} orderId - Order ID
     * @param {Object} data - Update data
     * @param {string} data.status - New status
     * @param {string} data.deviceId - Device identifier
     * @param {string} [data.station] - Station slug
     * @returns {Promise<ApiResponse>}
     */
    async updateOrderStatus(orderId, { status, deviceId, station }) {
        const payload = {
            status,
            device_id: deviceId,
        };
        if (station) payload.station = station;

        const response = await kitchenApi.patch(
            API_ENDPOINTS.ORDER_STATUS(orderId),
            payload
        );

        if (!response.success) {
            throw new Error(response.message || 'Failed to update order status');
        }

        return response;
    }

    /**
     * Start cooking an order (convenience method)
     * @param {number} orderId - Order ID
     * @param {string} deviceId - Device identifier
     * @param {string} [station] - Station slug
     * @returns {Promise<ApiResponse>}
     */
    async startCooking(orderId, deviceId, station) {
        return this.updateOrderStatus(orderId, {
            status: 'cooking',
            deviceId,
            station,
        });
    }

    /**
     * Mark order as ready (convenience method)
     * @param {number} orderId - Order ID
     * @param {string} deviceId - Device identifier
     * @param {string} [station] - Station slug
     * @returns {Promise<ApiResponse>}
     */
    async markReady(orderId, deviceId, station) {
        return this.updateOrderStatus(orderId, {
            status: 'ready',
            deviceId,
            station,
        });
    }

    /**
     * Return order to new state
     * @param {number} orderId - Order ID
     * @param {string} deviceId - Device identifier
     * @param {string} [station] - Station slug
     * @returns {Promise<ApiResponse>}
     */
    async returnToNew(orderId, deviceId, station) {
        return this.updateOrderStatus(orderId, {
            status: 'return_to_new',
            deviceId,
            station,
        });
    }

    /**
     * Return order to cooking state
     * @param {number} orderId - Order ID
     * @param {string} deviceId - Device identifier
     * @param {string} [station] - Station slug
     * @returns {Promise<ApiResponse>}
     */
    async returnToCooking(orderId, deviceId, station) {
        return this.updateOrderStatus(orderId, {
            status: 'return_to_cooking',
            deviceId,
            station,
        });
    }

    /**
     * Update individual item status
     * @param {number} itemId - Item ID
     * @param {Object} data - Update data
     * @param {string} data.status - New status
     * @param {string} data.deviceId - Device identifier
     * @returns {Promise<ApiResponse>}
     */
    async updateItemStatus(itemId, { status, deviceId }) {
        const response = await kitchenApi.patch(
            API_ENDPOINTS.ITEM_STATUS(itemId),
            { status, device_id: deviceId }
        );

        if (!response.success) {
            throw new Error(response.message || 'Failed to update item status');
        }

        return response;
    }

    /**
     * Mark individual item as ready
     * @param {number} itemId - Item ID
     * @param {string} deviceId - Device identifier
     * @returns {Promise<ApiResponse>}
     */
    async markItemReady(itemId, deviceId) {
        return this.updateItemStatus(itemId, {
            status: 'ready',
            deviceId,
        });
    }

    /**
     * Call waiter for an order
     * @param {number} orderId - Order ID
     * @param {string} deviceId - Device identifier
     * @returns {Promise<ApiResponse>}
     */
    async callWaiter(orderId, deviceId) {
        const response = await kitchenApi.post(
            API_ENDPOINTS.CALL_WAITER(orderId),
            { device_id: deviceId }
        );

        if (!response.success) {
            throw new Error(response.message || 'Failed to call waiter');
        }

        return response;
    }
}

// Export singleton instance
export const orderApi = new OrderApiService();
