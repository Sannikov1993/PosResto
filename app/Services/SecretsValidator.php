<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Централизованная валидация обязательных секретов
 *
 * Проверяет наличие критичных секретов при старте приложения.
 * В production выбрасывает RuntimeException при отсутствии обязательных секретов.
 */
class SecretsValidator
{
    /**
     * Обязательные секреты (должны быть всегда)
     */
    private const REQUIRED_SECRETS = [
        'APP_KEY' => 'app.key',
    ];

    /**
     * Условные секреты (обязательны если функция включена)
     */
    private const CONDITIONAL_SECRETS = [
        // ATOL fiscal integration
        [
            'condition_key' => 'atol.enabled',
            'condition_value' => true,
            'secrets' => [
                'ATOL_LOGIN' => 'atol.login',
                'ATOL_PASSWORD' => 'atol.password',
            ],
        ],
        // Telegram staff bot
        [
            'condition_key' => 'services.telegram.staff_bot_token',
            'condition_value' => null, // не null = включено
            'secrets' => [
                'TELEGRAM_STAFF_BOT_WEBHOOK_SECRET' => 'services.telegram.staff_bot_webhook_secret',
            ],
        ],
    ];

    /**
     * Валидация всех секретов
     *
     * @throws RuntimeException в production при отсутствии обязательных секретов
     */
    public function validate(): void
    {
        $missing = [];

        // Обязательные секреты
        foreach (self::REQUIRED_SECRETS as $envKey => $configKey) {
            if (empty(config($configKey))) {
                $missing[] = $envKey;
            }
        }

        // Проверяем APP_KEY формат
        $appKey = config('app.key');
        if ($appKey && strlen($appKey) < 32 && !str_starts_with($appKey, 'base64:')) {
            $missing[] = 'APP_KEY (invalid format)';
        }

        // Условные секреты
        foreach (self::CONDITIONAL_SECRETS as $group) {
            $conditionValue = config($group['condition_key']);

            $isEnabled = $group['condition_value'] === null
                ? !empty($conditionValue)
                : $conditionValue == $group['condition_value'];

            if ($isEnabled) {
                foreach ($group['secrets'] as $envKey => $configKey) {
                    if (empty(config($configKey))) {
                        $missing[] = $envKey;
                    }
                }
            }
        }

        if (empty($missing)) {
            return;
        }

        $message = 'Missing required secrets: ' . implode(', ', $missing);

        if (app()->isProduction()) {
            Log::critical($message);
            throw new RuntimeException($message);
        }

        Log::warning("SecretsValidator: {$message}");
    }
}
