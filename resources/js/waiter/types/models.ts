/**
 * Waiter App - Data Models
 * TypeScript interfaces for all domain entities
 */

// === User & Auth ===

export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  avatar_url?: string;
  restaurant_id: number;
  phone?: string;
  position?: string;
  created_at: string;
}

export type UserRole = 'admin' | 'manager' | 'waiter' | 'cashier' | 'cook' | 'courier';

export interface Restaurant {
  id: number;
  name: string;
  address?: string;
  phone?: string;
  timezone: string;
  currency: string;
}

// === Hall & Tables ===

export interface Zone {
  id: number;
  name: string;
  color?: string;
  sort_order: number;
  tables_count: number;
  is_active: boolean;
}

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
  shape?: 'rectangle' | 'circle' | 'square';
}

export type TableStatus = 'free' | 'occupied' | 'reserved' | 'bill_requested';

// === Orders ===

export interface Order {
  id: number;
  table_id: number;
  table?: Table;
  user_id: number;
  user?: User;
  customer_id?: number;
  customer?: Customer;
  status: OrderStatus;
  items: OrderItem[];
  subtotal: number;
  discount: number;
  discount_percent?: number;
  discount_reason?: string;
  total: number;
  guests_count: number;
  comment?: string;
  created_at: string;
  updated_at: string;
  paid_at?: string;
  payment_method?: PaymentMethod;
  source: OrderSource;
}

export type OrderStatus =
  | 'new'
  | 'pending'
  | 'cooking'
  | 'ready'
  | 'served'
  | 'paid'
  | 'cancelled';

export type PaymentMethod = 'cash' | 'card' | 'mixed' | 'online';

export type OrderSource = 'pos' | 'waiter' | 'delivery' | 'qr';

export interface OrderItem {
  id: number;
  order_id: number;
  dish_id: number;
  dish?: Dish;
  dish_name: string;
  name?: string;
  quantity: number;
  price: number;
  total: number;
  status: OrderItemStatus;
  comment?: string;
  modifiers?: OrderItemModifier[];
  sent_at?: string;
  ready_at?: string;
  served_at?: string;
}

export type OrderItemStatus =
  | 'new'
  | 'pending'
  | 'cooking'
  | 'ready'
  | 'served'
  | 'cancelled';

export interface OrderItemModifier {
  id: number;
  modifier_id: number;
  name: string;
  price: number;
}

// === Menu ===

export interface Category {
  id: number;
  name: string;
  parent_id?: number;
  image_url?: string;
  icon?: string;
  sort_order: number;
  is_active: boolean;
  dishes_count: number;
  children?: Category[];
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

// === Customers ===

export interface Customer {
  id: number;
  name: string;
  phone: string;
  email?: string;
  birthday?: string;
  bonus_balance: number;
  total_orders: number;
  total_spent: number;
  discount_percent?: number;
  notes?: string;
  is_blocked: boolean;
  created_at: string;
}

// === Shifts & Attendance ===

export interface Shift {
  id: number;
  user_id: number;
  restaurant_id: number;
  started_at: string;
  opened_at?: string;
  ended_at?: string;
  initial_cash: number;
  final_cash?: number;
  status: ShiftStatus;
  orders_count: number;
  total_sales: number;
}

export type ShiftStatus = 'open' | 'closed';

export interface WorkSession {
  id: number;
  user_id: number;
  started_at: string;
  ended_at?: string;
  duration_minutes?: number;
  status: 'active' | 'ended';
}

// === Notifications ===

export interface Notification {
  id: number;
  type: NotificationType;
  title: string;
  message: string;
  data?: Record<string, any>;
  read_at?: string;
  created_at: string;
}

export type NotificationType =
  | 'order_ready'
  | 'order_cancelled'
  | 'table_called'
  | 'bill_requested'
  | 'shift_reminder'
  | 'system';
