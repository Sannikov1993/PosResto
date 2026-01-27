<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Рабочие часы ресторана
    |--------------------------------------------------------------------------
    */
    'work_hours' => [
        'start' => env('RESTAURANT_WORK_START', 10), // 10:00
        'end' => env('RESTAURANT_WORK_END', 22),     // 22:00
    ],

    /*
    |--------------------------------------------------------------------------
    | Настройки бронирования
    |--------------------------------------------------------------------------
    */
    'reservation_slot_step' => env('RESERVATION_SLOT_STEP', 30), // минуты
    'reservation_min_duration' => env('RESERVATION_MIN_DURATION', 30), // минуты
    'reservation_max_duration' => env('RESERVATION_MAX_DURATION', 240), // минуты (4 часа)
    'reservation_default_duration' => env('RESERVATION_DEFAULT_DURATION', 120), // минуты (2 часа)
];
