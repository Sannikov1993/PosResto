<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\AtolOnlineService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class IntegrationSettingsController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Статус интеграций
     */
    public function integrations(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        return response()->json([
            'success' => true,
            'data' => $this->getIntegrationsStatus($restaurantId),
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
        $restaurantId = $this->getRestaurantId($request);
        $restaurant = Restaurant::find($restaurantId);

        $defaults = [
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
        ];

        $settings = $restaurant?->getSetting('notifications', $defaults) ?? $defaults;

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
        $restaurantId = $this->getRestaurantId($request);
        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'message' => 'Ресторан не найден',
            ], 404);
        }

        $settings = $request->input('settings', []);
        $restaurant->setSetting('notifications', $settings);

        return response()->json([
            'success' => true,
            'message' => 'Настройки уведомлений сохранены',
            'data' => $settings,
        ]);
    }

    /**
     * ===========================================
     * ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
     * ===========================================
     */

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
}
