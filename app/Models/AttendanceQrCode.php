<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AttendanceQrCode extends Model
{
    const TYPE_STATIC = 'static';
    const TYPE_DYNAMIC = 'dynamic';

    protected $fillable = [
        'restaurant_id',
        'code',
        'secret',
        'type',
        'require_geolocation',
        'max_distance_meters',
        'refresh_interval_minutes',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'require_geolocation' => 'boolean',
        'max_distance_meters' => 'integer',
        'refresh_interval_minutes' => 'integer',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'secret',
    ];

    // ==================== RELATIONSHIPS ====================

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForRestaurant($query, int $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    public function scopeValid($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    // ==================== METHODS ====================

    public function isExpired(): bool
    {
        if ($this->type === self::TYPE_STATIC) {
            return false;
        }
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * Генерирует подписанный токен для QR-кода
     */
    public function generateToken(): string
    {
        $timestamp = now()->timestamp;
        $payload = "{$this->code}:{$timestamp}";
        $signature = hash_hmac('sha256', $payload, $this->secret);

        return base64_encode("{$payload}:{$signature}");
    }

    /**
     * Валидирует токен из QR-кода
     */
    public function validateToken(string $token): bool
    {
        try {
            $decoded = base64_decode($token);
            $parts = explode(':', $decoded);

            if (count($parts) !== 3) {
                return false;
            }

            [$code, $timestamp, $signature] = $parts;

            // Проверяем код
            if ($code !== $this->code) {
                return false;
            }

            // Проверяем подпись
            $payload = "{$code}:{$timestamp}";
            $expectedSignature = hash_hmac('sha256', $payload, $this->secret);

            if (!hash_equals($expectedSignature, $signature)) {
                return false;
            }

            // Для динамических QR проверяем время
            if ($this->type === self::TYPE_DYNAMIC) {
                $tokenTime = \Carbon\Carbon::createFromTimestamp($timestamp);
                $maxAge = $this->refresh_interval_minutes * 60; // в секундах

                if ($tokenTime->diffInSeconds(now()) > $maxAge) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Обновляет динамический QR-код
     */
    public function refresh(): void
    {
        if ($this->type !== self::TYPE_DYNAMIC) {
            return;
        }

        $this->update([
            'code' => Str::random(32),
            'secret' => Str::random(64),
            'expires_at' => now()->addMinutes($this->refresh_interval_minutes),
        ]);
    }

    /**
     * Создать QR-код для ресторана
     */
    public static function createForRestaurant(
        int $restaurantId,
        string $type = self::TYPE_DYNAMIC,
        array $options = []
    ): self {
        $defaults = [
            'require_geolocation' => true,
            'max_distance_meters' => 100,
            'refresh_interval_minutes' => 5,
        ];

        $options = array_merge($defaults, $options);

        return self::create([
            'restaurant_id' => $restaurantId,
            'code' => Str::random(32),
            'secret' => Str::random(64),
            'type' => $type,
            'require_geolocation' => $options['require_geolocation'],
            'max_distance_meters' => $options['max_distance_meters'],
            'refresh_interval_minutes' => $options['refresh_interval_minutes'],
            'expires_at' => $type === self::TYPE_DYNAMIC
                ? now()->addMinutes($options['refresh_interval_minutes'])
                : null,
            'is_active' => true,
        ]);
    }

    /**
     * Получить или создать активный QR-код для ресторана
     */
    public static function getOrCreateForRestaurant(int $restaurantId): self
    {
        $qr = self::forRestaurant($restaurantId)->active()->first();

        if (!$qr) {
            $qr = self::createForRestaurant($restaurantId);
        } elseif ($qr->type === self::TYPE_DYNAMIC && $qr->isExpired()) {
            $qr->refresh();
        }

        return $qr;
    }

    /**
     * Найти QR-код по токену
     */
    public static function findByToken(string $token): ?self
    {
        try {
            $decoded = base64_decode($token);
            $parts = explode(':', $decoded);

            if (count($parts) !== 3) {
                return null;
            }

            $code = $parts[0];

            return self::where('code', $code)->active()->first();
        } catch (\Exception $e) {
            return null;
        }
    }
}
