<?php

return [
    /*
    |--------------------------------------------------------------------------
    | АТОЛ Онлайн - Настройки интеграции
    |--------------------------------------------------------------------------
    |
    | Документация: https://online.atol.ru/lib/
    |
    */

    // Включить фискализацию
    'enabled' => env('ATOL_ENABLED', false),

    // Тестовый режим (использует тестовый API)
    'test_mode' => env('ATOL_TEST_MODE', true),

    // Учётные данные
    'login' => env('ATOL_LOGIN'),
    'password' => env('ATOL_PASSWORD'),

    // Код группы касс
    'group_code' => env('ATOL_GROUP_CODE'),

    // URL API
    'api_url' => env('ATOL_API_URL', 'https://online.atol.ru/possystem/v4'),
    'test_api_url' => env('ATOL_TEST_API_URL', 'https://testonline.atol.ru/possystem/v4'),

    // Данные организации
    'company' => [
        'inn' => env('ATOL_COMPANY_INN'),
        'name' => env('ATOL_COMPANY_NAME', 'ООО "Ресторан"'),
        'email' => env('ATOL_COMPANY_EMAIL'),
        'payment_address' => env('ATOL_PAYMENT_ADDRESS'), // Адрес точки продаж
    ],

    // Система налогообложения (СНО)
    // osn - Общая
    // usn_income - УСН Доходы
    // usn_income_outcome - УСН Доходы минус расходы
    // envd - ЕНВД
    // esn - ЕСН
    // patent - Патент
    'sno' => env('ATOL_SNO', 'osn'),

    // НДС по умолчанию
    // vat0 - НДС 0%
    // vat10 - НДС 10%
    // vat20 - НДС 20%
    // vat110 - расчётный НДС 10/110
    // vat120 - расчётный НДС 20/120
    // none - без НДС
    'default_vat' => env('ATOL_DEFAULT_VAT', 'none'),

    // Тип платежа по умолчанию
    // full_prepayment - предоплата 100%
    // prepayment - предоплата
    // advance - аванс
    // full_payment - полный расчёт
    // partial_payment - частичный расчёт и кредит
    // credit - передача в кредит
    // credit_payment - оплата кредита
    'payment_object' => env('ATOL_PAYMENT_OBJECT', 'commodity'),
    'payment_method' => env('ATOL_PAYMENT_METHOD', 'full_payment'),

    // Callback URL для получения результата
    'callback_url' => env('ATOL_CALLBACK_URL'),

    // Таймаут запроса (секунды)
    'timeout' => env('ATOL_TIMEOUT', 30),

    // Время жизни токена (секунды)
    'token_ttl' => env('ATOL_TOKEN_TTL', 86400), // 24 часа
];
