/**
 * Waiter App - Types Index
 * Re-export all types for convenient imports
 */

// Models
export type {
  // User & Auth
  User,
  UserRole,
  Restaurant,
  // Hall & Tables
  Zone,
  Table,
  TableStatus,
  // Orders
  Order,
  OrderStatus,
  PaymentMethod,
  OrderSource,
  OrderItem,
  OrderItemStatus,
  OrderItemModifier,
  // Menu
  Category,
  Dish,
  ModifierGroup,
  Modifier,
  // Customers
  Customer,
  // Shifts
  Shift,
  ShiftStatus,
  WorkSession,
  // Notifications
  Notification,
  NotificationType,
} from './models';

// API Types
export type {
  // Base
  ApiResponse,
  ApiErrorResponse,
  PaginatedResponse,
  PaginationMeta,
  // Auth
  LoginByPinRequest,
  LoginByEmailRequest,
  LoginRequest,
  LoginResponse,
  MeResponse,
  // Zones
  ZonesResponse,
  ZoneResponse,
  // Tables
  TablesResponse,
  TableResponse,
  OpenTableRequest,
  OpenTableResponse,
  // Orders
  OrdersResponse,
  OrderResponse,
  GetOrdersParams,
  CreateOrderRequest,
  AddOrderItemRequest,
  UpdateOrderItemRequest,
  PayOrderRequest,
  PayOrderResponse,
  CancelOrderRequest,
  // Menu
  CategoriesResponse,
  CategoryResponse,
  DishesResponse,
  DishResponse,
  GetDishesParams,
  // Customers
  CustomersResponse,
  CustomerResponse,
  SearchCustomersParams,
  CreateCustomerRequest,
  // Shift
  ShiftResponse,
  StartShiftRequest,
  EndShiftRequest,
  // Work Session
  WorkSessionResponse,
  ClockInRequest,
  // Notifications
  NotificationsResponse,
  NotificationResponse,
  GetNotificationsParams,
  // Utility
  ApiMethod,
  RequestConfig,
  ApiError,
} from './api';
