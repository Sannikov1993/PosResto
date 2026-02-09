<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Role НЕ использует BelongsToRestaurant - запрашивается из User::getEffectiveRole()
// ДО установки контекста ресторана (во время аутентификации).
// Фильтрация по restaurant_id выполняется явно в getEffectiveRole().

class Role extends Model
{
    /**
     * Available POS modules
     */
    public const POS_MODULES = [
        'cash' => ['label' => 'Касса', 'icon' => '💵', 'description' => 'Создание и оплата заказов'],
        'orders' => ['label' => 'Заказы', 'icon' => '📋', 'description' => 'Просмотр активных заказов'],
        'delivery' => ['label' => 'Доставка', 'icon' => '🚚', 'description' => 'Управление доставками'],
        'customers' => ['label' => 'Клиенты', 'icon' => '👥', 'description' => 'База клиентов'],
        'warehouse' => ['label' => 'Склад', 'icon' => '📦', 'description' => 'Остатки и инвентаризация'],
        'stoplist' => ['label' => 'Стоп-лист', 'icon' => '🚫', 'description' => 'Блюда в стоп-листе'],
        'writeoffs' => ['label' => 'Списания', 'icon' => '📝', 'description' => 'Списания и отмены'],
        'settings' => ['label' => 'Настройки', 'icon' => '⚙️', 'description' => 'Настройки терминала'],
    ];

    /**
     * Available Backoffice modules
     */
    public const BACKOFFICE_MODULES = [
        'dashboard' => ['label' => 'Дашборд', 'icon' => '📊', 'description' => 'Сводная информация'],
        'menu' => ['label' => 'Меню', 'icon' => '🍽️', 'description' => 'Управление меню'],
        'pricelists' => ['label' => 'Прайс-листы', 'icon' => '💲', 'description' => 'Управление ценами'],
        'hall' => ['label' => 'Зал', 'icon' => '🪑', 'description' => 'Схема зала и столы'],
        'staff' => ['label' => 'Персонал', 'icon' => '👥', 'description' => 'Управление сотрудниками'],
        'attendance' => ['label' => 'Учёт времени', 'icon' => '⏱️', 'description' => 'Табель и смены'],
        'inventory' => ['label' => 'Склад', 'icon' => '📦', 'description' => 'Складской учёт'],
        'customers' => ['label' => 'Клиенты', 'icon' => '👤', 'description' => 'База клиентов'],
        'loyalty' => ['label' => 'Лояльность', 'icon' => '🎁', 'description' => 'Бонусы и акции'],
        'delivery' => ['label' => 'Доставка', 'icon' => '🚚', 'description' => 'Настройки доставки'],
        'finance' => ['label' => 'Финансы', 'icon' => '💰', 'description' => 'Финансовый учёт'],
        'analytics' => ['label' => 'Аналитика', 'icon' => '📈', 'description' => 'Отчёты и аналитика'],
        'integrations' => ['label' => 'Интеграции', 'icon' => '🔗', 'description' => 'Внешние сервисы'],
        'settings' => ['label' => 'Настройки', 'icon' => '⚙️', 'description' => 'Настройки системы'],
    ];

    protected $fillable = [
        'restaurant_id',
        'key',
        'name',
        'description',
        'color',
        'icon',
        'is_system',
        'is_active',
        'sort_order',
        // Лимиты
        'max_discount_percent',
        'max_refund_amount',
        'max_cancel_amount',
        // Доступ к интерфейсам
        'can_access_pos',
        'can_access_backoffice',
        'can_access_kitchen',
        'can_access_delivery',
        // Ограничения
        'require_manager_confirm',
        'allowed_halls',
        'allowed_payment_methods',
        // Module access (Level 2)
        'pos_modules',
        'backoffice_modules',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'max_discount_percent' => 'integer',
        'max_refund_amount' => 'integer',
        'max_cancel_amount' => 'integer',
        'can_access_pos' => 'boolean',
        'can_access_backoffice' => 'boolean',
        'can_access_kitchen' => 'boolean',
        'can_access_delivery' => 'boolean',
        'require_manager_confirm' => 'boolean',
        'allowed_halls' => 'array',
        'allowed_payment_methods' => 'array',
        'pos_modules' => 'array',
        'backoffice_modules' => 'array',
    ];

