/**
 * POS-specific TypeScript Types
 *
 * Types that are specific to the POS terminal application,
 * extending the shared types.
 */

import type {
    Order,
    OrderItem,
    Table,
    Zone,
    CashShift,
    CashOperation,
    Customer,
    Category,
    Dish,
    Reservation,
    DeliveryOrder,
    Ingredient,
    Promotion,
    User,
    PaymentMethod,
    BarOrder,
    BarCounts,
    WriteOff,
    StopListItem,
    PriceList,
} from '@/shared/types';

// ============================================================
// POS SESSION & AUTH
// ============================================================

export interface PosSession {
    token: string;
    user: User;
    permissions: string[];
    limits: PosLimits;
    interfaceAccess: Record<string, boolean>;
    posModules: string[];
    backofficeModules: string[];
    restaurantId: number;
    restaurantName: string;
}

export interface PosLimits {
    max_discount_percent: number;
    max_refund_amount: number;
    max_cancel_amount: number;
}

export interface PosUser extends User {
    pin?: string;
    can_login_by_pin?: boolean;
}

// ============================================================
// FLOOR PLAN
// ============================================================

export interface FloorPlan {
    zones: Zone[];
    tables: TableWithStatus[];
}

export interface TableWithStatus extends Table {
    next_reservation?: Reservation | null;
    all_reservations?: Reservation[];
    reservations_count?: number;
    active_order?: Order | null;
    linked_order_total?: number;
    guests_count?: number;
}

export interface TableContextAction {
    id: string;
    label: string;
    icon?: string;
    disabled?: boolean;
    danger?: boolean;
}

// ============================================================
// SHIFT MANAGEMENT
// ============================================================

export interface ShiftReport {
    shift: CashShift;
    operations: CashOperation[];
    summary: ShiftSummary;
}

export interface ShiftSummary {
    total_cash: number;
    total_card: number;
    total_online: number;
    total_sales: number;
    orders_count: number;
    avg_check: number;
    deposits: number;
    withdrawals: number;
    refunds: number;
    expected_cash: number;
    actual_cash?: number;
    difference?: number;
}

export interface OpenShiftData {
    opening_cash: number;
}

export interface CloseShiftData {
    closing_amount: number;
    notes?: string;
}

// ============================================================
// PAYMENT
// ============================================================

export interface PaymentData {
    payment_method: PaymentMethod;
    amount_cash?: number;
    amount_card?: number;
    change?: number;
    customer_id?: number;
    bonus_amount?: number;
    discount_percent?: number;
    discount_reason?: string;
    tips?: number;
}

export interface SplitPaymentData {
    cash: number;
    card: number;
}

// ============================================================
// ORDER MANAGEMENT
// ============================================================

export interface CreateOrderData {
    type: 'dine_in' | 'delivery' | 'pickup';
    table_id?: number;
    items: CreateOrderItem[];
    customer_id?: number;
    customer_name?: string;
    notes?: string;
    phone?: string;
    delivery_address?: string;
    delivery_notes?: string;
    payment_method?: PaymentMethod;
    price_list_id?: number;
    promotion_id?: number;
    manual_discount_percent?: number;
    prepayment?: number;
    prepayment_method?: PaymentMethod;
}

export interface CreateOrderItem {
    dish_id: number;
    quantity: number;
    modifiers?: number[];
    notes?: string;
}

export interface OrderDiscount {
    type: 'percent' | 'fixed' | 'loyalty' | 'promotion';
    value: number;
    reason?: string;
    promotion_id?: number;
    promo_code?: string;
}

// ============================================================
// CUSTOMER MANAGEMENT
// ============================================================

export interface CustomerSearchResult extends Customer {
    highlight?: string;
}

export interface CustomerFormData {
    name: string;
    phone: string;
    email?: string;
    birthday?: string;
    notes?: string;
}

// ============================================================
// STOP LIST
// ============================================================

export interface StopListEntry extends StopListItem {
    added_at?: string;
    added_by?: string;
}

// ============================================================
// DELIVERY
// ============================================================

export interface DeliveryFilters {
    status?: string;
    courier_id?: number;
    date_from?: string;
    date_to?: string;
    search?: string;
}

export interface DeliveryStats {
    total: number;
    pending: number;
    in_progress: number;
    completed: number;
    cancelled: number;
    avg_delivery_time?: number;
}

// ============================================================
// BAR
// ============================================================

export interface BarPanelData {
    orders: BarOrder[];
    counts: BarCounts;
}

// ============================================================
// SIDEBAR / NAVIGATION
// ============================================================

export type PosTab =
    | 'cash'
    | 'orders'
    | 'delivery'
    | 'customers'
    | 'warehouse'
    | 'stoplist'
    | 'writeoffs'
    | 'settings';

// ============================================================
// UI STATE
// ============================================================

export interface ToastMessage {
    id: string;
    type: 'success' | 'error' | 'warning' | 'info';
    message: string;
    duration?: number;
}

export interface ConfirmDialogOptions {
    title: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    danger?: boolean;
}
