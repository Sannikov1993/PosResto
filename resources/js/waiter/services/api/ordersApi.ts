/**
 * Waiter App - Orders API Service
 * Handles orders and order items API calls
 */

import { api } from './client';
import type {
  ApiResponse,
  OrdersResponse,
  OrderResponse,
  GetOrdersParams,
  AddOrderItemRequest,
  UpdateOrderItemRequest,
  PayOrderRequest,
  PayOrderResponse,
  CancelOrderRequest,
  Order,
  OrderItem,
} from '@/waiter/types';

export const ordersApi = {
  // === Orders ===

  /**
   * Get waiter's orders
   */
  async getOrders(params?: GetOrdersParams): Promise<OrdersResponse> {
    return api.get<OrdersResponse>('/waiter/orders', params);
  },

  /**
   * Get today's orders
   */
  async getTodayOrders(): Promise<OrdersResponse> {
    return this.getOrders({ today: true });
  },

  /**
   * Get active orders (not paid/cancelled)
   */
  async getActiveOrders(): Promise<OrdersResponse> {
    return this.getOrders({ status: 'active' });
  },

  /**
   * Get order by ID
   */
  async getOrder(orderId: number): Promise<OrderResponse> {
    return api.get<OrderResponse>(`/waiter/orders/${orderId}`);
  },

  /**
   * Get order for table
   */
  async getTableOrder(tableId: number): Promise<OrderResponse> {
    return api.get<OrderResponse>(`/pos/table/${tableId}/order`);
  },

  // === Order Items ===

  /**
   * Add item to order
   */
  async addItem(tableId: number, data: AddOrderItemRequest): Promise<OrderResponse> {
    return api.post<OrderResponse>(`/pos/table/${tableId}/order`, {
      dish_id: data.dish_id,
      quantity: data.quantity,
      comment: data.comment,
      modifiers: data.modifiers,
    });
  },

  /**
   * Update order item (quantity, comment)
   */
  async updateItem(
    tableId: number,
    itemId: number,
    data: UpdateOrderItemRequest
  ): Promise<OrderResponse> {
    return api.put<OrderResponse>(`/pos/table/${tableId}/item/${itemId}`, data);
  },

  /**
   * Update item quantity
   */
  async updateItemQuantity(
    tableId: number,
    itemId: number,
    quantity: number
  ): Promise<OrderResponse> {
    return this.updateItem(tableId, itemId, { quantity });
  },

  /**
   * Remove item from order
   */
  async removeItem(tableId: number, itemId: number): Promise<OrderResponse> {
    return api.delete<OrderResponse>(`/pos/table/${tableId}/item/${itemId}`);
  },

  // === Order Actions ===

  /**
   * Send order to kitchen
   */
  async sendToKitchen(orderId: number): Promise<OrderResponse> {
    return api.post<OrderResponse>(`/waiter/orders/${orderId}/send-to-kitchen`);
  },

  /**
   * Mark item as served
   */
  async markItemServed(orderId: number, itemId: number): Promise<OrderResponse> {
    return api.post<OrderResponse>(`/waiter/orders/${orderId}/items/${itemId}/serve`);
  },

  /**
   * Mark all ready items as served
   */
  async markAllServed(orderId: number): Promise<OrderResponse> {
    return api.post<OrderResponse>(`/waiter/orders/${orderId}/serve-all`);
  },

  /**
   * Pay order
   */
  async payOrder(orderId: number, data: PayOrderRequest): Promise<ApiResponse<PayOrderResponse>> {
    return api.post<ApiResponse<PayOrderResponse>>(`/waiter/orders/${orderId}/pay`, data);
  },

  /**
   * Cancel order
   */
  async cancelOrder(orderId: number, data?: CancelOrderRequest): Promise<OrderResponse> {
    return api.post<OrderResponse>(`/waiter/orders/${orderId}/cancel`, data);
  },

  /**
   * Apply discount to order
   */
  async applyDiscount(
    orderId: number,
    discountPercent: number,
    reason?: string
  ): Promise<OrderResponse> {
    return api.post<OrderResponse>(`/waiter/orders/${orderId}/discount`, {
      discount_percent: discountPercent,
      reason,
    });
  },

  /**
   * Add comment to order
   */
  async addComment(orderId: number, comment: string): Promise<OrderResponse> {
    return api.put<OrderResponse>(`/waiter/orders/${orderId}`, { comment });
  },

  /**
   * Print pre-check
   */
  async printPrecheck(orderId: number): Promise<ApiResponse<void>> {
    return api.post<ApiResponse<void>>(`/waiter/orders/${orderId}/print-precheck`);
  },

  /**
   * Print receipt
   */
  async printReceipt(orderId: number): Promise<ApiResponse<void>> {
    return api.post<ApiResponse<void>>(`/waiter/orders/${orderId}/print-receipt`);
  },

  // === Order Statistics ===

  /**
   * Get today's statistics for waiter
   */
  async getMyStats(): Promise<ApiResponse<{
    orders_count: number;
    total_sales: number;
    avg_check: number;
    tips: number;
  }>> {
    return api.get('/waiter/stats/today');
  },
};
