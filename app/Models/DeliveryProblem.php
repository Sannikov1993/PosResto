<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель проблемы при доставке
 */
class DeliveryProblem extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'delivery_order_id',
        'courier_id',
        'type',
        'description',
        'photo_path',
        'latitude',
        'longitude',
        'status',
        'resolution',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'resolved_at' => 'datetime',
    ];

    protected $appends = ['type_label', 'status_label', 'status_color'];

    // Типы проблем
    const TYPE_CUSTOMER_UNAVAILABLE = 'customer_unavailable';
    const TYPE_WRONG_ADDRESS = 'wrong_address';
    const TYPE_DOOR_LOCKED = 'door_locked';
    const TYPE_PAYMENT_ISSUE = 'payment_issue';
    const TYPE_DAMAGED_ITEM = 'damaged_item';
    const TYPE_OTHER = 'other';

    const TYPES = [
        self::TYPE_CUSTOMER_UNAVAILABLE => ['label' => 'Клиент не отвечает', 'icon' => 'phone-off'],
        self::TYPE_WRONG_ADDRESS => ['label' => 'Неверный адрес', 'icon' => 'map-pin-off'],
        self::TYPE_DOOR_LOCKED => ['label' => 'Закрытая дверь/домофон', 'icon' => 'lock'],
        self::TYPE_PAYMENT_ISSUE => ['label' => 'Проблема с оплатой', 'icon' => 'credit-card'],
        self::TYPE_DAMAGED_ITEM => ['label' => 'Повреждённый товар', 'icon' => 'package-x'],
        self::TYPE_OTHER => ['label' => 'Другое', 'icon' => 'help-circle'],
    ];

    // Статусы
    const STATUS_OPEN = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CANCELLED = 'cancelled';

    const STATUSES = [
        self::STATUS_OPEN => ['label' => 'Открыта', 'color' => 'red'],
        self::STATUS_IN_PROGRESS => ['label' => 'В работе', 'color' => 'yellow'],
        self::STATUS_RESOLVED => ['label' => 'Решена', 'color' => 'green'],
        self::STATUS_CANCELLED => ['label' => 'Отменена', 'color' => 'gray'],
    ];

    // ==================== СВЯЗИ ====================

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ==================== АКСЕССОРЫ ====================

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type]['label'] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'gray';
    }

    // ==================== МЕТОДЫ ====================

    public function markAsInProgress(): void
    {
        $this->update(['status' => self::STATUS_IN_PROGRESS]);
    }

    public function resolve(string $resolution, int $userId): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolution' => $resolution,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    // ==================== СКОУПЫ ====================

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
