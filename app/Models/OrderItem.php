<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'dish_id',
        'name',
        'quantity',
        'price',
        'original_price',
        'modifiers_price',
        'discount',
        'total',
        'guest_number',
        'guest_id',
        'is_paid',
        'is_gift',
        'modifiers',
        'status',
        'comment',
        'cooking_started_at',
        'cooking_finished_at',
        'served_at',
        'sent_at',
        'station',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'is_write_off',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'modifiers_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'guest_number' => 'integer',
        'is_paid' => 'boolean',
        'is_gift' => 'boolean',
        'modifiers' => 'array',
        'cooking_started_at' => 'datetime',
        'cooking_finished_at' => 'datetime',
        'served_at' => 'datetime',
        'sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_write_off' => 'boolean',
    ];

    // Статусы позиции
    const STATUS_NEW = 'new';
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_COOKING = 'cooking';
    const STATUS_READY = 'ready';
    const STATUS_SERVED = 'served';
    const STATUS_CANCELLED = 'cancelled';  // отменено ДО начала готовки
    const STATUS_VOIDED = 'voided';        // отменено ПОСЛЕ начала готовки (списание)

    // ===== RELATIONSHIPS =====

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(OrderItemCancellation::class);
    }

    // ===== SCOPES =====

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCooking($query)
    {
        return $query->where('status', self::STATUS_COOKING);
    }

    public function scopeReady($query)
    {
        return $query->where('status', self::STATUS_READY);
    }

    public function scopeForStation($query, string $station)
    {
        return $query->where('station', $station);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_VOIDED]);
    }

    public function scopeCancelled($query)
    {
        return $query->whereIn('status', [self::STATUS_CANCELLED, self::STATUS_VOIDED]);
    }

    // ===== STATUS TRANSITIONS =====

    public function startCooking(): void
    {
        $this->update([
            'status' => self::STATUS_COOKING,
            'cooking_started_at' => now(),
        ]);
    }

    public function markReady(): void
    {
        $this->update([
            'status' => self::STATUS_READY,
            'cooking_finished_at' => now(),
        ]);
    }

    public function markServed(): void
    {
        $this->update([
            'status' => self::STATUS_SERVED,
            'served_at' => now(),
        ]);
    }

    /**
     * Отменить позицию
     */
    public function cancel(int $userId, string $reason = null): void
    {
        // Определяем статус отмены: cancelled или voided
        $newStatus = in_array($this->status, [self::STATUS_COOKING, self::STATUS_READY, self::STATUS_SERVED])
            ? self::STATUS_VOIDED    // готовится/готово - списание
            : self::STATUS_CANCELLED; // ещё не готовится - просто отмена

        $this->update([
            'status' => $newStatus,
            'cancelled_at' => now(),
            'cancelled_by' => $userId,
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Проверить можно ли отменить без подтверждения менеджера
     */
    public function canCancelWithoutApproval(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_PENDING]);
    }

    /**
     * Проверить нужно ли уведомить кухню об отмене
     */
    public function needsKitchenNotification(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_COOKING, self::STATUS_READY]);
    }

    // ===== HELPERS =====

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_NEW => 'Новый',
            self::STATUS_PENDING => 'Ожидает',
            self::STATUS_SENT => 'Отправлено',
            self::STATUS_COOKING => 'Готовится',
            self::STATUS_READY => 'Готово',
            self::STATUS_SERVED => 'Подано',
            self::STATUS_CANCELLED => 'Отменено',
            self::STATUS_VOIDED => 'Списано',
            default => $this->status,
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_NEW => '#9CA3AF',
            self::STATUS_PENDING => '#6B7280',
            self::STATUS_SENT => '#8B5CF6',
            self::STATUS_COOKING => '#F59E0B',
            self::STATUS_READY => '#10B981',
            self::STATUS_SERVED => '#3B82F6',
            self::STATUS_CANCELLED => '#EF4444',
            self::STATUS_VOIDED => '#DC2626',
            default => '#6B7280',
        };
    }

    public function getCookingTime(): ?int
    {
        if (!$this->cooking_started_at) {
            return null;
        }
        $end = $this->cooking_finished_at ?? now();
        return $this->cooking_started_at->diffInMinutes($end);
    }

    public function getModifiersText(): string
    {
        if (empty($this->modifiers)) {
            return '';
        }
        return collect($this->modifiers)->pluck('name')->implode(', ');
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($item) {
            // Пересчитать итого заказа при изменении позиции
            $item->order->recalculateTotal();
        });

        static::deleted(function ($item) {
            $item->order->recalculateTotal();
        });
    }
}
