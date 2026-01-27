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
    /**
     * Получить все настройки
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Ресторан не найден',
            ], 404);
        }

        // Получаем общие настройки из БД (поле settings ресторана)
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
        $generalSettings = $restaurant->settings ?? [];
        // Ensure all default fields exist
        foreach ($defaults as $key => $value) {
            if (!isset($generalSettings[$key])) {
                $generalSettings[$key] = $value;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'general' => $restaurant,
                'integrations' => $this->getIntegrationsStatus(),
            ],
            // Дополнительно для backoffice
            'settings' => array_merge($restaurant->toArray(), $generalSettings),
            'notifications' => $this->getNotificationsForResponse($restaurantId),
        ]);
    }

    /**
     * Получить общие настройки (для всех модулей)
     */
    public function generalSettings(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
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
     * Получить настройки уведомлений для ответа
     */
    protected function getNotificationsForResponse(int $restaurantId): array
    {
        $cacheKey = "notifications_settings_{$restaurantId}";
        return Cache::get($cacheKey, [
            'newOrder' => true,
            'orderReady' => true,
            'dailyReport' => false,
            'telegram' => false,
        ]);
    }

    /**
     * Обновить настройки ресторана
     */
    public function update(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Ресторан не найден',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500',
            'phone' => 'sometimes|string|max:50',
            'email' => 'sometimes|email|max:255',
            'settings' => 'sometimes|array',
            'round_amounts' => 'sometimes|boolean',
            'working_hours' => 'sometimes|array',
            'timezone' => 'sometimes|string|max:50',
            'currency' => 'sometimes|string|max:10',
            'business_day_ends_at' => 'sometimes|integer|min:0|max:12', // Час окончания рабочего дня (0-12)
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
        $restaurantId = $request->input('restaurant_id', 1);

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
            'role' => 'required|in:admin,manager,cashier,waiter,cook,courier',
        ]);

        $user->update(['role' => $validated['role']]);

        return response()->json([
            'success' => true,
            'message' => 'Роль обновлена',
            'data' => $user,
        ]);
    }

    /**
     * ===========================================
     * ИНТЕГРАЦИИ
     * ===========================================
     */

    /**
     * Статус интеграций
     */
    public function integrations(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->getIntegrationsStatus(),
        ]);
    }

    /**
     * Проверить интеграцию
     */
    public function checkIntegration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'integration' => 'required|in:atol,telegram,sms,email',
        ]);

        $result = match ($validated['integration']) {
            'atol' => $this->checkAtolIntegration(),
            'telegram' => $this->checkTelegramIntegration(),
            'sms' => $this->checkSmsIntegration(),
            'email' => $this->checkEmailIntegration(),
        };

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * ===========================================
     * НАСТРОЙКИ УВЕДОМЛЕНИЙ
     * ===========================================
     */

    /**
     * Получить настройки уведомлений
     */
    public function notifications(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $cacheKey = "notifications_settings_{$restaurantId}";

        $settings = Cache::get($cacheKey, [
            'new_order' => [
                'sound' => true,
                'push' => true,
                'telegram' => false,
            ],
            'order_ready' => [
                'sound' => true,
                'push' => true,
                'telegram' => false,
            ],
            'new_reservation' => [
                'sound' => true,
                'push' => true,
                'telegram' => false,
            ],
            'low_stock' => [
                'sound' => false,
                'push' => true,
                'email' => true,
            ],
            'shift_end' => [
                'sound' => true,
                'push' => true,
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Обновить настройки уведомлений
     */
    public function updateNotifications(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $cacheKey = "notifications_settings_{$restaurantId}";

        $settings = $request->input('settings', []);
        Cache::put($cacheKey, $settings, now()->addYear());

        return response()->json([
            'success' => true,
            'message' => 'Настройки уведомлений сохранены',
            'data' => $settings,
        ]);
    }

    /**
     * ===========================================
     * НАСТРОЙКИ ПРИНТЕРОВ (общие)
     * ===========================================
     */

    /**
     * Получить настройки печати по умолчанию
     */
    public function printSettings(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $cacheKey = "print_settings_{$restaurantId}";

        $defaults = [
            // Автопечать
            'auto_print_receipt' => false,
            'auto_print_kitchen' => true,
            'auto_print_new_items' => true,
            'receipt_copies' => 1,
            'kitchen_copies' => 1,

            // Шапка чека
            'receipt_header_name' => '',
            'receipt_header_address' => '',
            'receipt_header_phone' => '',
            'receipt_header_inn' => '',

            // Настройки печати
            'print_logo' => false,
            'print_qr' => false,
            'qr_url' => '',
            'qr_text' => 'Сканируйте для отзыва',

            // Отображение на чеке гостя
            'show_waiter' => true,
            'show_table' => true,
            'show_guests_count' => false,
            'show_order_number' => true,
            'show_order_time' => true,
            'show_payment_method' => true,

            // Футер чека
            'receipt_footer_line1' => 'Спасибо за визит!',
            'receipt_footer_line2' => 'Ждем вас снова!',

            // Футер доставки
            'delivery_footer_line1' => 'Спасибо за заказ!',
            'delivery_footer_line2' => 'Приятного аппетита!',

            // Отображение на чеке доставки
            'delivery_show_customer' => true,
            'delivery_show_phone' => true,
            'delivery_show_address' => true,
            'delivery_show_entrance' => true,
            'delivery_show_intercom' => true,
            'delivery_show_courier' => true,
            'delivery_show_comment' => true,

            // Кухня
            'kitchen_beep' => true,
            'kitchen_large_font' => true,
            'kitchen_bold_items' => true,
            'kitchen_header_text' => 'НОВЫЙ ЗАКАЗ',
            'kitchen_show_table' => true,
            'kitchen_show_waiter' => true,
            'kitchen_show_order_number' => true,
            'kitchen_show_time' => true,
            'kitchen_show_order_type' => true,
            'kitchen_show_modifiers' => true,
            'kitchen_show_comments' => true,

            // Пречек
            'precheck_title' => 'ПРЕДВАРИТЕЛЬНЫЙ СЧЁТ',
            'precheck_subtitle' => '(не является фискальным документом)',
            'precheck_show_table' => true,
            'precheck_show_waiter' => true,
            'precheck_show_date' => true,
            'precheck_show_guests' => false,
            'precheck_footer' => 'Приятного аппетита!',
        ];

        $settings = Cache::get($cacheKey, []);

        return response()->json([
            'success' => true,
            'data' => array_merge($defaults, $settings),
        ]);
    }

    /**
     * Обновить настройки печати
     */
    public function updatePrintSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Автопечать
            'auto_print_receipt' => 'nullable|boolean',
            'auto_print_kitchen' => 'nullable|boolean',
            'auto_print_new_items' => 'nullable|boolean',
            'receipt_copies' => 'nullable|integer|min:1|max:5',
            'kitchen_copies' => 'nullable|integer|min:1|max:5',

            // Шапка чека
            'receipt_header_name' => 'nullable|string|max:100',
            'receipt_header_address' => 'nullable|string|max:200',
            'receipt_header_phone' => 'nullable|string|max:50',
            'receipt_header_inn' => 'nullable|string|max:20',

            // Настройки печати
            'print_logo' => 'nullable|boolean',
            'print_qr' => 'nullable|boolean',
            'qr_url' => 'nullable|string|max:200',
            'qr_text' => 'nullable|string|max:100',

            // Отображение на чеке гостя
            'show_waiter' => 'nullable|boolean',
            'show_table' => 'nullable|boolean',
            'show_guests_count' => 'nullable|boolean',
            'show_order_number' => 'nullable|boolean',
            'show_order_time' => 'nullable|boolean',
            'show_payment_method' => 'nullable|boolean',

            // Футер чека
            'receipt_footer_line1' => 'nullable|string|max:100',
            'receipt_footer_line2' => 'nullable|string|max:100',

            // Футер доставки
            'delivery_footer_line1' => 'nullable|string|max:100',
            'delivery_footer_line2' => 'nullable|string|max:100',

            // Отображение на чеке доставки
            'delivery_show_customer' => 'nullable|boolean',
            'delivery_show_phone' => 'nullable|boolean',
            'delivery_show_address' => 'nullable|boolean',
            'delivery_show_entrance' => 'nullable|boolean',
            'delivery_show_intercom' => 'nullable|boolean',
            'delivery_show_courier' => 'nullable|boolean',
            'delivery_show_comment' => 'nullable|boolean',

            // Кухня
            'kitchen_beep' => 'nullable|boolean',
            'kitchen_large_font' => 'nullable|boolean',
            'kitchen_bold_items' => 'nullable|boolean',
            'kitchen_header_text' => 'nullable|string|max:50',
            'kitchen_show_table' => 'nullable|boolean',
            'kitchen_show_waiter' => 'nullable|boolean',
            'kitchen_show_order_number' => 'nullable|boolean',
            'kitchen_show_time' => 'nullable|boolean',
            'kitchen_show_order_type' => 'nullable|boolean',
            'kitchen_show_modifiers' => 'nullable|boolean',
            'kitchen_show_comments' => 'nullable|boolean',

            // Пречек
            'precheck_title' => 'nullable|string|max:100',
            'precheck_subtitle' => 'nullable|string|max:100',
            'precheck_show_table' => 'nullable|boolean',
            'precheck_show_waiter' => 'nullable|boolean',
            'precheck_show_date' => 'nullable|boolean',
            'precheck_show_guests' => 'nullable|boolean',
            'precheck_footer' => 'nullable|string|max:100',
        ]);

        $restaurantId = $request->input('restaurant_id', 1);
        $cacheKey = "print_settings_{$restaurantId}";

        // Убираем null значения, чтобы они не перезаписывали существующие настройки
        $validated = array_filter($validated, fn($value) => $value !== null);

        $currentSettings = Cache::get($cacheKey, []);
        $newSettings = array_merge($currentSettings, $validated);

        Cache::put($cacheKey, $newSettings, now()->addYear());

        return response()->json([
            'success' => true,
            'message' => 'Настройки печати сохранены',
            'data' => $newSettings,
        ]);
    }

    /**
     * ===========================================
     * НАСТРОЙКИ POS-ТЕРМИНАЛА
     * ===========================================
     */

    /**
     * Получить настройки POS-терминала
     */
    public function posSettings(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $cacheKey = "pos_settings_{$restaurantId}";

        $defaults = [
            'theme' => 'dark',
            'fontSize' => 'medium',
            'tileSize' => 'standard',
            'showDishPhotos' => true,
            'showCalories' => false,
            'floorScale' => 100,
            'enableAnimations' => true,
            'autoOpenShift' => false,
            'confirmCloseShift' => true,
            'roundingMode' => 'none',
            'defaultPaymentMethod' => 'cash',
            'autoPrintPrecheck' => false,
            'requireCancelComment' => true,
            'autoPrintReceipt' => false,
            'autoPrintKitchen' => true,
            'receiptCopies' => 1,
            'kitchenCopies' => 1,
            'defaultPrinter' => null,
            'paperWidth' => 80,
            'printLogo' => true,
            'receiptFooter' => 'Спасибо за визит!',
            'soundNewOrder' => true,
            'soundOrderReady' => true,
            'soundWaiterCall' => true,
            'soundVolume' => 70,
            'enableVibration' => true,
            'quickDishes' => [],
            'quickDiscounts' => [5, 10, 15, 20],
            'showChangeCalculator' => true,
            'minDeliveryAmount' => 500,
            'autoAssignCourier' => false,
            'showDeliveryMap' => true,
            'defaultDeliveryRadius' => 5,
            'autoLogoutMinutes' => 30,
            'requirePinForCancel' => true,
            'requirePinForDiscount' => false,
            'requirePinForRefund' => true,
            'screenLockEnabled' => false,
            'menuRefreshInterval' => 5,
            'offlineModeEnabled' => false,
            'syncInterval' => 15,
            'cacheImages' => true,
        ];

        $settings = Cache::get($cacheKey, $defaults);

        return response()->json([
            'success' => true,
            'data' => array_merge($defaults, $settings),
        ]);
    }

    /**
     * Сохранить настройки POS-терминала
     */
    public function updatePosSettings(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $cacheKey = "pos_settings_{$restaurantId}";

        $settings = $request->except(['restaurant_id']);

        $currentSettings = Cache::get($cacheKey, []);
        $newSettings = array_merge($currentSettings, $settings);

        Cache::put($cacheKey, $newSettings, now()->addYear());

        return response()->json([
            'success' => true,
            'message' => 'Настройки POS сохранены',
            'data' => $newSettings,
        ]);
    }

    /**
     * ===========================================
     * ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
     * ===========================================
     */

    protected function getIntegrationsStatus(): array
    {
        $atol = app(AtolOnlineService::class);

        // Получаем настройки Yandex из кэша
        $yandexSettings = Cache::get('yandex_settings_1', []);
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

    protected function checkAtolIntegration(): array
    {
        $atol = app(AtolOnlineService::class);

        if (!$atol->isEnabled()) {
            return [
                'status' => 'disabled',
                'message' => 'Интеграция отключена',
            ];
        }

        $token = $atol->getToken();

        return [
            'status' => $token ? 'ok' : 'error',
            'message' => $token ? 'Подключение успешно' : 'Ошибка авторизации',
            'token_valid' => $token !== null,
        ];
    }

    protected function checkTelegramIntegration(): array
    {
        $token = config('services.telegram.bot_token');

        if (empty($token)) {
            return [
                'status' => 'not_configured',
                'message' => 'Telegram бот не настроен',
            ];
        }

        // Здесь можно добавить реальную проверку через API Telegram
        return [
            'status' => 'ok',
            'message' => 'Бот настроен',
        ];
    }

    protected function checkSmsIntegration(): array
    {
        return [
            'status' => 'not_configured',
            'message' => 'SMS провайдер не настроен',
        ];
    }

    protected function checkEmailIntegration(): array
    {
        $host = config('mail.mailers.smtp.host');

        if (empty($host)) {
            return [
                'status' => 'not_configured',
                'message' => 'SMTP не настроен',
            ];
        }

        return [
            'status' => 'ok',
            'message' => 'Email настроен',
            'from' => config('mail.from.address'),
        ];
    }

    /**
     * ===========================================
     * НАСТРОЙКИ РУЧНЫХ СКИДОК
     * ===========================================
     */

    /**
     * Получить настройки ручных скидок (для POS)
     */
    public function manualDiscountSettings(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $cacheKey = "manual_discount_settings_{$restaurantId}";

        $defaults = [
            'preset_percentages' => [5, 10, 15, 20],
            'max_discount_without_pin' => 20,
            'allow_custom_percent' => true,
            'allow_fixed_amount' => true,
            'require_reason' => false,
            'reasons' => [
                ['id' => 'birthday', 'label' => 'День рождения'],
                ['id' => 'regular', 'label' => 'Постоянный клиент'],
                ['id' => 'complaint', 'label' => 'Жалоба/компенсация'],
                ['id' => 'manager', 'label' => 'Скидка менеджера'],
                ['id' => 'staff', 'label' => 'Сотрудник'],
                ['id' => 'promo', 'label' => 'Акция ресторана'],
                ['id' => 'other', 'label' => 'Другое'],
            ],
        ];

        $settings = Cache::get($cacheKey, $defaults);

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
     * Сохранить настройки ручных скидок (для бекофиса)
     */
    public function updateManualDiscountSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preset_percentages' => 'nullable|array',
            'preset_percentages.*' => 'integer|min:1|max:100',
            'max_discount_without_pin' => 'nullable|integer|min:0|max:100',
            'allow_custom_percent' => 'nullable|boolean',
            'allow_fixed_amount' => 'nullable|boolean',
            'require_reason' => 'nullable|boolean',
            'reasons' => 'nullable|array',
            'reasons.*.id' => 'required|string|max:50',
            'reasons.*.label' => 'required|string|max:100',
        ]);

        $restaurantId = $request->input('restaurant_id', 1);
        $cacheKey = "manual_discount_settings_{$restaurantId}";

        $currentSettings = Cache::get($cacheKey, []);
        $newSettings = array_merge($currentSettings, $validated);

        Cache::put($cacheKey, $newSettings, now()->addYear());

        return response()->json([
            'success' => true,
            'message' => 'Настройки скидок сохранены',
            'data' => $newSettings,
        ]);
    }

    /**
     * ===========================================
     * YANDEX MAPS / GEOCODER
     * ===========================================
     */

    /**
     * Получить настройки Yandex Карт
     */
    public function yandexSettings(Request $request): JsonResponse
    {
        $restaurantId = $request->input('restaurant_id', 1);
        $cacheKey = "yandex_settings_{$restaurantId}";

        $settings = Cache::get($cacheKey, [
            'enabled' => false,
            'api_key' => '',
            'city' => '',
            'restaurant_address' => '',
            'restaurant_lat' => '',
            'restaurant_lng' => '',
        ]);

        // Маскируем API ключ для безопасности (показываем только последние 8 символов)
        if (!empty($settings['api_key'])) {
            $settings['api_key'] = str_repeat('*', 28) . substr($settings['api_key'], -8);
        }

        return response()->json($settings);
    }

    /**
     * Сохранить настройки Yandex Карт
     */
    public function updateYandexSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'api_key' => 'required|string|max:100',
            'city' => 'nullable|string|max:100',
            'restaurant_address' => 'nullable|string|max:500',
            'restaurant_lat' => 'required|numeric',
            'restaurant_lng' => 'required|numeric',
        ]);

        $restaurantId = $request->input('restaurant_id', 1);
        $cacheKey = "yandex_settings_{$restaurantId}";

        // Получаем текущие настройки
        $currentSettings = Cache::get($cacheKey, []);

        // Если ключ замаскирован (начинается с *), используем старый ключ
        if (str_starts_with($validated['api_key'], '*') && !empty($currentSettings['api_key'])) {
            $validated['api_key'] = $currentSettings['api_key'];
        }

        Cache::put($cacheKey, $validated, now()->addYear());

        return response()->json([
            'success' => true,
            'message' => 'Настройки Яндекс Карт сохранены',
        ]);
    }

    /**
     * Тест подключения к Yandex Geocoder
     */
    public function testYandexConnection(Request $request): JsonResponse
    {
        $apiKey = $request->input('api_key');

        // Если ключ замаскирован, берём из кэша
        if (str_starts_with($apiKey, '*')) {
            $restaurantId = $request->input('restaurant_id', 1);
            $cacheKey = "yandex_settings_{$restaurantId}";
            $settings = Cache::get($cacheKey, []);
            $apiKey = $settings['api_key'] ?? '';
        }

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'error' => 'API ключ не указан',
            ]);
        }

        try {
            // Делаем тестовый запрос к геокодеру
            $testAddress = 'Москва, Красная площадь';
            $url = 'https://geocode-maps.yandex.ru/1.x/?' . http_build_query([
                'apikey' => $apiKey,
                'geocode' => $testAddress,
                'format' => 'json',
                'results' => 1,
            ]);

            $response = file_get_contents($url);
            $data = json_decode($response, true);

            if (isset($data['response']['GeoObjectCollection'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Подключение успешно',
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Неверный ответ от геокодера',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ошибка подключения: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Геокодирование адреса ресторана
     */
    public function geocodeRestaurantAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => 'required|string|max:500',
            'api_key' => 'required|string',
        ]);

        $apiKey = $validated['api_key'];

        // Если ключ замаскирован, берём из кэша
        if (str_starts_with($apiKey, '*')) {
            $restaurantId = $request->input('restaurant_id', 1);
            $cacheKey = "yandex_settings_{$restaurantId}";
            $settings = Cache::get($cacheKey, []);
            $apiKey = $settings['api_key'] ?? '';
        }

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'error' => 'API ключ не указан',
            ]);
        }

        try {
            $url = 'https://geocode-maps.yandex.ru/1.x/?' . http_build_query([
                'apikey' => $apiKey,
                'geocode' => $validated['address'],
                'format' => 'json',
                'results' => 1,
            ]);

            $response = file_get_contents($url);
            $data = json_decode($response, true);

            $featureMember = $data['response']['GeoObjectCollection']['featureMember'] ?? [];

            if (empty($featureMember)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Адрес не найден',
                ]);
            }

            $geoObject = $featureMember[0]['GeoObject'] ?? null;
            if (!$geoObject) {
                return response()->json([
                    'success' => false,
                    'error' => 'Не удалось получить данные адреса',
                ]);
            }

            // Координаты в Яндексе в формате "долгота широта"
            $pos = $geoObject['Point']['pos'] ?? null;
            if (!$pos) {
                return response()->json([
                    'success' => false,
                    'error' => 'Координаты не найдены',
                ]);
            }

            [$lng, $lat] = explode(' ', $pos);

            return response()->json([
                'success' => true,
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'formatted_address' => $geoObject['metaDataProperty']['GeocoderMetaData']['text'] ?? $validated['address'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ошибка геокодирования: ' . $e->getMessage(),
            ]);
        }
    }
}
