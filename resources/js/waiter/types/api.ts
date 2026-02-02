/**
 * Waiter App - API Types
 * TypeScript interfaces for API requests and responses
 */

import type {
  User,
  Restaurant,
  Zone,
  Table,
  Order,
  OrderItem,
  Category,
  Dish,
  Customer,
  Shift,
  WorkSession,
  Notification,
  PaymentMethod,
} from './models';

// === Base Response Types ===

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
}

export interface ApiErrorResponse {
  success: false;
  message: string;
  errors?: Record<string, string[]>;
  code?: string;
}

export interface PaginatedResponse<T> {
  success: boolean;
  data: T[];
  meta: PaginationMeta;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

// === Auth API ===

export interface LoginByPinRequest {
  pin: string;
  device_token: string;
}

export interface LoginByEmailRequest {
  email: string;
  password: string;
  device_token: string;
}

export type LoginRequest = LoginByPinRequest | LoginByEmailRequest;

export interface LoginResponse {
  user: User;
  token: string;
  restaurant: Restaurant;
  permissions: string[];
}

export interface MeResponse {
  user: User;
  restaurant: Restaurant;
  permissions: string[];
  shift?: Shift;
}

// === Zones API ===

export type ZonesResponse = ApiResponse<Zone[]>;
export type ZoneResponse = ApiResponse<Zone>;

// === Tables API ===

export type TablesResponse = ApiResponse<Table[]>;
export type TableResponse = ApiResponse<Table>;

export interface OpenTableRequest {
  guests_count: number;
  customer_id?: number;
}

export interface OpenTableResponse {
  table: Table;
  order: Order;
}

// === Orders API ===

export type OrdersResponse = ApiResponse<Order[]>;
export type OrderResponse = ApiResponse<Order>;

export interface GetOrdersParams {
  today?: boolean;
  status?: string;
  table_id?: number;
  from_date?: string;
  to_date?: string;
}

export interface CreateOrderRequest {
  table_id: number;
  guests_count: number;
  customer_id?: number;
  comment?: string;
}

export interface AddOrderItemRequest {
  dish_id: number;
  quantity: number;
  comment?: string;
  modifiers?: number[];
}

export interface UpdateOrderItemRequest {
  quantity?: number;
  comment?: string;
}

export interface PayOrderRequest {
  payment_method: PaymentMethod;
  amount_cash?: number;
  amount_card?: number;
  customer_id?: number;
  use_bonus?: number;
  discount_percent?: number;
  discount_reason?: string;
}

export interface PayOrderResponse {
  order: Order;
  receipt_url?: string;
  change?: number;
}

export interface CancelOrderRequest {
  reason?: string;
}

// === Menu API ===

export type CategoriesResponse = ApiResponse<Category[]>;
export type CategoryResponse = ApiResponse<Category>;

export type DishesResponse = ApiResponse<Dish[]>;
export type DishResponse = ApiResponse<Dish>;

export interface GetDishesParams {
  category_id?: number;
  search?: string;
  available_only?: boolean;
}

// === Customers API ===

export type CustomersResponse = ApiResponse<Customer[]>;
export type CustomerResponse = ApiResponse<Customer>;

export interface SearchCustomersParams {
  query: string;
  limit?: number;
}

export interface CreateCustomerRequest {
  name: string;
  phone: string;
  email?: string;
  birthday?: string;
  notes?: string;
}

// === Shift API ===

export type ShiftResponse = ApiResponse<Shift>;

export interface StartShiftRequest {
  initial_cash?: number;
}

export interface EndShiftRequest {
  final_cash: number;
  notes?: string;
}

// === Work Session API ===

export type WorkSessionResponse = ApiResponse<WorkSession>;

export interface ClockInRequest {
  restaurant_id?: number;
}

// === Notifications API ===

export type NotificationsResponse = ApiResponse<Notification[]>;
export type NotificationResponse = ApiResponse<Notification>;

export interface GetNotificationsParams {
  unread_only?: boolean;
  limit?: number;
}

// === Utility Types ===

export type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface RequestConfig {
  method?: ApiMethod;
  params?: Record<string, any>;
  data?: Record<string, any>;
  headers?: Record<string, string>;
  timeout?: number;
}

export interface ApiError {
  type: 'validation' | 'network' | 'auth' | 'server' | 'unknown';
  message: string;
  status?: number;
  errors?: Record<string, string[]>;
  originalError?: any;
}
