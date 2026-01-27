<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'restaurant_id',
        'name',
        'email',
        'phone',
        'role',
        'avatar',
        'pin_code',
        'pin_lookup',
        'password',
        'is_active',
        'is_tenant_owner',
        'last_login_at',
        'hire_date',
        'birth_date',
        'address',
        'emergency_contact',
        'position',
        'salary',
        'salary_type',
        'hourly_rate',
        'percent_rate',
        'bank_card',
        'fired_at',
        'fire_reason',
        'notes',
        'telegram_chat_id',
        'telegram_username',
        'notification_settings',
        'push_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'pin_code',
        'pin_lookup',
    ];

    protected $appends = ['role_label', 'initials', 'has_password', 'has_pin', 'pending_invitation'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'hire_date' => 'date',
            'birth_date' => 'date',
            'fired_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_tenant_owner' => 'boolean',
            'salary' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'percent_rate' => 'decimal:2',
            'notification_settings' => 'array',
        ];
    }

    // Role constants
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_OWNER = 'owner';
    const ROLE_ADMIN = 'admin';
    const ROLE_MANAGER = 'manager';
    const ROLE_WAITER = 'waiter';
    const ROLE_COOK = 'cook';
    const ROLE_CASHIER = 'cashier';
    const ROLE_COURIER = 'courier';
    const ROLE_HOSTESS = 'hostess';

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'waiter_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function tips(): HasMany
    {
        return $this->hasMany(Tip::class);
    }

    public function workSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class);
    }

    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    // Accessors
    public function getRoleLabelAttribute(): string
    {
        return self::getRoles()[$this->role] ?? $this->role ?? '';
    }

    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_substr($word, 0, 1);
        }
        return mb_strtoupper($initials);
    }

    public function getHasPasswordAttribute(): bool
    {
        // Проверяем, что пароль не пустой и не дефолтный
        return !empty($this->password) && $this->password !== '$2y$12$hash_placeholder';
    }

    public function getHasPinAttribute(): bool
    {
        return !empty($this->pin_code);
    }

    public function getPendingInvitationAttribute(): ?array
    {
        if (!$this->invitation_id) {
            return null;
        }

        $invitation = StaffInvitation::where('id', $this->invitation_id)
            ->where('status', 'pending')
            ->first();

        if (!$invitation) {
            return null;
        }

        return [
            'id' => $invitation->id,
            'invite_url' => $invitation->invite_url,
            'expires_at' => $invitation->expires_at,
            'status' => $invitation->status,
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeStaff($query)
    {
        return $query->whereIn('role', ['waiter', 'cook', 'cashier', 'courier', 'hostess']);
    }

    public function scopeManagement($query)
    {
        return $query->whereIn('role', ['admin', 'manager', 'owner']);
    }

    // Methods

    /**
     * Является ли пользователь суперадмином системы
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Является ли пользователь владельцем тенанта (организации)
     */
    public function isTenantOwner(): bool
    {
        return $this->is_tenant_owner === true;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'owner', 'admin']);
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['super_admin', 'owner', 'admin', 'manager']);
    }

    public function canManageStaff(): bool
    {
        return $this->isManager();
    }

    public function canManageMenu(): bool
    {
        return $this->isManager();
    }

    public function canViewReports(): bool
    {
        return $this->isManager();
    }

    public function canProcessPayments(): bool
    {
        return in_array($this->role, ['super_admin', 'owner', 'admin', 'manager', 'cashier']);
    }

    public function canTakeOrders(): bool
    {
        return in_array($this->role, ['super_admin', 'owner', 'admin', 'manager', 'waiter', 'cashier']);
    }

    public function verifyPin(string $pin): bool
    {
        return \Hash::check($pin, $this->pin_code);
    }

    public function setPin(string $pin): void
    {
        $this->update([
            'pin_code' => \Hash::make($pin),
            'pin_lookup' => $pin, // Для быстрого поиска при входе
        ]);
    }

    public function clearPin(): void
    {
        $this->update([
            'pin_code' => null,
            'pin_lookup' => null,
        ]);
    }

    // Static methods
    public static function getRoles(): array
    {
        return [
            'super_admin' => 'Супер-админ',
            'owner' => 'Владелец',
            'admin' => 'Администратор',
            'manager' => 'Менеджер',
            'waiter' => 'Официант',
            'cook' => 'Повар',
            'cashier' => 'Кассир',
            'courier' => 'Курьер',
            'hostess' => 'Хостес',
        ];
    }

    public static function getStaffRoles(): array
    {
        return [
            'admin' => 'Администратор',
            'manager' => 'Менеджер',
            'waiter' => 'Официант',
            'cook' => 'Повар',
            'cashier' => 'Кассир',
            'courier' => 'Курьер',
            'hostess' => 'Хостес',
        ];
    }

    public static function getRolePermissions(): array
    {
        return [
            'super_admin' => ['*'],
            'owner' => ['*'],
            'admin' => [
                'staff.view', 'staff.create', 'staff.edit', 'staff.delete',
                'menu.view', 'menu.create', 'menu.edit', 'menu.delete',
                'orders.view', 'orders.create', 'orders.edit', 'orders.cancel',
                'reports.view', 'reports.export',
                'settings.view', 'settings.edit',
                'loyalty.view', 'loyalty.edit',
                'finance.view', 'finance.edit',
            ],
            'manager' => [
                'staff.view', 'staff.edit',
                'menu.view', 'menu.edit',
                'orders.view', 'orders.create', 'orders.edit', 'orders.cancel',
                'reports.view',
                'loyalty.view', 'loyalty.edit',
                'finance.view',
            ],
            'waiter' => [
                'orders.view', 'orders.create', 'orders.edit',
                'menu.view',
            ],
            'cook' => [
                'orders.view',
                'menu.view',
            ],
            'cashier' => [
                'orders.view', 'orders.create', 'orders.edit',
                'menu.view',
                'finance.view',
            ],
            'courier' => [
                'orders.view',
            ],
            'hostess' => [
                'orders.view',
                'reservations.view', 'reservations.create', 'reservations.edit',
            ],
        ];
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = self::getRolePermissions()[$this->role] ?? [];
        return in_array('*', $permissions) || in_array($permission, $permissions);
    }

    public static function generatePin(): string
    {
        return str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    // ==================== NOTIFICATION METHODS ====================

    /**
     * Get user's notifications
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(\App\Models\Notification::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->notifications()->unread()->count();
    }

    /**
     * Check if user has Telegram connected
     */
    public function hasTelegram(): bool
    {
        return !empty($this->telegram_chat_id);
    }

    /**
     * Check if user has push notifications enabled
     */
    public function hasPushToken(): bool
    {
        return !empty($this->push_token);
    }

    /**
     * Get notification preference for specific type
     */
    public function getNotificationPreference(string $type, string $channel): bool
    {
        $settings = $this->notification_settings ?? [];

        // Default: all enabled
        if (!isset($settings[$type])) {
            return true;
        }

        return $settings[$type][$channel] ?? true;
    }

    /**
     * Set notification preference
     */
    public function setNotificationPreference(string $type, string $channel, bool $enabled): void
    {
        $settings = $this->notification_settings ?? [];

        if (!isset($settings[$type])) {
            $settings[$type] = [];
        }

        $settings[$type][$channel] = $enabled;

        $this->update(['notification_settings' => $settings]);
    }

    /**
     * Get default notification settings
     */
    public static function getDefaultNotificationSettings(): array
    {
        return [
            'shift_reminder' => ['email' => true, 'telegram' => true, 'push' => true],
            'schedule_change' => ['email' => true, 'telegram' => true, 'push' => true],
            'salary_paid' => ['email' => true, 'telegram' => true, 'push' => true],
            'bonus_received' => ['email' => true, 'telegram' => true, 'push' => true],
            'penalty_received' => ['email' => true, 'telegram' => true, 'push' => true],
            'system' => ['email' => false, 'telegram' => true, 'push' => true],
        ];
    }

    /**
     * Get channels to notify this user through for a specific type
     */
    public function getNotificationChannels(string $type): array
    {
        $channels = [];
        $settings = $this->notification_settings ?? self::getDefaultNotificationSettings();
        $typeSettings = $settings[$type] ?? ['email' => true, 'telegram' => true, 'push' => true];

        if (($typeSettings['email'] ?? true) && !empty($this->email)) {
            $channels[] = 'email';
        }

        if (($typeSettings['telegram'] ?? true) && $this->hasTelegram()) {
            $channels[] = 'telegram';
        }

        if (($typeSettings['push'] ?? true) && $this->hasPushToken()) {
            $channels[] = 'push';
        }

        // Always include in-app notifications
        $channels[] = 'in_app';

        return $channels;
    }

    /**
     * Connect Telegram account
     */
    public function connectTelegram(string $chatId, ?string $username = null): void
    {
        $this->update([
            'telegram_chat_id' => $chatId,
            'telegram_username' => $username,
        ]);
    }

    /**
     * Disconnect Telegram account
     */
    public function disconnectTelegram(): void
    {
        $this->update([
            'telegram_chat_id' => null,
            'telegram_username' => null,
        ]);
    }
}
