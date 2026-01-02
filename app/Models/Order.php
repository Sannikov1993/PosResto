<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'customer_id',
        'user_id',
        'table_id',
        'courier_id',
        'order_number',
        'daily_number',
        'type',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'discount_amount',
        'discount_reason',
        'delivery_fee',
        'tips',
        'total',
        'paid_amount',
        'change_amount',
        'persons',
        'comment',
        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',
        'delivery_time',
        'estimated_delivery_minutes',
        'confirmed_at',
        'cooking_started_at',
        'cooking_finished_at',
        'ready_at',
        'picked_up_at',
        'delivered_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
        'source',
        'external_id',
        'external_data',
        'is_printed',
        'printed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'tips' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
        'delivery_time' => 'datetime',
        'confirmed_at' => 'datetime',
        'cooking_started_at' => 'datetime',
        'cooking_finished_at' => 'datetime',
        'ready_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'printed_at' => 'datetime',
        'external_data' => 'array',
        'is_printed' => 'boolean',
        'persons' => 'integer',
        'estimated_delivery_minutes' => 'integer',
    ];

    // Типы заказов
    const TYPE_DINE_IN = 'dine_in';
    const TYPE_DELIVERY = 'delivery';
    const TYPE_PICKUP = 'pickup';
    const TYPE_AGGREGATOR = 'aggregator';

    // Статусы заказов
    const STATUS_NEW = 'new';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_COOKING = 'cooking';
    const STATUS_READY = 'ready';
    const STATUS_DELIVERING = 'delivering';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Статусы оплаты
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

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    // ===== SCOPES =====

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
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
        return $query->where('type', self::TYPE_DINE_IN);
    }

    public function scopeDelivery($query)
    {
        return $query->where('type', self::TYPE_DELIVERY);
    }

    public function scopePickup($query)
    {
        return $query->where('type', self::TYPE_PICKUP);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_PAID);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', self::PAYMENT_PENDING);
    }

    // ===== STATUS TRANSITIONS =====

    public function confirm(): bool
    {
        if ($this->status !== self::STATUS_NEW) {
            return false;
        }
        
        $this->update([
            'status' => self::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);
        
        $this->logStatus(self::STATUS_CONFIRMED);
        return true;
    }

    public function startCooking(): bool
    {
        if (!in_array($this->status, [self::STATUS_NEW, self::STATUS_CONFIRMED])) {
            return false;
        }
        
        $this->update([
            'status' => self::STATUS_COOKING,
            'cooking_started_at' => now(),
        ]);
        
        // Обновить статус стола
        if ($this->table) {
            $this->table->occupy();
        }
        
        $this->logStatus(self::STATUS_COOKING);
        return true;
    }

    public function markReady(): bool
    {
        if ($this->status !== self::STATUS_COOKING) {
            return false;
        }
        
        $this->update([
            'status' => self::STATUS_READY,
            'cooking_finished_at' => now(),
            'ready_at' => now(),
        ]);
        
        $this->logStatus(self::STATUS_READY);
        return true;
    }

    public function startDelivering(int $courierId = null): bool
    {
        if ($this->status !== self::STATUS_READY) {
            return false;
        }
        
        $this->update([
            'status' => self::STATUS_DELIVERING,
            'courier_id' => $courierId ?? $this->courier_id,
            'picked_up_at' => now(),
        ]);
        
        $this->logStatus(self::STATUS_DELIVERING);
        return true;
    }

    public function complete(): bool
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            return false;
        }
        
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'delivered_at' => $this->type === self::TYPE_DELIVERY ? now() : null,
        ]);
        
        // Освободить стол
        if ($this->table) {
            $this->table->free();
        }
        
        // Обновить статистику клиента
        if ($this->customer) {
            $this->customer->updateStats();
        }
        
        $this->logStatus(self::STATUS_COMPLETED);
        return true;
    }

    public function cancel(string $reason = null): bool
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            return false;
        }
        
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
        
        // Освободить стол
        if ($this->table) {
            $this->table->free();
        }
        
        $this->logStatus(self::STATUS_CANCELLED, $reason);
        return true;
    }

    // ===== PAYMENT =====

    public function markPaid(string $method = 'cash', float $amount = null): void
    {
        $this->update([
            'payment_status' => self::PAYMENT_PAID,
            'payment_method' => $method,
            'paid_amount' => $amount ?? $this->total,
        ]);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
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

    public function recalculateTotal(): void
    {
        $subtotal = $this->items()->sum('total');
        
        $this->update([
            'subtotal' => $subtotal,
            'total' => $subtotal - $this->discount_amount + $this->delivery_fee + $this->tips,
        ]);
    }

    // ===== HELPERS =====

    protected function logStatus(string $status, string $comment = null): void
    {
        $this->statusHistory()->create([
            'status' => $status,
            'comment' => $comment,
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_NEW => 'Новый',
            self::STATUS_CONFIRMED => 'Подтверждён',
            self::STATUS_COOKING => 'Готовится',
            self::STATUS_READY => 'Готов',
            self::STATUS_DELIVERING => 'Доставляется',
            self::STATUS_COMPLETED => 'Завершён',
            self::STATUS_CANCELLED => 'Отменён',
            default => $this->status,
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_NEW => '#3B82F6',      // Синий
            self::STATUS_CONFIRMED => '#8B5CF6', // Фиолетовый
            self::STATUS_COOKING => '#F59E0B',   // Оранжевый
            self::STATUS_READY => '#10B981',     // Зелёный
            self::STATUS_DELIVERING => '#06B6D4', // Голубой
            self::STATUS_COMPLETED => '#6B7280', // Серый
            self::STATUS_CANCELLED => '#EF4444', // Красный
            default => '#6B7280',
        };
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_DINE_IN => 'В зале',
            self::TYPE_DELIVERY => 'Доставка',
            self::TYPE_PICKUP => 'Самовывоз',
            self::TYPE_AGGREGATOR => 'Агрегатор',
            default => $this->type,
        };
    }

    public function getTypeIcon(): string
    {
        return match($this->type) {
            self::TYPE_DINE_IN => '🍽️',
            self::TYPE_DELIVERY => '🛵',
            self::TYPE_PICKUP => '🏃',
            self::TYPE_AGGREGATOR => '📱',
            default => '📋',
        };
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
        if (!$this->cooking_started_at || $this->status === self::STATUS_COMPLETED) {
            return false;
        }
        
        $maxTime = $this->items()->max('cooking_time') ?? 30;
        return $this->getElapsedCookingTime() > $maxTime;
    }

    // Генерация номера заказа
    public static function generateOrderNumber(int $restaurantId): string
    {
        $today = today();
        $count = self::where('restaurant_id', $restaurantId)
            ->whereDate('created_at', $today)
            ->count();
        
        return str_pad($count + 1, 3, '0', STR_PAD_LEFT);
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
