<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'dish_id',
        'name',
        'quantity',
        'price',
        'modifiers_price',
        'discount',
        'total',
        'modifiers',
        'status',
        'comment',
        'cooking_started_at',
        'cooking_finished_at',
        'served_at',
        'station',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'modifiers_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'modifiers' => 'array',
        'cooking_started_at' => 'datetime',
        'cooking_finished_at' => 'datetime',
        'served_at' => 'datetime',
    ];

    // Статусы позиции
    const STATUS_PENDING = 'pending';
    const STATUS_COOKING = 'cooking';
    const STATUS_READY = 'ready';
    const STATUS_SERVED = 'served';

    // ===== RELATIONSHIPS =====

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
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

    // ===== HELPERS =====

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Ожидает',
            self::STATUS_COOKING => 'Готовится',
            self::STATUS_READY => 'Готово',
            self::STATUS_SERVED => 'Подано',
            default => $this->status,
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => '#6B7280',
            self::STATUS_COOKING => '#F59E0B',
            self::STATUS_READY => '#10B981',
            self::STATUS_SERVED => '#3B82F6',
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
