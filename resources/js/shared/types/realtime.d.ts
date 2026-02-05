/**
 * TypeScript type definitions for the real-time system
 *
 * @module shared/types/realtime
 */

// ═══════════════════════════════════════════════════════════════
// EVENT TYPES
// ═══════════════════════════════════════════════════════════════

/** Order events */
export interface NewOrderEvent {
    order_id: number;
    order_number: string;
    table_id?: number;
    table_number?: string;
    order_type: 'dine_in' | 'takeaway' | 'delivery';
    total: number;
    items_count: number;
    created_at: string;
}

export interface OrderStatusEvent {
    order_id: number;
    order_number: string;
    status: 'new' | 'preparing' | 'ready' | 'served' | 'completed' | 'cancelled';
    previous_status?: string;
    updated_at: string;
}

export interface OrderPaidEvent {
    order_id: number;
    order_number: string;
    payment_method: string;
    amount: number;
    paid_at: string;
}

export interface OrderCancelledEvent {
    order_id: number;
    order_number: string;
    reason?: string;
    cancelled_by?: string;
    cancelled_at: string;
}

export interface OrderUpdatedEvent {
    order_id: number;
    order_number: string;
    changes: Record<string, any>;
    updated_at: string;
}

export interface OrderTransferredEvent {
    order_id: number;
    order_number: string;
    from_table_id: number;
    to_table_id: number;
    transferred_at: string;
}

export interface CancellationRequestedEvent {
    order_id: number;
    order_number: string;
    requested_by: string;
    reason: string;
    requested_at: string;
}

export interface ItemCancellationRequestedEvent {
    order_id: number;
    order_number: string;
    item_id: number;
    item_name: string;
    quantity: number;
    requested_by: string;
    reason: string;
    requested_at: string;
}

/** Kitchen events */
export interface KitchenNewEvent {
    order_id: number;
    order_number: string;
    table_number?: string;
    items: Array<{
        id: number;
        name: string;
        quantity: number;
        modifiers?: string[];
        notes?: string;
    }>;
    priority: 'normal' | 'high' | 'urgent';
    created_at: string;
}

export interface KitchenReadyEvent {
    order_id: number;
    order_number: string;
    table_number?: string;
    item_id?: number;
    item_name?: string;
    ready_at: string;
}

export interface ItemCancelledEvent {
    order_id: number;
    order_number: string;
    item_id: number;
    item_name: string;
    quantity: number;
    reason?: string;
    cancelled_at: string;
}

/** Delivery events */
export interface DeliveryNewEvent {
    order_id: number;
    order_number: string;
    address: string;
    customer_name: string;
    customer_phone: string;
    delivery_time?: string;
    total: number;
    created_at: string;
}

export interface DeliveryStatusEvent {
    order_id: number;
    order_number: string;
    status: 'pending' | 'assigned' | 'picked_up' | 'on_way' | 'delivered' | 'cancelled';
    courier_id?: number;
    courier_name?: string;
    updated_at: string;
}

export interface CourierAssignedEvent {
    order_id: number;
    order_number: string;
    courier_id: number;
    courier_name: string;
    assigned_at: string;
}

export interface DeliveryProblemCreatedEvent {
    order_id: number;
    order_number: string;
    problem_type: string;
    description: string;
    created_at: string;
}

export interface DeliveryProblemResolvedEvent {
    order_id: number;
    order_number: string;
    resolution: string;
    resolved_at: string;
}

/** Table events */
export interface TableStatusEvent {
    table_id: number;
    table_number: string;
    status: 'free' | 'occupied' | 'reserved' | 'bill_requested';
    order_id?: number;
    updated_at: string;
}

/** Reservation events */
export interface ReservationNewEvent {
    reservation_id: number;
    table_id: number;
    table_number: string;
    customer_name: string;
    customer_phone: string;
    guest_count: number;
    reservation_date: string;
    reservation_time: string;
    notes?: string;
    created_at: string;
}

export interface ReservationConfirmedEvent {
    reservation_id: number;
    confirmed_at: string;
}

export interface ReservationCancelledEvent {
    reservation_id: number;
    reason?: string;
    cancelled_at: string;
}

export interface ReservationSeatedEvent {
    reservation_id: number;
    table_id: number;
    order_id?: number;
    seated_at: string;
}

export interface DepositPaidEvent {
    reservation_id: number;
    amount: number;
    payment_method: string;
    paid_at: string;
}

export interface DepositRefundedEvent {
    reservation_id: number;
    amount: number;
    refunded_at: string;
}

export interface PrepaymentReceivedEvent {
    reservation_id: number;
    amount: number;
    payment_method: string;
    received_at: string;
}

/** Bar events */
export interface BarOrderCreatedEvent {
    bar_order_id: number;
    order_id: number;
    items: Array<{
        id: number;
        name: string;
        quantity: number;
    }>;
    created_at: string;
}

