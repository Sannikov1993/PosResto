<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Yandex Services
    |--------------------------------------------------------------------------
    */
    'yandex' => [
        'geocoder_key' => env('YANDEX_GEOCODER_KEY', ''),
        'js_api_key' => env('YANDEX_JS_API_KEY', ''),
        'city' => env('YANDEX_CITY', 'Москва'),
        'restaurant_lat' => env('RESTAURANT_LAT'),
        'restaurant_lng' => env('RESTAURANT_LNG'),
        // Yandex Vision OCR
        'vision_api_key' => env('YANDEX_VISION_API_KEY', ''),
        'folder_id' => env('YANDEX_FOLDER_ID', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot (для заказов и клиентов)
    |--------------------------------------------------------------------------
    */
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'), // For deep links
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'admin_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
        // Staff Bot (для уведомлений сотрудникам)
        'staff_bot_token' => env('TELEGRAM_STAFF_BOT_TOKEN'),
        'staff_bot_username' => env('TELEGRAM_STAFF_BOT_USERNAME'),
        'staff_bot_webhook_secret' => env('TELEGRAM_STAFF_BOT_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    */
    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', env('APP_URL')),
    ],

];
