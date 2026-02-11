<?php

namespace App\Models;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderType;
use App\Domain\Order\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\DiscountCalculatorService;
use App\Models\Promotion;
use App\Traits\BelongsToRestaurant;

class Order extends Model
{
    use HasFactory;
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'price_list_id',
        'customer_id',
        'user_id',
        'table_id',
        'linked_table_ids',
        'reservation_id',
        'table_order_number',
        'courier_id',
        'order_number',
        'daily_number',
        'type',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'discount_amount',
        'discount_percent',
        'discount_max_amount',
        'discount_reason',
        'delivery_fee',
        'tips',
        'total',
        'paid_amount',
        'change_amount',
        'persons',
        'comment',
        'notes',
        'phone',
        'delivery_address',
        'delivery_notes',
        'delivery_status',
        'delivery_zone_id',
        'delivery_latitude',
        'delivery_longitude',
        'delivery_time',
        'scheduled_at',
        'is_asap',
        'estimated_delivery_minutes',
        'confirmed_at',
        'cooking_started_at',
        'cooking_finished_at',
        'ready_at',
        'picked_up_at',
        'delivered_at',
        'completed_at',
        'closed_at',
        'cancelled_at',
        'cancel_reason',
        'is_write_off',
        'write_off_amount',
        'cancelled_by',
        'pending_cancellation',
        'cancel_request_reason',
        'cancel_requested_by',
        'cancel_requested_at',
        'source',
        'external_id',
        'external_data',
        'is_printed',
        'printed_at',
        'paid_at',
        'prepayment',
        'prepayment_method',
        'deposit_used',
        // From reservation deposit
        'prepaid_amount',
        'prepaid_source',
        'prepaid_reservation_id',
        // Интеграция лояльности и склада
        'bonus_used',
        'pending_bonus_spend', // Бонусы выбранные для списания (до оплаты)
        'promo_code',
        'inventory_deducted',
        // Скидка уровня лояльности
        'loyalty_discount_amount',
        'loyalty_level_id',
        // Детальная информация о скидках
        'applied_discounts',
        // Разбиение оплаты по юрлицам
        'payment_split',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_max_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'tips' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'prepayment' => 'decimal:2',
        'prepaid_amount' => 'decimal:2',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
        'delivery_time' => 'datetime',
        'scheduled_at' => 'datetime',
        'is_asap' => 'boolean',
        'confirmed_at' => 'datetime',
        'cooking_started_at' => 'datetime',
        'cooking_finished_at' => 'datetime',
        'ready_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'closed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'printed_at' => 'datetime',
        'paid_at' => 'datetime',
        'external_data' => 'array',
        'is_printed' => 'boolean',
        'is_write_off' => 'boolean',
        'pending_cancellation' => 'boolean',
        'cancel_requested_at' => 'datetime',
        'persons' => 'integer',
        'estimated_delivery_minutes' => 'integer',
        'table_order_number' => 'integer',
        'linked_table_ids' => 'array',
        'bonus_used' => 'decimal:2',
        'pending_bonus_spend' => 'integer',
        'inventory_deducted' => 'boolean',
        'loyalty_discount_amount' => 'decimal:2',
        'applied_discounts' => 'array',
        'payment_split' => 'array',
    ];

    // Типы заказов (алиасы для обратной совместимости, source of truth — OrderType enum)
    const TYPE_DINE_IN = 'dine_in';
    const TYPE_DELIVERY = 'delivery';
    const TYPE_PICKUP = 'pickup';
    const TYPE_AGGREGATOR = 'aggregator';
    const TYPE_PREORDER = 'preorder';

    // Статусы заказов (алиасы для обратной совместимости, source of truth — OrderStatus enum)
    const STATUS_NEW = 'new';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_COOKING = 'cooking';
    const STATUS_READY = 'ready';
    const STATUS_SERVED = 'served';
    const STATUS_DELIVERING = 'delivering';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Статусы оплаты (алиасы для обратной совместимости, source of truth — PaymentStatus enum)
    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_PARTIAL = 'partial';
    const PAYMENT_REFUNDED = 'refunded';

    // ===== RELATIONSHIPS =====

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function loyaltyLevel(): BelongsTo
    {
        return $this->belongsTo(LoyaltyLevel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    // Alias for user - waiter who created the order
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Кто отменил заказ
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // Кто запросил отмену
    public function cancelRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancel_requested_by');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    // ===== SCOPES =====

    /**
     * Filter orders for a specific date in restaurant's timezone
     * Properly converts the date to UTC range for database comparison
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $date Date in YYYY-MM-DD format (in restaurant's timezone)
     * @param int $restaurantId Restaurant ID for timezone lookup
     * @param bool $includeActiveOrders Whether to include active orders regardless of date (for "today" view)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForDate($query, string $date, int $restaurantId, bool $includeActiveOrders = false)
    {
        // Get restaurant's timezone
        $tz = \App\Helpers\TimeHelper::getTimezone($restaurantId);

        // Parse date in restaurant's timezone and convert to UTC range
        $filterDate = \Carbon\Carbon::parse($date, $tz);
        $startOfDayUtc = $filterDate->copy()->startOfDay()->utc();
        $endOfDayUtc = $filterDate->copy()->endOfDay()->utc();

        // Check if requested date is today in restaurant's timezone
        $restaurantToday = \App\Helpers\TimeHelper::today($restaurantId);
        $isToday = $filterDate->format('Y-m-d') === $restaurantToday->format('Y-m-d');

        return $query->where(function ($q) use ($startOfDayUtc, $endOfDayUtc, $isToday, $includeActiveOrders) {
            // Orders scheduled for this date (preorders)
            $q->whereBetween('scheduled_at', [$startOfDayUtc, $endOfDayUtc]);

            // Orders without scheduled_at (regular orders), created on this date
            $q->orWhere(function ($sq) use ($startOfDayUtc, $endOfDayUtc) {
                $sq->whereNull('scheduled_at')
                   ->whereBetween('created_at', [$startOfDayUtc, $endOfDayUtc]);
            });

            // For today's view, also include all active orders regardless of creation date
            // This ensures orders being prepared right now are always visible
            if ($isToday && $includeActiveOrders) {
                $q->orWhere(function ($sq) {
                    $sq->whereNull('scheduled_at')
                       ->whereIn('status', [
                           OrderStatus::NEW->value,
                           OrderStatus::CONFIRMED->value,
                           OrderStatus::COOKING->value,
                           OrderStatus::READY->value,
                       ]);
                });
            }
        });
    }

    /**
     * Filter orders for today in restaurant's timezone
     * Uses TimeHelper to get the correct "today" based on restaurant settings
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|null $restaurantId Restaurant ID (uses default if not provided)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeToday($query, ?int $restaurantId = null)
    {
        $restaurantId = $restaurantId ?? 1;
        $today = \App\Helpers\TimeHelper::today($restaurantId)->format('Y-m-d');
        return $query->forDate($today, $restaurantId, true);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', OrderStatus::COMPLETED->value);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', OrderStatus::CANCELLED->value);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDineIn($query)
    {
        return $query->where('type', OrderType::DINE_IN->value);
    }

    public function scopeDelivery($query)
    {
        return $query->where('type', OrderType::DELIVERY->value);
    }

    public function scopePickup($query)
    {
        return $query->where('type', OrderType::PICKUP->value);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', PaymentStatus::PAID->value);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', PaymentStatus::PENDING->value);
    }

    // ===== STATUS TRANSITIONS =====

    public function confirm(): bool
    {
        if ($this->status !== OrderStatus::NEW->value) {
            return false;
        }

        $this->update([
            'status' => OrderStatus::CONFIRMED->value,
            'confirmed_at' => now(),
        ]);

        $this->logStatus(OrderStatus::CONFIRMED->value);
        return true;
    }

    public function startCooking(): bool
    {
        if (!in_array($this->status, [OrderStatus::NEW->value, OrderStatus::CONFIRMED->value])) {
            return false;
        }

        $this->update([
            'status' => OrderStatus::COOKING->value,
            'cooking_started_at' => now(),
        ]);

        // Обновить статус стола
        $table = $this->table_id ? $this->table()->first() : null;
        if ($table instanceof Table) {
            $table->occupy();
        }

        $this->logStatus(OrderStatus::COOKING->value);
        return true;
    }

    public function markReady(): bool
    {
        if ($this->status !== OrderStatus::COOKING->value) {
            return false;
        }

        $this->update([
            'status' => OrderStatus::READY->value,
            'cooking_finished_at' => now(),
            'ready_at' => now(),
        ]);

        $this->logStatus(OrderStatus::READY->value);
        return true;
    }

    public function markServed(): bool
    {
        if ($this->status !== OrderStatus::READY->value) {
            return false;
        }

        $this->update([
            'status' => OrderStatus::SERVED->value,
        ]);

        $this->logStatus(OrderStatus::SERVED->value);
        return true;
    }

    public function startDelivering(int $courierId = null): bool
    {
        if ($this->status !== OrderStatus::READY->value) {
            return false;
        }

        $this->update([
            'status' => OrderStatus::DELIVERING->value,
            'courier_id' => $courierId ?? $this->courier_id,
            'picked_up_at' => now(),
        ]);

        $this->logStatus(OrderStatus::DELIVERING->value);
        return true;
    }

    public function complete(): bool
    {
        if (in_array($this->status, [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value])) {
            return false;
        }

        $this->update([
            'status' => OrderStatus::COMPLETED->value,
            'completed_at' => now(),
            'delivered_at' => $this->type === OrderType::DELIVERY->value ? now() : null,
        ]);

        // Освободить стол
        $table = $this->table_id ? $this->table()->first() : null;
        if ($table instanceof Table) {
            $table->free();
        }

        // Обновить статистику клиента
        if ($this->customer) {
            $this->customer->updateStats();
        }

        $this->logStatus(OrderStatus::COMPLETED->value);
        return true;
    }

    public function cancel(string $reason = null): bool
    {
        if (in_array($this->status, [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value])) {
            return false;
        }

        $this->update([
            'status' => OrderStatus::CANCELLED->value,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        // Освободить стол (проверяем что это объект Table)
        $table = $this->table_id ? $this->table()->first() : null;
        if ($table instanceof Table) {
            $table->free();
        }

        $this->logStatus(OrderStatus::CANCELLED->value, $reason);
        return true;
    }

    // ===== PAYMENT =====

    public function markPaid(string $method = 'cash', float $amount = null): void
    {
        $this->update([
            'payment_status' => PaymentStatus::PAID->value,
            'payment_method' => $method,
            'paid_amount' => $amount ?? $this->total,
        ]);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::PAID->value;
    }

    public function getAmountDue(): float
    {
        return max(0, $this->total - $this->paid_amount);
    }

    // ===== ITEMS =====

    public function addItem(Dish $dish, int $quantity = 1, array $modifiers = [], string $comment = null): OrderItem
    {
        $modifiersPrice = collect($modifiers)->sum('price');
        $itemTotal = ($dish->price + $modifiersPrice) * $quantity;

        $item = $this->items()->create([
            'dish_id' => $dish->id,
            'name' => $dish->name,
            'quantity' => $quantity,
            'price' => $dish->price,
            'modifiers_price' => $modifiersPrice,
            'total' => $itemTotal,
            'modifiers' => $modifiers,
            'comment' => $comment,
        ]);

        $this->recalculateTotal();
        return $item;
    }

    /**
     * Применить автоматические акции к заказу
     */
    public function applyAutomaticPromotions(): void
    {
        app(DiscountCalculatorService::class, ['restaurantId' => $this->restaurant_id])
            ->applyAutomaticPromotions($this);
    }

    /**
     * Пересчитать итоги заказа (subtotal, discounts, total)
     */
    public function recalculateTotal(): void
    {
        app(DiscountCalculatorService::class, ['restaurantId' => $this->restaurant_id])
            ->recalculateOrderTotal($this);
    }

    // ===== HELPERS =====

    public function logStatus(string $status, string $comment = null): void
    {
        // Пропускаем логирование если модель OrderStatusHistory не существует
        if (!class_exists(\App\Models\OrderStatusHistory::class)) {
            return;
        }

        try {
            $this->statusHistory()->create([
                'status' => $status,
                'comment' => $comment,
                'user_id' => auth()->id(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Игнорируем ошибки логирования статуса
        }
    }

    public function getStatusLabel(): string
    {
        $status = OrderStatus::tryFrom($this->status);
        return $status?->label() ?? $this->status;
    }

    public function getStatusColor(): string
    {
        $status = OrderStatus::tryFrom($this->status);
        return $status?->color() ?? '#6B7280';
    }

    public function getTypeLabel(): string
    {
        $type = OrderType::tryFrom($this->type);
        return $type?->label() ?? $this->type;
    }

    public function getTypeIcon(): string
    {
        $type = OrderType::tryFrom($this->type);
        return $type?->icon() ?? 'clipboard';
    }

    public function getCookingTime(): ?int
    {
        if (!$this->cooking_started_at || !$this->cooking_finished_at) {
            return null;
        }
        return $this->cooking_started_at->diffInMinutes($this->cooking_finished_at);
    }

    public function getElapsedCookingTime(): ?int
    {
        if (!$this->cooking_started_at) {
            return null;
        }
        if ($this->cooking_finished_at) {
            return $this->cooking_started_at->diffInMinutes($this->cooking_finished_at);
        }
        return $this->cooking_started_at->diffInMinutes(now());
    }

    public function isLate(): bool
    {
        if (!$this->cooking_started_at || $this->status === OrderStatus::COMPLETED->value) {
            return false;
        }
        
        $maxTime = $this->items()->max('cooking_time') ?? 30;
        return $this->getElapsedCookingTime() > $maxTime;
    }

    /**
     * Генерация номера заказа — атомарный счётчик через Redis.
     * Исключает race condition (TOCTOU) при одновременном создании заказов.
     */
    public static function generateOrderNumber(int $restaurantId): string
    {
        $today = today()->format('Y-m-d');
        $cacheKey = "order_counter:{$restaurantId}:{$today}";

        try {
            // Атомарный инкремент через Redis
            $number = \Cache::increment($cacheKey);

            // TTL 48 часов (с запасом на следующий день)
            if ($number === 1) {
                \Cache::put($cacheKey, 1, now()->addHours(48));
            }
        } catch (\Throwable) {
            // Fallback если Redis недоступен — считаем из БД + random suffix
            $count = self::where('restaurant_id', $restaurantId)
                ->whereDate('created_at', $today)
                ->count();
            $number = $count + 1;
        }

        return str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    // Генерация следующего номера заказа для стола
    public static function getNextTableOrderNumber(int $tableId): int
    {
        $maxNumber = self::where('table_id', $tableId)
            ->active()
            ->max('table_order_number') ?? 0;

        return $maxNumber + 1;
    }

    // Получить все активные заказы для стола
    public static function getTableOrders(int $tableId)
    {
        return self::where('table_id', $tableId)
            ->active()
            ->orderBy('table_order_number')
            ->get();
    }

    // Boot метод для автогенерации номера
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber($order->restaurant_id);
            }
            if (empty($order->daily_number)) {
                $order->daily_number = '#' . $order->order_number;
            }
        });
    }
}
