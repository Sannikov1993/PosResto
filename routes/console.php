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
