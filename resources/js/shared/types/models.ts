/**
 * Shared Data Models
 *
 * Base types used across all MenuLab modules.
 * Extends waiter/types/models.ts with additional domain entities.
 */

// ============================================================
// USER & AUTH
// ============================================================

export type UserRole =
    | 'super_admin'
    | 'owner'
    | 'manager'
    | 'cashier'
    | 'waiter'
    | 'chef'
    | 'courier'
    | 'hostess';

export interface User {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    avatar_url?: string;
    restaurant_id: number;
    tenant_id?: number;
    is_tenant_owner?: boolean;
    phone?: string;
    position?: string;
    pin?: string;
    is_active: boolean;
    created_at: string;
    updated_at?: string;
}

export interface Restaurant {
    id: number;
    name: string;
    address?: string;
    phone?: string;
    timezone: string;
    currency: string;
    tenant_id?: number;
    settings?: Record<string, unknown>;
}

// ============================================================
// HALL & TABLES
// ============================================================

export interface Zone {
    id: number;
    name: string;
    color?: string;
    sort_order: number;
    tables_count: number;
    is_active: boolean;
    restaurant_id: number;
}

export type TableStatus = 'free' | 'occupied' | 'reserved' | 'bill_requested';

export type TableShape = 'rectangle' | 'circle' | 'square';

export interface Table {
    id: number;
    number: string;
    zone_id: number;
    zone?: Zone;
    seats: number;
    status: TableStatus;
    current_order_id?: number;
    current_order?: Order;
    position_x?: number;
    position_y?: number;
    width?: number;
    height?: number;
    shape?: TableShape;
    is_active: boolean;
    restaurant_id: number;
}

// ============================================================
// ORDERS
// ============================================================

export type OrderStatus =
    | 'new'
    | 'confirmed'
    | 'cooking'
    | 'ready'
    | 'served'
    | 'paid'
    | 'completed'
    | 'cancelled';

export type PaymentMethod = 'cash' | 'card' | 'mixed' | 'online';

export type PaymentStatus = 'pending' | 'partial' | 'paid' | 'refunded';

export type OrderSource = 'pos' | 'waiter' | 'delivery' | 'qr' | 'table-order';

export type OrderType = 'dine_in' | 'delivery' | 'pickup';

export interface Order {
    id: number;
    order_number?: string;
    table_id?: number;
    table?: Table;
    user_id: number;
    user?: User;
    customer_id?: number;
    customer?: Customer;
    type: OrderType;
    status: OrderStatus;
    payment_status: PaymentStatus;
    items: OrderItem[];
    subtotal: number;
    discount: number;
    discount_percent?: number;
    discount_reason?: string;
    manual_discount_percent?: number;
    total: number;
    guests_count?: number;
    notes?: string;
    created_at: string;
    updated_at: string;
    paid_at?: string;
    payment_method?: PaymentMethod;
    source: OrderSource;
    linked_table_ids?: number[];
    promotion_id?: number;
    price_list_id?: number;
    prepayment?: number;
    prepayment_method?: PaymentMethod;
    delivery_address?: string;
    delivery_notes?: string;
    delivery_status?: DeliveryStatus;
    scheduled_at?: string;
    is_asap?: boolean;
    restaurant_id: number;
}

export type OrderItemStatus =
    | 'new'
    | 'pending'
    | 'cooking'
    | 'ready'
    | 'served'
    | 'cancelled';

export interface OrderItem {
    id: number;
    order_id: number;
    dish_id: number;
    dish?: Dish;
    dish_name: string;
    quantity: number;
    price: number;
    total: number;
    status: OrderItemStatus;
    comment?: string;
    notes?: string;
    modifiers?: OrderItemModifier[];
    sent_at?: string;
    ready_at?: string;
    served_at?: string;
}

export interface OrderItemModifier {
    id: number;
    modifier_id: number;
    name: string;
    price: number;
}

// ============================================================
// MENU
// ============================================================

