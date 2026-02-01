<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Permission НЕ использует BelongsToRestaurant - запрашивается из Role::hasPermission()
// во время аутентификации ДО установки контекста ресторана.
// Фильтрация по restaurant_id выполняется явно в Role::syncPermissions() и других методах.

class Permission extends Model
{
    protected $fillable = [
        'restaurant_id',
        'key',
        'name',
        'group',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    // Scopes
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    // Static methods - получить все группы разрешений
    public static function getGroups(): array
    {
        return [
            'staff' => [
                'label' => 'Персонал',
                'icon' => '👥',
                'permissions' => [
                    'staff.view' => 'Просмотр сотрудников',
                    'staff.create' => 'Создание сотрудников',
                    'staff.edit' => 'Редактирование сотрудников',
                    'staff.delete' => 'Удаление сотрудников',
                    'staff.schedule' => 'Управление расписанием',
                ],
            ],
            'menu' => [
                'label' => 'Меню',
                'icon' => '🍽️',
                'permissions' => [
                    'menu.view' => 'Просмотр меню',
                    'menu.create' => 'Добавление блюд',
                    'menu.edit' => 'Редактирование блюд',
                    'menu.delete' => 'Удаление блюд',
                    'menu.categories' => 'Управление категориями',
                    'menu.modifiers' => 'Управление модификаторами',
                ],
            ],
            'orders' => [
                'label' => 'Заказы',
                'icon' => '📋',
                'permissions' => [
                    'orders.view' => 'Просмотр заказов',
                    'orders.create' => 'Создание заказов',
                    'orders.edit' => 'Редактирование заказов',
                    'orders.cancel' => 'Отмена заказов',
                    'orders.discount' => 'Применение скидок',
                    'orders.refund' => 'Возврат заказов',
                ],
            ],
            'hall' => [
                'label' => 'Зал',
                'icon' => '🪑',
                'permissions' => [
                    'hall.view' => 'Просмотр зала',
                    'hall.manage' => 'Управление столами',
                    'hall.reservations' => 'Управление бронями',
                ],
            ],
            'customers' => [
                'label' => 'Клиенты',
                'icon' => '👤',
                'permissions' => [
                    'customers.view' => 'Просмотр клиентов',
                    'customers.create' => 'Создание клиентов',
                    'customers.edit' => 'Редактирование клиентов',
                    'customers.delete' => 'Удаление клиентов',
                ],
            ],
            'loyalty' => [
                'label' => 'Лояльность',
                'icon' => '🎁',
                'permissions' => [
                    'loyalty.view' => 'Просмотр программы лояльности',
                    'loyalty.edit' => 'Настройка программы лояльности',
                    'loyalty.bonuses' => 'Управление бонусами',
                    'loyalty.promotions' => 'Управление акциями',
                ],
            ],
            'finance' => [
                'label' => 'Финансы',
                'icon' => '💰',
                'permissions' => [
                    'finance.view' => 'Просмотр финансов',
                    'finance.shifts' => 'Управление кассовыми сменами',
                    'finance.operations' => 'Кассовые операции',
                    'finance.reports' => 'Финансовые отчёты',
                ],
            ],
            'inventory' => [
                'label' => 'Склад',
                'icon' => '📦',
                'permissions' => [
                    'inventory.view' => 'Просмотр склада',
                    'inventory.manage' => 'Управление запасами',
                    'inventory.write_off' => 'Списание товаров',
                ],
            ],
            'reports' => [
                'label' => 'Отчёты',
                'icon' => '📊',
                'permissions' => [
                    'reports.view' => 'Просмотр отчётов',
                    'reports.export' => 'Экспорт отчётов',
                    'reports.analytics' => 'Аналитика',
                ],
            ],
            'settings' => [
                'label' => 'Настройки',
                'icon' => '⚙️',
                'permissions' => [
                    'settings.view' => 'Просмотр настроек',
                    'settings.edit' => 'Изменение настроек',
                    'settings.integrations' => 'Управление интеграциями',
                    'settings.roles' => 'Управление ролями',
                ],
            ],
        ];
    }

    // Получить все разрешения плоским списком
    public static function getAllPermissions(): array
    {
        $result = [];
        foreach (self::getGroups() as $groupKey => $group) {
            foreach ($group['permissions'] as $key => $name) {
                $result[$key] = [
                    'key' => $key,
                    'name' => $name,
                    'group' => $groupKey,
                    'group_label' => $group['label'],
                    'group_icon' => $group['icon'],
                ];
            }
        }
        return $result;
    }
}
