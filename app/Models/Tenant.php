<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'inn',
        'legal_name',
        'legal_address',
        'plan',
        'trial_ends_at',
        'subscription_ends_at',
        'settings',
        'timezone',
        'currency',
        'locale',
        'is_active',
        'blocked_at',
        'blocked_reason',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    /**
     * Доступные тарифные планы
     */
    const PLAN_TRIAL = 'trial';
    const PLAN_START = 'start';
    const PLAN_BUSINESS = 'business';
    const PLAN_PREMIUM = 'premium';

    const PLANS = [
        self::PLAN_TRIAL => 'Пробный период',
        self::PLAN_START => 'Старт',
        self::PLAN_BUSINESS => 'Бизнес',
        self::PLAN_PREMIUM => 'Премиум',
    ];

    /**
     * Рестораны организации
     */
    public function restaurants(): HasMany
    {
        return $this->hasMany(Restaurant::class);
    }

    /**
     * Пользователи организации
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Владелец организации
     */
    public function owner()
    {
        return $this->users()->where('is_tenant_owner', true)->first();
    }

    /**
     * Главный ресторан сети
     */
    public function mainRestaurant()
    {
        return $this->restaurants()->where('is_main', true)->first()
            ?? $this->restaurants()->first();
    }

    /**
     * Проверка активности подписки
     */
    public function hasActiveSubscription(): bool
    {
        // Если на триале
        if ($this->plan === self::PLAN_TRIAL) {
            return $this->trial_ends_at && $this->trial_ends_at->isFuture();
        }

        // Если платная подписка
        return $this->subscription_ends_at && $this->subscription_ends_at->isFuture();
    }

    /**
     * Проверка на триал
     */
    public function isOnTrial(): bool
    {
        return $this->plan === self::PLAN_TRIAL
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Дней до окончания подписки
     */
    public function daysUntilExpiration(): ?int
    {
        $endDate = $this->plan === self::PLAN_TRIAL
            ? $this->trial_ends_at
            : $this->subscription_ends_at;

        if (!$endDate) {
            return null;
        }

        return max(0, now()->diffInDays($endDate, false));
    }

    /**
     * Блокировка тенанта
     */
    public function block(string $reason = null): void
    {
        $this->update([
            'is_active' => false,
            'blocked_at' => now(),
            'blocked_reason' => $reason,
        ]);
    }

    /**
     * Разблокировка тенанта
     */
    public function unblock(): void
    {
        $this->update([
            'is_active' => true,
            'blocked_at' => null,
            'blocked_reason' => null,
        ]);
    }

    /**
     * Генерация уникального slug
     */
    public static function generateSlug(string $name): string
    {
        $slug = \Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
