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
        'hmac_secret',
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
     * Генерирует криптографически стойкий код привязки (32 символа hex)
     * Хранит SHA-256 хеш в БД, возвращает plaintext код (показывается один раз)
     * Код действует 10 минут
     */
    public function generateLinkingCode(): string
    {
        $code = bin2hex(random_bytes(16)); // 32 символа hex

        $this->update([
            'linking_code' => hash('sha256', $code),
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
     * Найти устройство по plaintext коду привязки (сравнение хешей)
     */
    public static function findByLinkingCode(string $plainCode): ?self
    {
        $hash = hash('sha256', $plainCode);

        return static::withoutGlobalScopes()
            ->where('linking_code', $hash)
            ->where('linking_code_expires_at', '>', now())
            ->whereNull('device_id')
            ->first();
    }

    /**
     * Привязать физическое устройство
     * Генерирует HMAC secret для подписи запросов
     *
     * @return string|null HMAC secret (возвращается устройству один раз)
     */
    public function linkDevice(string $deviceId, ?string $userAgent = null, ?string $ipAddress = null): ?string
    {
        $hmacSecret = bin2hex(random_bytes(32)); // 64 char hex

        $this->update([
            'device_id' => $deviceId,
            'linking_code' => null,
            'linking_code_expires_at' => null,
            'last_seen_at' => now(),
            'user_agent' => $userAgent,
            'ip_address' => $ipAddress,
            'hmac_secret' => $hmacSecret,
        ]);

        return $hmacSecret;
    }

    /**
     * Проверяет, привязано ли устройство
     */
    public function isLinked(): bool
    {
        return !empty($this->device_id);
    }
}
