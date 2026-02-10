/**
 * Kitchen Module Type Definitions
 *
 * TypeScript interfaces for the kitchen display system.
 *
 * @module kitchen/types
 */

export interface KitchenStation {
    id: number;
    name: string;
    slug: string;
    icon?: string;
    notification_sound?: string;
    is_active: boolean;
    sort_order: number;
}

export interface KitchenDevice {
    id: string;
    name?: string;
    status: string;
    kitchen_station?: KitchenStation;
    last_seen_at?: string;
    settings?: Record<string, unknown>;
    restaurant_id?: number;
    timezone?: string;
    device_id?: string;
}

export interface OrderModifier {
    id: number;
    name: string;
    option_name?: string;
    price: number;
    quantity: number;
}

export interface DishInfo {
    id?: number;
    name?: string;
    image?: string;
    description?: string;
    cooking_time?: number;
    weight?: number;
    calories?: number;
    proteins?: number;
    fats?: number;
    carbs?: number;
    is_spicy?: boolean;
    is_vegetarian?: boolean;
    is_vegan?: boolean;
    category?: {
        name: string;
    };
}

export interface OrderItem {
    id: number;
    order_id?: number;
    name: string;
    quantity: number;
    status: string;
    cooking_started_at?: string | null;
    ready_at?: string | null;
    comment?: string | null;
    notes?: string | null;
    guest_number?: number | null;
    modifiers?: OrderModifier[];
    dish?: DishInfo | null;
    done?: boolean;
}

export interface OrderTable {
    id: number;
    name?: string;
    number?: string | number;
}

export interface Order {
    id: number;
    order_number: string | number;
    type: string;
    status: string;
    created_at: string;
    updated_at: string;
    scheduled_at?: string | null;
    is_asap?: boolean | null;
    cooking_started_at?: string | null;
    ready_at?: string | null;
    items: OrderItem[];
    table?: OrderTable | null;
    comment?: string | null;
    notes?: string | null;
    customer?: Record<string, unknown> | null;
    waiter?: { id: number; name: string } | null;
}

export interface ProcessedOrder extends Order {
    cookingMinutes?: number;
    isWarning?: boolean;
    isCritical?: boolean;
    isAlert?: boolean;
}

export interface TimeSlot {
    key: string;
    label: string;
    orders: Order[];
    urgency: string;
}

export interface StopListItem {
    id: number;
    dish: {
        name: string;
        image?: string;
    };
    reason: string;
    resume_at?: string;
}

export interface CancellationData {
    item_name?: string;
    itemName?: string;
    quantity?: number;
    order_number?: string;
    orderNumber?: string;
    table_number?: string;
    tableNumber?: string;
    reason_label?: string;
    reasonLabel?: string;
    reason_type?: string;
    reason_comment?: string;
    reasonComment?: string;
}

export interface WaiterCallData {
    waiterName: string;
    orderNumber: string;
    tableName?: string;
}

export interface ApiResponse<T = unknown> {
    success: boolean;
    data?: T;
    message?: string;
    error_code?: string;
    status?: string;
}

export interface DeviceStatusResponse {
    success: boolean;
    data?: KitchenDevice;
    status: string;
}

export interface OrdersResponse {
    success: boolean;
    data: Order[];
}

export interface OrderCountsByDate {
    [date: string]: number;
}

export interface CalendarDay {
    day: number | string;
    date: string | null;
    count?: number;
    isToday?: boolean;
    isSelected?: boolean;
    isPast?: boolean;
}

export interface ParsedTime {
    date: string;
    hours: number;
    minutes: number;
    timeStr: string;
}

export interface NowInTimezone {
    year: number;
    month: number;
    day: number;
    hours: number;
    minutes: number;
}

export interface ErrorRecord {
    id: string;
    timestamp: number;
    context: string;
    severity: string;
    message: string;
    code: string | null;
    stack?: string;
    retryable: boolean;
}

export interface ErrorHandlerOptions {
    context?: string;
    silent?: boolean;
    rethrow?: boolean;
}

export interface RetryOptions {
    maxRetries?: number;
    delay?: number;
    context?: string;
}

export interface SoundConfigHarmonic {
    name: string;
    description: string;
    fundamental: number;
    harmonics: number[];
    duration: number;
    type: 'harmonic';
}

export interface SoundConfigSequence {
    name: string;
    description: string;
    notes: number[];
    noteDelay: number;
    duration: number;
    type: 'sequence';
}

export interface SoundConfigMulti {
    name: string;
    description: string;
    frequencies: number[];
    durations: number[];
    volumes: number[];
    type: 'multi';
}

export interface SoundConfigDouble {
    name: string;
    description: string;
    frequencies: number[];
    delays: number[];
    duration: number;
    type: 'double';
}

export interface SoundConfigRising {
    name: string;
    description: string;
    frequencies: number[];
    delay: number;
    duration: number;
    type: 'rising';
}

export interface SoundConfigGong {
    name: string;
    description: string;
    fundamental: number;
    harmonics: number[];
    duration: number;
    type: 'gong';
}

export interface SoundConfigUrgent {
    name: string;
    description: string;
    tones: { freq1: number; freq2: number; delay: number }[];
    duration: number;
    type: 'urgent';
}

export type SoundConfig =
    | SoundConfigHarmonic
    | SoundConfigSequence
    | SoundConfigMulti
    | SoundConfigDouble
    | SoundConfigRising
    | SoundConfigGong
    | SoundConfigUrgent;

export interface SoundOption {
    value: string;
    label: string;
    description: string;
}

export interface ValidationResult {
    valid: boolean;
    errors: string[];
}

export interface SchemaDefinition {
    type?: string;
    nullable?: boolean;
    required?: string[];
    properties?: Record<string, SchemaDefinition>;
    items?: SchemaDefinition;
}

export interface KitchenApiClientOptions {
    timeout?: number;
    maxRetries?: number;
    retryBaseDelay?: number;
    retryMaxDelay?: number;
    debug?: boolean;
}

export interface ExecuteOptions {
    maxRetries?: number;
    dedupeKey?: string;
}

export interface KitchenSettings {
    soundEnabled: boolean;
    compactMode: boolean;
    focusMode: boolean;
    singleColumnMode: boolean;
    activeColumn: string;
    autoResponsiveEnabled: boolean;
    layoutSource: 'auto' | 'manual';
}