export interface BarOrderUpdatedEvent {
    bar_order_id: number;
    status: string;
    updated_at: string;
}

export interface BarOrderCompletedEvent {
    bar_order_id: number;
    order_id: number;
    completed_at: string;
}

/** Cash events */
export interface CashOperationCreatedEvent {
    operation_id: number;
    type: 'income' | 'expense' | 'withdrawal' | 'deposit';
    amount: number;
    description: string;
    created_at: string;
}

export interface ShiftOpenedEvent {
    shift_id: number;
    cashier_id: number;
    cashier_name: string;
    opening_balance: number;
    opened_at: string;
}

export interface ShiftClosedEvent {
    shift_id: number;
    cashier_id: number;
    closing_balance: number;
    total_sales: number;
    closed_at: string;
}

/** Global events */
export interface StopListChangedEvent {
    items: Array<{
        id: number;
        name: string;
        reason?: string;
    }>;
    changed_at: string;
}

export interface SettingsChangedEvent {
    settings: Record<string, any>;
    changed_at: string;
}

// ═══════════════════════════════════════════════════════════════
// CONNECTION TYPES
// ═══════════════════════════════════════════════════════════════

export interface ConnectionEstablishedEvent {
    restaurantId: number;
}

export interface ConnectionLostEvent {
    restaurantId: number;
}

// ═══════════════════════════════════════════════════════════════
// STORE TYPES
// ═══════════════════════════════════════════════════════════════

export interface EventLogEntry {
    id: string;
    event: string;
    data: any;
    timestamp: number;
}

export interface PendingOptimisticEntry {
    snapshot: any;
    startedAt: number;
}

export interface RealtimeStoreState {
    restaurantId: number | null;
    connected: boolean;
    connecting: boolean;
    latency: number;
    reconnectCount: number;
}

export interface EventLogFilter {
    event?: string;
    since?: number;
    limit?: number;
}

export interface DebugInfo {
    restaurantId: number | null;
    connected: boolean;
    connecting: boolean;
    latency: number;
    reconnectCount: number;
    queueSize: number;
    pendingOptimisticCount: number;
    subscriptions: Record<string, number>;
    eventLogSize: number;
}

// ═══════════════════════════════════════════════════════════════
// COMPOSABLE TYPES
// ═══════════════════════════════════════════════════════════════

export interface UseRealtimeEventsOptions {
    debounce?: number;
}

export interface OptimisticUpdateOptions<T = any, R = any> {
    snapshot: T;
    optimisticUpdate: () => void;
    serverAction: () => Promise<R>;
    rollback: (snapshot: T) => void;
    onSuccess?: (result: R) => void;
    onError?: (error: Error) => void;
}

// ═══════════════════════════════════════════════════════════════
// EVENT MAP (for typed subscriptions)
// ═══════════════════════════════════════════════════════════════

export interface RealtimeEventMap {
    // Orders
    'new_order': NewOrderEvent;
    'order_status': OrderStatusEvent;
    'order_paid': OrderPaidEvent;
    'order_cancelled': OrderCancelledEvent;
    'order_updated': OrderUpdatedEvent;
    'order_transferred': OrderTransferredEvent;
    'cancellation_requested': CancellationRequestedEvent;
    'item_cancellation_requested': ItemCancellationRequestedEvent;

    // Kitchen
    'kitchen_new': KitchenNewEvent;
    'kitchen_ready': KitchenReadyEvent;
    'item_cancelled': ItemCancelledEvent;

    // Delivery
    'delivery_new': DeliveryNewEvent;
    'delivery_status': DeliveryStatusEvent;
    'courier_assigned': CourierAssignedEvent;
    'delivery_problem_created': DeliveryProblemCreatedEvent;
    'delivery_problem_resolved': DeliveryProblemResolvedEvent;

    // Tables
    'table_status': TableStatusEvent;

    // Reservations
    'reservation_new': ReservationNewEvent;
    'reservation_confirmed': ReservationConfirmedEvent;
    'reservation_cancelled': ReservationCancelledEvent;
    'reservation_seated': ReservationSeatedEvent;
    'deposit_paid': DepositPaidEvent;
    'deposit_refunded': DepositRefundedEvent;
    'prepayment_received': PrepaymentReceivedEvent;

    // Bar
    'bar_order_created': BarOrderCreatedEvent;
    'bar_order_updated': BarOrderUpdatedEvent;
    'bar_order_completed': BarOrderCompletedEvent;

    // Cash
    'cash_operation_created': CashOperationCreatedEvent;
    'shift_opened': ShiftOpenedEvent;
    'shift_closed': ShiftClosedEvent;

    // Global
    'stop_list_changed': StopListChangedEvent;
    'settings_changed': SettingsChangedEvent;

    // Connection
    'connection:established': ConnectionEstablishedEvent;
    'connection:lost': ConnectionLostEvent;

    // Wildcard
    '*': { event: string; data: any };
}
