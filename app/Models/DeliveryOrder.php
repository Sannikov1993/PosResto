<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Модель заказа на доставку
 */
class DeliveryOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number', 'type', 'status',
        'customer_id', 'customer_name', 'customer_phone', 'customer_comment',
        'address_street', 'address_house', 'address_apartment',
        'address_entrance', 'address_floor', 'address_intercom', 'address_comment',
        'delivery_zone_id', 'deliver_at',
        'courier_id', 'courier_assigned_at',
        'payment_method', 'change_from', 'is_paid',
        'subtotal', 'delivery_cost', 'discount', 'total',
        'created_by', 'updated_by', 'internal_comment',
        'cooking_started_at', 'ready_at', 'picked_up_at', 'delivered_at',
    ];

    protected $casts = [
        'deliver_at' => 'datetime',
        'cooking_started_at' => 'datetime',
        'ready_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'courier_assigned_at' => 'datetime',
        'is_paid' => 'boolean',
        'subtotal' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'change_from' => 'decimal:2',
    ];

    protected $appends = ['full_address', 'time_remaining', 'status_label', 'status_color'];

    // Статусы
    const STATUS_NEW = 'new';
    const STATUS_COOKING = 'cooking';
    const STATUS_READY = 'ready';
    const STATUS_DELIVERING = 'delivering';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    const STATUSES = [
        self::STATUS_NEW => ['label' => 'Новый', 'color' => 'blue'],
        self::STATUS_COOKING => ['label' => 'Готовится', 'color' => 'yellow'],
        self::STATUS_READY => ['label' => 'Готов', 'color' => 'green'],
        self::STATUS_DELIVERING => ['label' => 'В пути', 'color' => 'purple'],
        self::STATUS_COMPLETED => ['label' => 'Доставлен', 'color' => 'gray'],
        self::STATUS_CANCELLED => ['label' => 'Отменён', 'color' => 'red'],
    ];

    /**
     * Генерация номера заказа
     */
    public static function generateOrderNumber(): string
    {
        $date = now()->format('dmy');
        $lastOrder = self::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastOrder ? (int) substr($lastOrder->order_number, -3) + 1 : 1;

        return sprintf('#%s-%03d', $date, $sequence);
    }

    // ==================== СВЯЗИ ====================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(DeliveryOrderHistory::class)->orderBy('created_at', 'desc');
    }

    // ==================== АКСЕССОРЫ ====================

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_street,
            $this->address_house ? 'д. ' . $this->address_house : null,
            $this->address_apartment ? 'кв. ' . $this->address_apartment : null,
        ]);
        return implode(', ', $parts);
    }

    public function getTimeRemainingAttribute(): ?int
    {
        if (!$this->deliver_at || in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            return null;
        }
        return now()->diffInMinutes($this->deliver_at, false);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'gray';
    }

    // ==================== МЕТОДЫ ИЗМЕНЕНИЯ СТАТУСА ====================

    public function markAsCooking(): void
    {
        $this->update([
            'status' => self::STATUS_COOKING,
            'cooking_started_at' => now(),
        ]);
        $this->logHistory('status_changed', 'new', 'cooking');
    }

    public function markAsReady(): void
    {
        $this->update([
            'status' => self::STATUS_READY,
            'ready_at' => now(),
        ]);
        $this->logHistory('status_changed', 'cooking', 'ready');
    }

    public function assignCourier(Courier $courier): void
    {
        $oldCourier = $this->courier;

        $this->update([
            'courier_id' => $courier->id,
            'courier_assigned_at' => now(),
            'status' => self::STATUS_DELIVERING,
            'picked_up_at' => now(),
        ]);

        $this->logHistory('courier_assigned', $oldCourier?->name, $courier->name);

        // Обновить статус курьера
        $courier->updateStatus('busy');
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'delivered_at' => now(),
            'is_paid' => true,
        ]);
        $this->logHistory('status_changed', 'delivering', 'completed');

        // Освободить курьера
        if ($this->courier) {
            $this->courier->updateStatus('available');
        }
    }

    public function cancel(string $reason = null): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
        $this->logHistory('cancelled', null, $reason);

        if ($this->courier) {
            $this->courier->updateStatus('available');
        }
    }

    public function logHistory(string $action, ?string $oldValue, ?string $newValue): void
    {
        $this->history()->create([
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'user_id' => auth()->id() ?? 1,
        ]);
    }

    // ==================== СКОУПЫ ====================

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
