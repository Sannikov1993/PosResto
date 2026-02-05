<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Версия
    |--------------------------------------------------------------------------
    |
    | Текущая версия публичного API. Используется в префиксе маршрутов
    | и заголовках ответов.
    |
    */

    'version' => env('API_VERSION', '1'),

    /*
    |--------------------------------------------------------------------------
    | API Ключи
    |--------------------------------------------------------------------------
    |
    | Настройки для API ключей клиентов (machine-to-machine auth).
    |
    */

    'keys' => [
        'prefix' => env('API_KEY_PREFIX', 'ml_'),
        'length' => 32,
        'secret_length' => 48,
    ],

    /*
    |--------------------------------------------------------------------------
    | Токены доступа
    |--------------------------------------------------------------------------
    |
    | Настройки для Bearer токенов (user context auth).
    |
    */

    'tokens' => [
        // Время жизни access token в минутах (по умолчанию 60 минут)
        'access_ttl' => env('API_ACCESS_TOKEN_TTL', 60),

        // Время жизни refresh token в днях (по умолчанию 30 дней)
        'refresh_ttl' => env('API_REFRESH_TOKEN_TTL', 30),

        // Префикс для токенов (для сканирования секретов)
        'prefix' => env('API_TOKEN_PREFIX', 'mlat_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scopes (права доступа)
    |--------------------------------------------------------------------------
    |
    | Определение доступных scopes для API токенов.
    | Формат: 'scope:action' => 'Описание'
    |
    */

    'scopes' => [
        // Меню
        'menu:read' => 'Чтение меню (категории, блюда, модификаторы)',
        'menu:write' => 'Редактирование меню',

        // Заказы
        'orders:read' => 'Чтение заказов',
        'orders:write' => 'Создание и редактирование заказов',

        // Клиенты
        'customers:read' => 'Чтение данных клиентов',
        'customers:write' => 'Редактирование данных клиентов',

        // Столы и зоны (только чтение - для показа доступности)
        'tables:read' => 'Чтение столов и зон',

        // Бронирования
        'reservations:read' => 'Чтение бронирований',
        'reservations:write' => 'Создание и редактирование бронирований',

        // Webhooks
        'webhooks:manage' => 'Управление вебхуками',

        // Программа лояльности
        'loyalty:read' => 'Чтение бонусов и уровней лояльности',
        'loyalty:write' => 'Начисление и списание бонусов',

        // Кухня (KDS)
        'kitchen:read' => 'Чтение очереди на кухне',
        'kitchen:write' => 'Управление статусами блюд на кухне',

        // Платежи
        'payments:read' => 'Чтение статуса оплаты',
        'payments:write' => 'Проведение оплаты и возвратов',
    ],

    /*
    |--------------------------------------------------------------------------
    | Группы Scopes
    |--------------------------------------------------------------------------
    |
    | Предустановленные группы scopes для типичных интеграций.
    |
    */

    'scope_groups' => [
        'website' => [
            'name' => 'Интеграция с сайтом',
            'scopes' => ['menu:read', 'orders:write', 'customers:read', 'customers:write', 'loyalty:read', 'loyalty:write'],
        ],
        'mobile_app' => [
            'name' => 'Мобильное приложение',
            'scopes' => ['menu:read', 'orders:read', 'orders:write', 'customers:read', 'customers:write', 'reservations:read', 'reservations:write', 'loyalty:read', 'loyalty:write'],
        ],
        'kiosk' => [
            'name' => 'Киоск самообслуживания',
            'scopes' => ['menu:read', 'orders:write'],
        ],
        'aggregator' => [
            'name' => 'Агрегатор доставки',
            'scopes' => ['menu:read', 'orders:read', 'orders:write'],
        ],
        'read_only' => [
            'name' => 'Только чтение',
            'scopes' => ['menu:read', 'orders:read', 'customers:read', 'tables:read', 'reservations:read', 'loyalty:read'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Настройки ограничения запросов по тарифным планам.
    | Используется Redis для хранения счётчиков.
    |
    */

    'rate_limiting' => [
        // Включить rate limiting
        'enabled' => env('API_RATE_LIMITING', true),

        // Redis соединение для rate limiting
        'connection' => env('API_RATE_LIMIT_CONNECTION', 'default'),

        // Тарифные планы
        'plans' => [
            'free' => [
                'name' => 'Free',
                'requests_per_minute' => 60,
                'burst' => 10,
                'daily_limit' => 1000,
            ],
            'business' => [
                'name' => 'Business',
                'requests_per_minute' => 300,
                'burst' => 50,
                'daily_limit' => 50000,
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'requests_per_minute' => 1000,
                'burst' => 100,
                'daily_limit' => null, // Без лимита
            ],
        ],

        // План по умолчанию для новых клиентов
        'default_plan' => 'free',

        // Заголовки rate limit
        'headers' => [
            'limit' => 'X-RateLimit-Limit',
            'remaining' => 'X-RateLimit-Remaining',
            'reset' => 'X-RateLimit-Reset',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    |
    | Настройки логирования API запросов.
    |
    */

    'logging' => [
        // Включить логирование
        'enabled' => env('API_LOGGING', true),

        // Асинхронное логирование через очереди
        'async' => env('API_LOGGING_ASYNC', true),

        // Очередь для логирования
        'queue' => env('API_LOGGING_QUEUE', 'api-logs'),

        // Хранить логи (дней)
        'retention_days' => env('API_LOGGING_RETENTION', 90),

        // Логировать тело запроса
        'log_request_body' => env('API_LOG_REQUEST_BODY', false),

        // Логировать тело ответа
        'log_response_body' => env('API_LOG_RESPONSE_BODY', false),

        // Максимальный размер тела для логирования (байт)
        'max_body_size' => 10000,

        // Исключить пути из логирования
        'excluded_paths' => [
            'api/v1/health',
            'api/v1/ping',
        ],

        // Маскировать чувствительные поля
        'masked_fields' => [
            'password',
            'pin_code',
            'api_secret',
            'token',
            'card_number',
            'cvv',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Format
    |--------------------------------------------------------------------------
    |
    | Настройки формата ответов API.
    |
    */

    'response' => [
        // Включать X-Request-ID в ответы
        'include_request_id' => true,

        // Включать время выполнения в ответы
        'include_execution_time' => true,

        // Формат даты/времени
        'datetime_format' => 'Y-m-d\TH:i:s.uP',

        // Формат даты
        'date_format' => 'Y-m-d',

        // Формат времени
        'time_format' => 'H:i:s',
    ],

    /*
    |--------------------------------------------------------------------------
    | Коды ошибок
    |--------------------------------------------------------------------------
    |
    | Стандартизированные коды ошибок API.
    |
    */

    'error_codes' => [
        // Аутентификация
        'UNAUTHORIZED' => 'Требуется аутентификация',
        'INVALID_CREDENTIALS' => 'Неверные учётные данные',
        'TOKEN_EXPIRED' => 'Токен истёк',
        'TOKEN_INVALID' => 'Недействительный токен',
        'INSUFFICIENT_SCOPE' => 'Недостаточно прав',

        // Rate Limiting
        'RATE_LIMIT_EXCEEDED' => 'Превышен лимит запросов',
        'DAILY_LIMIT_EXCEEDED' => 'Превышен дневной лимит запросов',

        // Валидация
        'VALIDATION_ERROR' => 'Ошибка валидации',
        'INVALID_REQUEST' => 'Некорректный запрос',

        // Ресурсы
        'NOT_FOUND' => 'Ресурс не найден',
        'ALREADY_EXISTS' => 'Ресурс уже существует',
        'CONFLICT' => 'Конфликт данных',

        // Бизнес-логика
        'BUSINESS_ERROR' => 'Ошибка бизнес-логики',
        'DISH_UNAVAILABLE' => 'Блюдо недоступно',
        'INSUFFICIENT_STOCK' => 'Недостаточно на складе',
        'ORDER_CANNOT_BE_MODIFIED' => 'Заказ нельзя изменить',

        // Система
        'INTERNAL_ERROR' => 'Внутренняя ошибка сервера',
        'SERVICE_UNAVAILABLE' => 'Сервис временно недоступен',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Настройки исходящих вебхуков.
    |
    */

    'webhooks' => [
        // Включить вебхуки
        'enabled' => env('API_WEBHOOKS_ENABLED', true),

        // Таймаут запроса (секунды)
        'timeout' => 30,

        // Количество повторных попыток
        'max_retries' => 3,

        // Интервал между попытками (секунды)
        'retry_delay' => [60, 300, 900], // 1 мин, 5 мин, 15 мин

        // Подписываемые события
        'events' => [
            'order.created',
            'order.updated',
            'order.completed',
            'order.cancelled',
            'reservation.created',
            'reservation.updated',
            'reservation.cancelled',
            'menu.updated',
            'customer.created',
            'customer.updated',
            'customer.bonus_earned',
            'customer.bonus_spent',
            'customer.level_changed',
            'kitchen.item_started',
            'kitchen.item_ready',
            'kitchen.item_recalled',
            'order.paid',
            'order.refunded',
        ],
    ],

];
