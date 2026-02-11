<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class DeviceSession extends Model
{
    protected $fillable = [
        'user_id',
        'tenant_id',
        'device_fingerprint',
        'device_name',
        'app_type',
        'token',
        'token_hash',
        'last_activity_at',
        'expires_at',
        'rotation_token_hash',
        'max_lifetime_at',
        'rotated_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
        'max_lifetime_at' => 'datetime',
        'rotated_at' => 'datetime',
    ];

    // App types
    const APP_POS = 'pos';
    const APP_WAITER = 'waiter';
    const APP_COURIER = 'courier';
    const APP_KITCHEN = 'kitchen';
    const APP_BACKOFFICE = 'backoffice';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeForApp($query, string $appType)
    {
        return $query->where('app_type', $appType);
    }

    public function scopeForDevice($query, string $deviceFingerprint)
    {
        return $query->where('device_fingerprint', $deviceFingerprint);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Methods
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        // Считаем сессию активной если активность была в последние 24 часа
        if ($this->last_activity_at) {
            return $this->last_activity_at->gt(now()->subHours(24));
        }

        return true;
    }

    public function updateActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    public function extend(int $days = 30): void
    {
        $this->update(['expires_at' => now()->addDays($days)]);
    }

    // Static methods
    public static function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Найти сессию по токену (dual-lookup: хеш + plaintext для миграции)
     */
    public static function findByToken(string $token): ?self
    {
        $hash = hash('sha256', $token);

        // Приоритет: поиск по хешу
        $session = self::where('token_hash', $hash)->first();

        if (!$session) {
            // Fallback: plaintext (graceful migration period)
            $session = self::where('token', $token)->first();
            if ($session && !$session->token_hash) {
                // Мигрируем на хеш
                $session->update(['token_hash' => $hash]);
            }
        }

        return $session;
    }

    public static function cleanupExpired(): int
    {
        return self::where('expires_at', '<', now())->delete();
    }

    public static function getAppTypes(): array
    {
        return [
            self::APP_POS => 'POS-терминал',
            self::APP_WAITER => 'Официант',
            self::APP_COURIER => 'Курьер',
            self::APP_KITCHEN => 'Кухня',
            self::APP_BACKOFFICE => 'Бэк-офис',
        ];
    }
}