    protected $appends = ['permissions_list', 'users_count'];

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Accessors
    public function getPermissionsListAttribute(): array
    {
        return $this->permissions->pluck('key')->toArray();
    }

    public function getUsersCountAttribute(): int
    {
        // Считаем пользователей по старому полю role или по role_id
        return User::where('role_id', $this->id)
            ->orWhere('role', $this->key)
            ->count();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Methods
    public function hasPermission(string $permission): bool
    {
        // Полный доступ
        if ($this->permissions()->where('key', '*')->exists()) {
            return true;
        }
        return $this->permissions()->where('key', $permission)->exists();
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    public function syncPermissions(array $permissionKeys): void
    {
        $permissionIds = Permission::whereIn('key', $permissionKeys)
            ->where(function ($q) {
                $q->whereNull('restaurant_id')
                  ->orWhere('restaurant_id', $this->restaurant_id);
            })
            ->pluck('id');

        $this->permissions()->sync($permissionIds);
    }

    public function grantPermission(string $permissionKey): void
    {
        $permission = Permission::where('key', $permissionKey)
            ->where(function ($q) {
                $q->whereNull('restaurant_id')
                  ->orWhere('restaurant_id', $this->restaurant_id);
            })
            ->first();

        if ($permission && !$this->permissions()->where('permission_id', $permission->id)->exists()) {
            $this->permissions()->attach($permission->id);
        }
    }

    public function revokePermission(string $permissionKey): void
    {
        $permission = Permission::where('key', $permissionKey)->first();
        if ($permission) {
            $this->permissions()->detach($permission->id);
        }
    }

    // ===== Проверка лимитов =====

    /**
     * Проверить, может ли роль применить скидку указанного размера
     */
    public function canApplyDiscount(int $percent): bool
    {
        if (!$this->hasPermission('orders.discount')) {
            return false;
        }
        return $this->max_discount_percent >= $percent;
    }

    /**
     * Проверить, может ли роль сделать возврат на указанную сумму
     */
    public function canRefund(float $amount): bool
    {
        if (!$this->hasPermission('orders.refund')) {
            return false;
        }
        // 0 = нельзя возвраты, null или очень большое число = без лимита
        if ($this->max_refund_amount === 0) {
            return false;
        }
        return $this->max_refund_amount === null || $this->max_refund_amount >= $amount;
    }

    /**
     * Проверить, может ли роль отменить заказ на указанную сумму
     */
    public function canCancelOrder(float $amount): bool
    {
        if (!$this->hasPermission('orders.cancel')) {
            return false;
        }
        if ($this->max_cancel_amount === 0) {
            return false;
        }
        return $this->max_cancel_amount === null || $this->max_cancel_amount >= $amount;
    }

    /**
     * Проверить доступ к залу
     */
    public function canAccessHall(int $hallId): bool
    {
        if (empty($this->allowed_halls)) {
            return true; // Доступ ко всем залам
        }
        return in_array($hallId, $this->allowed_halls);
    }

    /**
     * Проверить доступ к способу оплаты
     */
    public function canUsePaymentMethod(string $method): bool
    {
        if (empty($this->allowed_payment_methods)) {
            return true; // Все способы доступны
        }
        return in_array($method, $this->allowed_payment_methods);
    }

    /**
     * Проверить доступ к модулю POS
     */
    public function canAccessPosModule(string $module): bool
    {
        // Если модули не заданы - доступ ко всем (для обратной совместимости)
        if ($this->pos_modules === null) {
            return true;
        }
        return in_array($module, $this->pos_modules ?? []);
    }

    /**
     * Проверить доступ к модулю Backoffice
     */
    public function canAccessBackofficeModule(string $module): bool
    {
        // Если модули не заданы - доступ ко всем (для обратной совместимости)
        if ($this->backoffice_modules === null) {
            return true;
        }
        return in_array($module, $this->backoffice_modules ?? []);
    }

    /**
     * Получить доступные POS модули
     * Возвращает пустой массив если нет доступа к POS интерфейсу
     */
    public function getAvailablePosModules(): array
    {
        // Если нет доступа к интерфейсу - нет и модулей
        if (!$this->can_access_pos) {
            return [];
        }

        $modules = $this->pos_modules ?? array_keys(self::POS_MODULES);

        // Фильтруем модули, требующие специальных прав
        return array_values(array_filter($modules, function ($module) {
            return match ($module) {
                'writeoffs' => $this->hasPermission('orders.cancel'),
                default => true,
            };
        }));
    }

    /**
     * Получить доступные Backoffice модули
     * Возвращает пустой массив если нет доступа к Backoffice интерфейсу
     */
    public function getAvailableBackofficeModules(): array
    {
        // Если нет доступа к интерфейсу - нет и модулей
        if (!$this->can_access_backoffice) {
            return [];
        }

        if ($this->backoffice_modules === null) {
            return array_keys(self::BACKOFFICE_MODULES);
        }
        return $this->backoffice_modules ?? [];
    }

    // Получить базовые роли для создания
    public static function getDefaultRoles(): array
    {
        return [
            [
                'key' => 'owner',
                'name' => 'Владелец',
                'description' => 'Полный доступ ко всем функциям системы',
                'color' => '#7c3aed',
                'icon' => '👑',
                'is_system' => true,
                'sort_order' => 1,
                // Лимиты - без ограничений
                'max_discount_percent' => 100,
                'max_refund_amount' => 999999999,
                'max_cancel_amount' => 999999999,
                // Доступ ко всем интерфейсам
                'can_access_pos' => true,
                'can_access_backoffice' => true,
                'can_access_kitchen' => true,
                'can_access_delivery' => true,
                'require_manager_confirm' => false,
                'permissions' => ['*'],
            ],
            [
                'key' => 'admin',
                'name' => 'Администратор',
                'description' => 'Управление рестораном и персоналом',
                'color' => '#2563eb',
                'icon' => '👔',
                'is_system' => true,
                'sort_order' => 2,
                // Лимиты - почти без ограничений
                'max_discount_percent' => 100,
                'max_refund_amount' => 100000,
                'max_cancel_amount' => 100000,
                'can_access_pos' => true,
                'can_access_backoffice' => true,
                'can_access_kitchen' => true,
                'can_access_delivery' => true,
                'require_manager_confirm' => false,
                'permissions' => [
                    'staff.view', 'staff.create', 'staff.edit', 'staff.delete', 'staff.schedule',
                    'menu.view', 'menu.create', 'menu.edit', 'menu.delete', 'menu.categories', 'menu.modifiers',
                    'orders.view', 'orders.create', 'orders.edit', 'orders.cancel', 'orders.discount', 'orders.refund',
                    'hall.view', 'hall.manage', 'hall.reservations',
                    'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
                    'loyalty.view', 'loyalty.edit', 'loyalty.bonuses', 'loyalty.promotions',
                    'finance.view', 'finance.shifts', 'finance.operations', 'finance.reports',
                    'inventory.view', 'inventory.manage', 'inventory.ingredients', 'inventory.invoices', 'inventory.checks', 'inventory.write_off', 'inventory.suppliers', 'inventory.settings',
                    'reports.view', 'reports.export', 'reports.analytics',
                    'settings.view', 'settings.edit', 'settings.roles',
                ],
            ],
            [
                'key' => 'manager',
                'name' => 'Менеджер',
                'description' => 'Управление залом и заказами',
                'color' => '#059669',
                'icon' => '📋',
                'is_system' => true,
                'sort_order' => 3,
                // Лимиты - умеренные
                'max_discount_percent' => 30,
                'max_refund_amount' => 10000,
                'max_cancel_amount' => 10000,
                'can_access_pos' => true,
                'can_access_backoffice' => true,
                'can_access_kitchen' => false,
                'can_access_delivery' => false,
                'require_manager_confirm' => false,
                'permissions' => [
                    'staff.view', 'staff.schedule',
                    'menu.view', 'menu.edit',
                    'orders.view', 'orders.create', 'orders.edit', 'orders.cancel', 'orders.discount',
                    'hall.view', 'hall.manage', 'hall.reservations',
                    'customers.view', 'customers.edit',
                    'loyalty.view', 'loyalty.bonuses',
                    'finance.view', 'finance.shifts',
                    'reports.view',
                ],
            ],
            [
                'key' => 'waiter',
                'name' => 'Официант',
                'description' => 'Приём и обслуживание заказов',
                'color' => '#f59e0b',
                'icon' => '🍽️',
                'is_system' => true,
                'sort_order' => 4,
                // Лимиты - минимальные
                'max_discount_percent' => 10,
                'max_refund_amount' => 0,
                'max_cancel_amount' => 0,
                'can_access_pos' => true,
                'can_access_backoffice' => false,
                'can_access_kitchen' => false,
                'can_access_delivery' => false,
                'require_manager_confirm' => true, // Требуется подтверждение менеджера
                'permissions' => [
                    'menu.view',
                    'orders.view', 'orders.create', 'orders.edit', 'orders.discount',
                    'hall.view',
                    'customers.view',
                ],
            ],
            [
                'key' => 'cook',
                'name' => 'Повар',
                'description' => 'Работа на кухне',
                'color' => '#dc2626',
                'icon' => '👨‍🍳',
                'is_system' => true,
                'sort_order' => 5,
                'max_discount_percent' => 0,
                'max_refund_amount' => 0,
                'max_cancel_amount' => 0,
                'can_access_pos' => false,
                'can_access_backoffice' => false,
                'can_access_kitchen' => true,
                'can_access_delivery' => false,
                'require_manager_confirm' => false,
                'permissions' => [
                    'menu.view',
                    'orders.view',
                    'inventory.view',
                ],
            ],
            [
                'key' => 'cashier',
                'name' => 'Кассир',
                'description' => 'Работа с кассой и оплатами',
                'color' => '#0891b2',
                'icon' => '💵',
                'is_system' => true,
                'sort_order' => 6,
                'max_discount_percent' => 15,
                'max_refund_amount' => 5000,
                'max_cancel_amount' => 5000,
                'can_access_pos' => true,
                'can_access_backoffice' => false,
                'can_access_kitchen' => false,
                'can_access_delivery' => false,
                'require_manager_confirm' => true,
                'permissions' => [
                    'menu.view',
                    'orders.view', 'orders.create', 'orders.edit', 'orders.discount', 'orders.refund', 'orders.cancel',
                    'hall.view',
                    'customers.view', 'customers.create',
                    'finance.view', 'finance.operations',
                ],
            ],
            [
                'key' => 'courier',
                'name' => 'Курьер',
                'description' => 'Доставка заказов',
                'color' => '#84cc16',
                'icon' => '🚴',
                'is_system' => true,
                'sort_order' => 7,
                'max_discount_percent' => 0,
                'max_refund_amount' => 0,
                'max_cancel_amount' => 0,
                'can_access_pos' => false,
                'can_access_backoffice' => false,
                'can_access_kitchen' => false,
                'can_access_delivery' => true,
                'require_manager_confirm' => false,
                'permissions' => [
                    'orders.view',
                    'customers.view',
                ],
            ],
            [
                'key' => 'hostess',
                'name' => 'Хостес',
                'description' => 'Встреча гостей и бронирование',
                'color' => '#ec4899',
                'icon' => '💁',
                'is_system' => true,
                'sort_order' => 8,
                'max_discount_percent' => 0,
                'max_refund_amount' => 0,
                'max_cancel_amount' => 0,
                'can_access_pos' => true,
                'can_access_backoffice' => false,
                'can_access_kitchen' => false,
                'can_access_delivery' => false,
                'require_manager_confirm' => false,
                'permissions' => [
                    'orders.view',
                    'hall.view', 'hall.reservations',
                    'customers.view', 'customers.create',
                ],
            ],
        ];
    }

    /**
     * Получить описание доступных интерфейсов
     */
    public static function getInterfaceOptions(): array
    {
        return [
            'can_access_pos' => ['label' => 'POS терминал', 'icon' => '🖥️', 'description' => 'Работа с заказами и оплатами'],
            'can_access_backoffice' => ['label' => 'Бэк-офис', 'icon' => '📊', 'description' => 'Управление рестораном'],
            'can_access_kitchen' => ['label' => 'Кухня', 'icon' => '👨‍🍳', 'description' => 'Экран кухни'],
            'can_access_delivery' => ['label' => 'Доставка', 'icon' => '🚴', 'description' => 'Приложение курьера'],
        ];
    }
}
