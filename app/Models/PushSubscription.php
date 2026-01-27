<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Модель подписки на Web Push уведомления
 * Поддерживает как клиентов (Customer), так и сотрудников (User)
 */
class PushSubscription extends Model
{
    protected $fillable = [
        'subscribable_type',
        'subscribable_id',
        'customer_id', // legacy
        'user_id',
        'phone',
        'endpoint',
        'p256dh',
        'auth',
        'content_encoding',
        'device_name',
        'user_agent',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'p256dh',
        'auth',
    ];

    protected $appends = ['device_info'];

    // ==================== RELATIONSHIPS ====================

    /**
     * Полиморфная связь (Customer или User)
     */
    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Связь с клиентом (legacy)
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Связь с пользователем
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==================== SCOPES ====================

    /**
     * Scope: только активные подписки
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: по клиенту
     */
    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope: по пользователю (сотруднику)
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: по телефону
     */
    public function scopeForPhone($query, string $phone)
    {
        return $query->where('phone', $phone);
    }

    // ==================== METHODS ====================

    /**
     * Get subscription data for web-push library
     */
    public function toWebPush(): array
    {
        return [
            'endpoint' => $this->endpoint,
            'keys' => [
                'p256dh' => $this->p256dh,
                'auth' => $this->auth,
            ],
            'contentEncoding' => $this->content_encoding ?? 'aesgcm',
        ];
    }

    /**
     * Mark as used
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Deactivate subscription
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Create or update subscription for user (staff)
     */
    public static function createOrUpdateForUser(int $userId, array $data): self
    {
        return static::updateOrCreate(
            [
                'endpoint' => $data['endpoint'],
            ],
            [
                'user_id' => $userId,
                'customer_id' => null,
                'p256dh' => $data['keys']['p256dh'] ?? $data['p256dh'] ?? null,
                'auth' => $data['keys']['auth'] ?? $data['auth'] ?? null,
                'content_encoding' => $data['contentEncoding'] ?? 'aesgcm',
                'device_name' => $data['device_name'] ?? null,
                'user_agent' => $data['user_agent'] ?? request()->userAgent(),
                'is_active' => true,
            ]
        );
    }

    /**
     * Create or update subscription for customer
     */
    public static function createOrUpdateForCustomer(int $customerId, array $data, ?string $phone = null): self
    {
        return static::updateOrCreate(
            [
                'endpoint' => $data['endpoint'],
            ],
            [
                'customer_id' => $customerId,
                'user_id' => null,
                'phone' => $phone,
                'p256dh' => $data['keys']['p256dh'] ?? $data['p256dh'] ?? null,
                'auth' => $data['keys']['auth'] ?? $data['auth'] ?? null,
                'content_encoding' => $data['contentEncoding'] ?? 'aesgcm',
                'is_active' => true,
            ]
        );
    }

    /**
     * Get short device info
     */
    public function getDeviceInfoAttribute(): string
    {
        if ($this->device_name) {
            return $this->device_name;
        }

        if ($this->user_agent) {
            if (str_contains($this->user_agent, 'Android')) {
                return 'Android';
            }
            if (str_contains($this->user_agent, 'iPhone') || str_contains($this->user_agent, 'iPad')) {
                return 'iOS';
            }
            if (str_contains($this->user_agent, 'Windows')) {
                return 'Windows';
            }
            if (str_contains($this->user_agent, 'Mac')) {
                return 'macOS';
            }
            if (str_contains($this->user_agent, 'Linux')) {
                return 'Linux';
            }
        }

        return 'Устройство';
    }

    /**
     * Get all subscriptions for user IDs
     */
    public static function getForUsers(array $userIds): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereIn('user_id', $userIds)
            ->active()
            ->get();
    }
}
