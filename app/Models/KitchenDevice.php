<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenDevice extends Model
{
    use HasFactory, BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'device_id',
        'linking_code',
        'linking_code_expires_at',
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
        'linking_code_expires_at' => 'datetime',
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

    // ===== LINKING CODE =====

    /**
     * Генерирует новый 6-значный код привязки
     * Код действует 10 минут
     */
    public function generateLinkingCode(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->update([
            'linking_code' => $code,
            'linking_code_expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }

    /**
     * Проверяет, действителен ли код привязки
     */
    public function hasValidLinkingCode(): bool
    {
        return $this->linking_code
            && $this->linking_code_expires_at
            && $this->linking_code_expires_at->isFuture();
    }

    /**
     * Привязать физическое устройство
     */
    public function linkDevice(string $deviceId, ?string $userAgent = null, ?string $ipAddress = null): void
    {
        $this->update([
            'device_id' => $deviceId,
            'linking_code' => null,
            'linking_code_expires_at' => null,
            'last_seen_at' => now(),
            'user_agent' => $userAgent,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Проверяет, привязано ли устройство
     */
    public function isLinked(): bool
    {
        return !empty($this->device_id);
    }
}