export interface Category {
    id: number;
    name: string;
    parent_id?: number;
    image_url?: string;
    sort_order: number;
    is_active: boolean;
    dishes_count: number;
    children?: Category[];
    restaurant_id: number;
}

export interface Dish {
    id: number;
    name: string;
    description?: string;
    price: number;
    category_id: number;
    category?: Category;
    image_url?: string;
    is_available: boolean;
    in_stop_list: boolean;
    cooking_time?: number;
    weight?: number;
    calories?: number;
    sort_order: number;
    modifiers?: ModifierGroup[];
    restaurant_id: number;
}

export interface ModifierGroup {
    id: number;
    name: string;
    required: boolean;
    min_selections: number;
    max_selections: number;
    items: Modifier[];
}

export interface Modifier {
    id: number;
    name: string;
    price: number;
    is_default: boolean;
    is_available: boolean;
}

export interface PriceList {
    id: number;
    name: string;
    is_active: boolean;
    restaurant_id: number;
    prices?: PriceListItem[];
}

export interface PriceListItem {
    dish_id: number;
    price: number;
}

export interface StopListItem {
    dish_id: number;
    dish_name?: string;
    reason: string;
    resume_at?: string;
}

// ============================================================
// CUSTOMERS & LOYALTY
// ============================================================

export interface Customer {
    id: number;
    name: string;
    phone: string;
    email?: string;
    birthday?: string;
    bonus_balance: number;
    total_orders: number;
    orders_count?: number;
    total_spent: number;
    discount_percent?: number;
    notes?: string;
    is_blocked: boolean;
    is_new?: boolean;
    loyalty_level?: LoyaltyLevel;
    created_at: string;
    restaurant_id?: number;
}

export interface LoyaltyLevel {
    id: number;
    name: string;
    discount_percent: number;
    min_spent?: number;
    bonus_percent?: number;
}

export interface Promotion {
    id: number;
    name: string;
    description?: string;
    type: string;
    is_active: boolean;
    start_date?: string;
    end_date?: string;
    conditions?: Record<string, unknown>;
    restaurant_id: number;
}

export interface PromoCode {
    id: number;
    code: string;
    promotion_id?: number;
    discount_type: 'percent' | 'fixed';
    discount_value: number;
    max_uses?: number;
    used_count: number;
    is_active: boolean;
    expires_at?: string;
}

export interface GiftCertificate {
    id: number;
    code: string;
    amount: number;
    balance: number;
    customer_id?: number;
    is_active: boolean;
    expires_at?: string;
}

// ============================================================
// CASH & FINANCE
// ============================================================

export type ShiftStatus = 'open' | 'closed';

export interface CashShift {
    id: number;
    user_id: number;
    user?: User;
    restaurant_id: number;
    started_at: string;
    ended_at?: string;
    opening_cash: number;
    closing_amount?: number;
    status: ShiftStatus;
    orders_count: number;
    total_sales: number;
    notes?: string;
}

export type CashOperationType = 'income' | 'expense' | 'withdrawal' | 'deposit' | 'refund' | 'prepayment';

export interface CashOperation {
    id: number;
    shift_id: number;
    type: CashOperationType;
    amount: number;
    description?: string;
    category?: string;
    order_id?: number;
    order_number?: string;
    refund_method?: string;
    reason?: string;
    staff_id?: number;
    created_at: string;
    restaurant_id: number;
}

// ============================================================
// RESERVATIONS
// ============================================================

export type ReservationStatus =
    | 'pending'
    | 'confirmed'
    | 'seated'
    | 'completed'
    | 'cancelled'
    | 'no_show';

export interface Reservation {
    id: number;
    table_id: number;
    table?: Table;
    customer_id?: number;
    customer?: Customer;
    customer_name: string;
    customer_phone: string;
    guest_count: number;
    date: string;
    time_from: string;
    time_to?: string;
    status: ReservationStatus;
    notes?: string;
    deposit_amount?: number;
    deposit_paid?: boolean;
    deposit_method?: PaymentMethod;
    linked_table_ids?: number[];
    preorder_items?: OrderItem[];
    created_at: string;
    updated_at?: string;
    restaurant_id: number;
}

