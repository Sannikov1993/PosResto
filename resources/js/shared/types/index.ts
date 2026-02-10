/**
 * Shared Types Index
 * Re-export all types for convenient imports
 *
 * Usage:
 *   import type { User, Order, ApiResponse } from '@/shared/types';
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
    TableShape,
    // Orders
    Order,
    OrderStatus,
    OrderType,
    PaymentMethod,
    PaymentStatus,
    OrderSource,
    OrderItem,
    OrderItemStatus,
    OrderItemModifier,
    // Menu
    Category,
    Dish,
    ModifierGroup,
    Modifier,
    PriceList,
    PriceListItem,
    StopListItem,
    // Customers & Loyalty
    Customer,
    LoyaltyLevel,
    Promotion,
    PromoCode,
    GiftCertificate,
    // Cash & Finance
    CashShift,
    ShiftStatus,
    CashOperation,
    CashOperationType,
    // Reservations
    Reservation,
    ReservationStatus,
    // Delivery
    DeliveryOrder,
    DeliveryStatus,
    DeliveryProblem,
    DeliveryZone,
    // Warehouse & Inventory
    Ingredient,
    Warehouse,
    InventoryMovement,
    Invoice,
    InvoiceItem,
    InventoryCheck,
    InventoryCheckItem,
    // Bar
    BarOrder,
    BarOrderStatus,
    BarOrderItem,
    BarStation,
    BarCounts,
    // Write-Offs
    WriteOff,
    // Notifications
    Notification,
    NotificationType,
    // Work Sessions
    WorkSession,
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
    // Utility
    ApiMethod,
    RequestConfig,
    ApiError,
} from './api';
