<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

// StaffInvitation НЕ использует BelongsToRestaurant - запрашивается из
// User::getPendingInvitationAttribute() во время аутентификации ДО установки контекста.
// Фильтрация по restaurant_id выполняется явно при создании и в контроллерах.

class StaffInvitation extends Model
{
    protected $fillable = [
        'restaurant_id',
        'tenant_id',
        'user_id', // Link to existing user (for password reset, etc.)
        'created_by',
        'token',
        'type', // 'invitation' (default), 'password_reset'
        'email',
        'phone',
        'name',
        'role',
        'role_id',
        'salary_type',
        'salary_amount',
        'hourly_rate',
        'percent_rate',
        'permissions',
        'status',
        'expires_at',
        'accepted_at',
        'accepted_by',
        'notes',
    ];

    protected $casts = [
        'permissions' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'salary_amount' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'percent_rate' => 'decimal:2',
    ];

    protected $appends = ['invite_url', 'is_expired', 'status_label', 'role_label'];

    // Статусы
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';

    // Типы зарплаты
    const SALARY_FIXED = 'fixed';
    const SALARY_HOURLY = 'hourly';
    const SALARY_MIXED = 'mixed';
    const SALARY_PERCENT = 'percent';

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acceptedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function roleModel(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    // Accessors
    public function getInviteUrlAttribute(): string
    {
        // Use request URL if available, otherwise fall back to config
        $baseUrl = request()?->getSchemeAndHttpHost() ?: config('app.url');
        return $baseUrl . '/register/invite/' . $this->token;
    }

    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        // Убедимся что это Carbon объект
        $expiresAt = $this->expires_at instanceof \Carbon\Carbon
            ? $this->expires_at
            : \Carbon\Carbon::parse($this->expires_at);
        return $expiresAt->isPast();
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Ожидает',
            'accepted' => 'Принято',
            'expired' => 'Истекло',
            'cancelled' => 'Отменено',
            default => $this->status,
        };
    }

    public function getRoleLabelAttribute(): string
    {
        return User::getRoles()[$this->role] ?? $this->role;
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeValid($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                     ->where('expires_at', '>', now());
    }

    // Methods
    public static function generateToken(): string
    {
        do {
            $token = Str::random(32);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public static function createInvitation(array $data): self
    {
        $restaurantId = $data['restaurant_id'] ?? auth()->user()?->restaurant_id;
        if (!$restaurantId) {
            throw new \InvalidArgumentException('restaurant_id is required for StaffInvitation');
        }

        // Автоматически найти role_id если не передан
        $roleId = $data['role_id'] ?? null;
        $roleKey = $data['role'] ?? 'waiter';
        if (!$roleId && $roleKey) {
            // Сначала ищем роль ресторана по точному ключу
            $role = \App\Models\Role::where('key', $roleKey)
                ->where('restaurant_id', $restaurantId)
                ->first();

            // Если не нашли - ищем по паттерну (cashier, cashier_2, cashier_3...)
            if (!$role) {
                $role = \App\Models\Role::where('restaurant_id', $restaurantId)
                    ->where(function($q) use ($roleKey) {
                        $q->where('key', $roleKey)
                          ->orWhere('key', 'like', $roleKey . '_%');
                    })
                    ->first();
            }

            // Если не нашли - ищем системную роль
            if (!$role) {
                $role = \App\Models\Role::where('key', $roleKey)
                    ->whereNull('restaurant_id')
                    ->first();
            }

            $roleId = $role?->id;
            // Также обновляем roleKey на актуальный ключ из найденной роли
            if ($role) {
                $roleKey = $role->key;
            }

            \Log::info('StaffInvitation: Auto-resolved role_id', [
                'original_key' => $data['role'] ?? 'waiter',
                'resolved_key' => $roleKey,
                'restaurant_id' => $restaurantId,
                'found_role_id' => $roleId,
                'role_name' => $role?->name,
            ]);
        }

        return self::create([
            'restaurant_id' => $restaurantId,
            'created_by' => $data['created_by'] ?? auth()->id(),
            'token' => self::generateToken(),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'name' => $data['name'] ?? null,
            'role' => $roleKey,
            'role_id' => $roleId,
            'salary_type' => $data['salary_type'] ?? 'fixed',
            'salary_amount' => $data['salary_amount'] ?? 0,
            'hourly_rate' => $data['hourly_rate'] ?? null,
            'percent_rate' => $data['percent_rate'] ?? null,
            'permissions' => $data['permissions'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
            'expires_at' => $data['expires_at'] ?? Carbon::now()->addDays(7),
        ]);
    }

    public function accept(User $user): void
    {
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'accepted_by' => $user->id,
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    public function markExpired(): void
    {
        $this->update(['status' => self::STATUS_EXPIRED]);
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_PENDING && !$this->is_expired;
    }

    public static function getSalaryTypes(): array
    {
        return [
            'fixed' => 'Оклад (в месяц)',
            'hourly' => 'Почасовая',
            'mixed' => 'Оклад + почасовая',
            'percent' => 'Процент от продаж',
        ];
    }

    // Автоматическая пометка истёкших приглашений
    public static function markExpiredInvitations(): int
    {
        return self::where('status', self::STATUS_PENDING)
                   ->where('expires_at', '<', now())
                   ->update(['status' => self::STATUS_EXPIRED]);
    }
}