// ============================================================
// DELIVERY
// ============================================================

export type DeliveryStatus =
    | 'pending'
    | 'preparing'
    | 'ready'
    | 'assigned'
    | 'picked_up'
    | 'on_way'
    | 'delivered'
    | 'cancelled';

export interface DeliveryOrder {
    id: number;
    order_id: number;
    order?: Order;
    courier_id?: number;
    courier?: User;
    address: string;
    lat?: number;
    lng?: number;
    status: DeliveryStatus;
    estimated_delivery?: string;
    delivered_at?: string;
    problems?: DeliveryProblem[];
    restaurant_id: number;
}

export interface DeliveryProblem {
    id: number;
    delivery_order_id: number;
    type: string;
    description: string;
    resolution?: string;
    resolved_at?: string;
    created_at: string;
}

export interface DeliveryZone {
    id: number;
    name: string;
    min_order?: number;
    delivery_fee?: number;
    polygon?: Array<{ lat: number; lng: number }>;
    restaurant_id: number;
}

// ============================================================
// WAREHOUSE & INVENTORY
// ============================================================

export interface Ingredient {
    id: number;
    name: string;
    category_id?: number;
    unit_id?: number;
    unit_name?: string;
    current_stock?: number;
    min_stock?: number;
    cost_price?: number;
    restaurant_id: number;
}

export interface Warehouse {
    id: number;
    name: string;
    restaurant_id: number;
}

export interface InventoryMovement {
    id: number;
    ingredient_id: number;
    warehouse_id: number;
    type: 'in' | 'out' | 'adjustment' | 'transfer';
    quantity: number;
    reason?: string;
    created_at: string;
}

export interface Invoice {
    id: number;
    supplier_id: number;
    warehouse_id: number;
    status: string;
    total?: number;
    items?: InvoiceItem[];
    created_at: string;
    restaurant_id: number;
}

export interface InvoiceItem {
    ingredient_id: number;
    quantity: number;
    price: number;
}

export interface InventoryCheck {
    id: number;
    warehouse_id: number;
    status: string;
    items?: InventoryCheckItem[];
    created_at: string;
    restaurant_id: number;
}

export interface InventoryCheckItem {
    ingredient_id: number;
    expected_quantity: number;
    actual_quantity: number;
}

// ============================================================
// BAR
// ============================================================

export type BarOrderStatus = 'new' | 'in_progress' | 'ready' | 'served';

export interface BarOrder {
    id: number;
    order_id: number;
    order?: Order;
    item_id: number;
    status: BarOrderStatus;
    items?: BarOrderItem[];
    station?: BarStation | null;
    created_at: string;
}

export interface BarOrderItem {
    id: number;
    name: string;
    quantity: number;
}

export interface BarStation {
    id: number;
    name: string;
}

export interface BarCounts {
    new: number;
    in_progress: number;
    ready: number;
}

// ============================================================
// WRITE-OFFS
// ============================================================

export interface WriteOff {
    id: number;
    ingredient_id?: number;
    dish_id?: number;
    quantity: number;
    reason: string;
    user_id: number;
    created_at: string;
    restaurant_id: number;
}

// ============================================================
// NOTIFICATIONS
// ============================================================

export type NotificationType =
    | 'order_ready'
    | 'order_cancelled'
    | 'table_called'
    | 'bill_requested'
    | 'shift_reminder'
    | 'system';

export interface Notification {
    id: number;
    type: NotificationType;
    title: string;
    message: string;
    data?: Record<string, unknown>;
    read_at?: string;
    created_at: string;
}

// ============================================================
// WORK SESSIONS
// ============================================================

export interface WorkSession {
    id: number;
    user_id: number;
    started_at: string;
    ended_at?: string;
    duration_minutes?: number;
    status: 'active' | 'ended';
}
