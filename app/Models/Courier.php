<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Модель курьера
 */
class Courier extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'user_id', 'name', 'phone', 'status', 'transport',
        'current_lat', 'current_lng', 'last_location_at', 'is_active',
    ];

    protected $casts = [
        'last_location_at' => 'datetime',
        'is_active' => 'boolean',
        'current_lat' => 'decimal:8',
        'current_lng' => 'decimal:8',
    ];

    protected $appends = ['estimated_free_time', 'status_label', 'status_color'];

    const STATUS_AVAILABLE = 'available';
    const STATUS_BUSY = 'busy';
    const STATUS_OFFLINE = 'offline';

    const STATUSES = [
        self::STATUS_AVAILABLE => ['label' => 'Доступен', 'color' => 'green'],
        self::STATUS_BUSY => ['label' => 'Занят', 'color' => 'yellow'],
        self::STATUS_OFFLINE => ['label' => 'Оффлайн', 'color' => 'gray'],
    ];

    const TRANSPORT_TYPES = [
        'car' => ['label' => 'Авто', 'icon' => '🚗'],
        'bike' => ['label' => 'Велосипед', 'icon' => '🚲'],
        'scooter' => ['label' => 'Скутер', 'icon' => '🛵'],
        'foot' => ['label' => 'Пешком', 'icon' => '🚶'],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activeOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class)
            ->whereIn('status', [DeliveryOrder::STATUS_DELIVERING]);
    }

    public function allOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class);
    }

    public function todayOrders(): HasMany
    {
        return $this->hasMany(DeliveryOrder::class)
            ->whereDate('created_at', today())
            ->where('status', DeliveryOrder::STATUS_COMPLETED);
    }

    /**
     * Обновить статус курьера
     */
    public function updateStatus(string $status): void
    {
        $this->update(['status' => $status]);
    }

    /**
     * Обновить местоположение курьера
     */
    public function updateLocation(float $lat, float $lng): void
    {
        $this->update([
            'current_lat' => $lat,
            'current_lng' => $lng,
            'last_location_at' => now(),
        ]);
    }

    /**
     * Примерное время до освобождения (в минутах)
     */
    public function getEstimatedFreeTimeAttribute(): int
    {
        $activeOrder = $this->activeOrders()->first();
        if (!$activeOrder) return 0;

        // Примерная оценка: 15-30 минут на доставку
        return $activeOrder->picked_up_at
            ? max(0, 20 - now()->diffInMinutes($activeOrder->picked_up_at))
            : 30;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'gray';
    }

    public function getTransportLabelAttribute(): string
    {
        return self::TRANSPORT_TYPES[$this->transport]['label'] ?? $this->transport;
    }

    public function getTransportIconAttribute(): string
    {
        return self::TRANSPORT_TYPES[$this->transport]['icon'] ?? '🚗';
    }

    // Скоупы
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeOnline($query)
    {
        return $query->whereIn('status', [self::STATUS_AVAILABLE, self::STATUS_BUSY]);
    }
}
