<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Напоминания о сменах - каждые 10 минут
        $schedule->command('shifts:send-reminders')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Напоминания об отметках (clock in/out) - каждые 5 минут
        $schedule->command('clock:send-reminders')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\Cors::class);

        // Middleware для установки текущего тенанта
        $middleware->appendToGroup('web', \App\Http\Middleware\SetTenantScope::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\SetTenantScope::class);

        // ✅ Проверка активности пользователя для API
        $middleware->appendToGroup('api', \App\Http\Middleware\CheckUserActive::class);

        // Регистрация middleware для API токенов
        $middleware->alias([
            'auth.api_token' => \App\Http\Middleware\AuthenticateApiToken::class,
            'check.user.active' => \App\Http\Middleware\CheckUserActive::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
        ]);

        // Исключаем POS маршруты из проверки CSRF
        $middleware->validateCsrfTokens(except: [
            'pos/*',
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();