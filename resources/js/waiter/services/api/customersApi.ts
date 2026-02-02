/**
 * Waiter App - Customers API Service
 * Handles customer search and management API calls
 */

import { api } from './client';
import type {
  ApiResponse,
  CustomersResponse,
  CustomerResponse,
  SearchCustomersParams,
  CreateCustomerRequest,
  Customer,
} from '@/waiter/types';

export const customersApi = {
  /**
   * Search customers by phone or name
   */
  async search(params: SearchCustomersParams): Promise<CustomersResponse> {
    return api.get<CustomersResponse>('/customers/search', params);
  },

  /**
   * Search by phone number
   */
  async searchByPhone(phone: string): Promise<CustomersResponse> {
    return this.search({ query: phone, limit: 10 });
  },

  /**
   * Search by name
   */
  async searchByName(name: string): Promise<CustomersResponse> {
    return this.search({ query: name, limit: 10 });
  },

  /**
   * Get customer by ID
   */
  async getCustomer(customerId: number): Promise<CustomerResponse> {
    return api.get<CustomerResponse>(`/customers/${customerId}`);
  },

  /**
   * Create new customer
   */
  async createCustomer(data: CreateCustomerRequest): Promise<CustomerResponse> {
    return api.post<CustomerResponse>('/customers', data);
  },

  /**
   * Quick create (minimal info)
   */
  async quickCreate(name: string, phone: string): Promise<CustomerResponse> {
    return this.createCustomer({ name, phone });
  },

  /**
   * Update customer
   */
  async updateCustomer(customerId: number, data: Partial<CreateCustomerRequest>): Promise<CustomerResponse> {
    return api.put<CustomerResponse>(`/customers/${customerId}`, data);
  },

  /**
   * Get customer order history
   */
  async getOrderHistory(customerId: number): Promise<ApiResponse<{
    orders_count: number;
    total_spent: number;
    last_visit?: string;
    favorite_dishes: Array<{ id: number; name: string; count: number }>;
  }>> {
    return api.get(`/customers/${customerId}/history`);
  },

  /**
   * Get customer bonus balance
   */
  async getBonusBalance(customerId: number): Promise<ApiResponse<{
    balance: number;
    pending: number;
    total_earned: number;
    total_spent: number;
  }>> {
    return api.get(`/customers/${customerId}/bonus`);
  },

  /**
   * Apply bonus to order
   */
  async applyBonus(customerId: number, orderId: number, amount: number): Promise<ApiResponse<{
    applied: number;
    remaining: number;
  }>> {
    return api.post(`/customers/${customerId}/bonus/apply`, {
      order_id: orderId,
      amount,
    });
  },
};
