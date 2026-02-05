<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Автозакрытие "забытых" смен каждые 8 часов (смены старше 18ч)
Schedule::command('attendance:close-stale-sessions --hours=18')
    ->cron('0 */8 * * *') // 00:00, 08:00, 16:00
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/attendance-auto-close.log'));

// Синхронизация отметок с биометрических устройств каждую минуту
Schedule::command('attendance:sync')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/attendance-sync.log'));

// Напоминания о бронировании за 2 часа до визита
Schedule::command('reservations:send-reminders --hours=2')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/reservation-reminders.log'));

// Автоматическая отметка no_show для просроченных бронирований
Schedule::command('reservations:mark-no-show')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/reservation-no-show.log'));

// Retry неудавшихся уведомлений (exponential backoff)
Schedule::command('notifications:retry')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/notification-retry.log'));
