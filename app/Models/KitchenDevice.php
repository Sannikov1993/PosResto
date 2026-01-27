<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'device_id',
        'name',
        'kitchen_station_id',
        'status',
        'pin',
        'settings',
        'last_seen_at',
        'user_agent',
        'ip_address',
    ];

    protected $casts = [
        'settings' => 'array',
        'last_seen_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';   // Ожидает настройки
    const STATUS_ACTIVE = 'active';     // Активно
    const STATUS_DISABLED = 'disabled'; // Отключено

    // ===== RELATIONSHIPS =====

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function kitchenStation(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class);
    }

    // ===== SCOPES =====

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForRestaurant($query, int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    // ===== HELPERS =====

    public function isConfigured(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->kitchen_station_id !== null;
    }

    public function updateLastSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }
}
