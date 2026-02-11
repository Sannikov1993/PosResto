<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\AtolOnlineService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Получить все настройки
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $data = Cache::remember("settings:{$restaurantId}", 3600, function () use ($restaurantId) {
            $restaurant = Restaurant::find($restaurantId);

            if (!$restaurant) {
                return null;
            }

            $defaultWorkingHours = [
                'monday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
                'tuesday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
                'wednesday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
                'thursday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
                'friday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
                'saturday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
                'sunday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
            ];
            $defaults = [
                'round_amounts' => false,
                'working_hours' => $defaultWorkingHours,
                'timezone' => 'Europe/Moscow',
                'currency' => 'RUB',
                'business_day_ends_at' => 5,
            ];
            $generalSettings = $restaurant->settings ?? [];
            foreach ($defaults as $key => $value) {
                if (!isset($generalSettings[$key])) {
                    $generalSettings[$key] = $value;
                }
            }

            return [
                'data' => [
                    'general' => $restaurant,
                    'integrations' => $this->getIntegrationsStatus($restaurantId),
                ],
                'settings' => array_merge($restaurant->toArray(), $generalSettings),
                'notifications' => $this->getNotificationsForResponse($restaurantId),
            ];
        });

        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => 'Ресторан не найден',
            ], 404);
        }

        return response()->json(array_merge(['success' => true], $data));
    }

    /**
     * Получить общие настройки (для всех модулей)
     */
    public function generalSettings(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $restaurant = Restaurant::find($restaurantId);

        $defaultWorkingHours = [
            'monday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
            'tuesday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
            'wednesday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
            'thursday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
            'friday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
            'saturday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
            'sunday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
        ];

        $defaults = [
            'round_amounts' => false,
            'working_hours' => $defaultWorkingHours,
            'timezone' => 'Europe/Moscow',
            'currency' => 'RUB',
            'business_day_ends_at' => 5, // Час окончания рабочего дня (по умолчанию 05:00)
        ];

        // Получаем настройки из БД (поле settings ресторана)
        $settings = $restaurant?->settings ?? [];

        // Ensure all default fields exist
        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Обновить настройки ресторана
     */
    public function update(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);
        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Ресторан не найден',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string|max:500',
            'phone' => 'sometimes|nullable|string|max:50',
            'email' => 'sometimes|nullable|email|max:255',
            'settings' => 'sometimes|nullable|array',
            'round_amounts' => 'sometimes|nullable|boolean',
            'working_hours' => 'sometimes|nullable|array',
            'timezone' => 'sometimes|nullable|string|max:50',
            'currency' => 'sometimes|nullable|string|max:10',
            'business_day_ends_at' => 'sometimes|nullable|integer|min:0|max:12', // Час окончания рабочего дня (0-12)
        ]);

        // Сохраняем настройки в БД (поле settings ресторана)
        $currentSettings = $restaurant->settings ?? [];
        $needsSettingsUpdate = false;

        // Поля для сохранения в settings
        $settingsFields = ['round_amounts', 'working_hours', 'timezone', 'currency', 'business_day_ends_at'];
        foreach ($settingsFields as $field) {
            if ($request->has($field)) {
                $currentSettings[$field] = $validated[$field];
                unset($validated[$field]);
                $needsSettingsUpdate = true;
            }
        }

        if ($needsSettingsUpdate) {
            $validated['settings'] = $currentSettings;
        }

        $restaurant->update($validated);
        Cache::forget("settings:{$restaurantId}");

        return response()->json([
            'success' => true,
            'message' => 'Настройки сохранены',
            'data' => $restaurant,
        ]);
    }

    /**
     * ===========================================
     * РОЛИ И ПРАВА
     * ===========================================
     */

    /**
     * Получить список ролей
     */
    public function roles(): JsonResponse
    {
        $roles = [
            [
                'id' => 'admin',
                'name' => 'Администратор',
                'description' => 'Полный доступ ко всем функциям',
                'permissions' => ['*'],
                'color' => '#ef4444',
            ],
            [
                'id' => 'manager',
                'name' => 'Менеджер',
                'description' => 'Управление заведением, отчёты, персонал',
                'permissions' => ['orders', 'menu', 'staff', 'reports', 'customers', 'reservations'],
                'color' => '#f97316',
            ],
            [
                'id' => 'cashier',
                'name' => 'Кассир',
                'description' => 'Работа с кассой, оплата заказов',
                'permissions' => ['orders', 'payments', 'cash_shift'],
                'color' => '#22c55e',
            ],
            [
                'id' => 'waiter',
                'name' => 'Официант',
                'description' => 'Приём заказов, работа со столами',
                'permissions' => ['orders.create', 'orders.view', 'tables.view'],
                'color' => '#3b82f6',
            ],
            [
                'id' => 'cook',
                'name' => 'Повар',
                'description' => 'Просмотр заказов на кухне',
                'permissions' => ['orders.view', 'kitchen'],
                'color' => '#a855f7',
            ],
            [
                'id' => 'courier',
                'name' => 'Курьер',
                'description' => 'Доставка заказов',
                'permissions' => ['delivery'],
                'color' => '#06b6d4',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Получить сотрудников с ролями
     */
    public function staffWithRoles(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $users = User::where('restaurant_id', $restaurantId)
            ->select('id', 'name', 'role', 'is_active')
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Обновить роль сотрудника
     */
    public function updateStaffRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|in:admin,manager,cashier,waiter,cook,courier,hostess',
        ]);

        // Обновляем оба поля: role (строка) и role_id (FK)
        $updateData = ['role' => $validated['role']];

        $roleRecord = \App\Models\Role::where('key', $validated['role'])
            ->where('restaurant_id', $user->restaurant_id)
            ->first();

        if ($roleRecord) {
            $updateData['role_id'] = $roleRecord->id;
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Роль обновлена',
            'data' => $user,
        ]);
    }

    /**
     * ===========================================
     * ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
     * ===========================================
     */

    /**
     * Получить настройки уведомлений для ответа
     */
    protected function getNotificationsForResponse(int $restaurantId): array
    {
        $restaurant = Restaurant::find($restaurantId);
        return $restaurant?->getSetting('notifications', [
            'newOrder' => true,
            'orderReady' => true,
            'dailyReport' => false,
            'telegram' => false,
        ]) ?? [
            'newOrder' => true,
            'orderReady' => true,
            'dailyReport' => false,
            'telegram' => false,
        ];
    }

    protected function getIntegrationsStatus(int $restaurantId): array
    {
        $atol = app(AtolOnlineService::class);

        // Получаем настройки Yandex из БД
        $restaurant = Restaurant::find($restaurantId);
        $yandexSettings = $restaurant?->getSetting('yandex', []) ?? [];
        $yandexEnabled = !empty($yandexSettings['enabled']) && !empty($yandexSettings['api_key']);

        return [
            'atol' => [
                'name' => 'АТОЛ Онлайн',
                'description' => 'Фискализация чеков (54-ФЗ)',
                'enabled' => $atol->isEnabled(),
                'test_mode' => config('atol.test_mode', true),
                'configured' => !empty(config('atol.login')),
            ],
            'telegram' => [
                'name' => 'Telegram Bot',
                'description' => 'Уведомления через Telegram',
                'enabled' => !empty(config('services.telegram.bot_token')),
                'configured' => !empty(config('services.telegram.bot_token')),
            ],
            'yandex' => [
                'name' => 'Яндекс Карты',
                'description' => 'Геокодирование и расчёт доставки',
                'enabled' => $yandexEnabled,
                'configured' => !empty($yandexSettings['api_key']),
            ],
            'sms' => [
                'name' => 'SMS-уведомления',
                'description' => 'SMS клиентам',
                'enabled' => false,
                'configured' => false,
            ],
            'email' => [
                'name' => 'Email',
                'description' => 'Email-уведомления',
                'enabled' => !empty(config('mail.mailers.smtp.host')),
                'configured' => !empty(config('mail.mailers.smtp.host')),
            ],
            'yandex_eda' => [
                'name' => 'Яндекс Еда',
                'description' => 'Интеграция с агрегатором',
                'enabled' => false,
                'configured' => false,
            ],
            'delivery_club' => [
                'name' => 'Delivery Club',
                'description' => 'Интеграция с агрегатором',
                'enabled' => false,
                'configured' => false,
            ],
        ];
    }
}
