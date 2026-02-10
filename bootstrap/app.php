<?php

use App\Domain\Reservation\Exceptions\ReservationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

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

        // Обработка pending webhooks - каждую минуту
        $schedule->command('webhooks:process --limit=50')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\Cors::class);
        $middleware->append(\App\Http\Middleware\MeasureResponseTime::class);

        // Middleware для установки текущего тенанта (франшиза)
        $middleware->appendToGroup('web', \App\Http\Middleware\SetTenantScope::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\SetTenantScope::class);

        // Middleware для установки текущего ресторана (restaurant_id)
        $middleware->appendToGroup('web', \App\Http\Middleware\SetRestaurant::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\SetRestaurant::class);

        // ✅ Проверка активности пользователя для API
        $middleware->appendToGroup('api', \App\Http\Middleware\CheckUserActive::class);

        // Регистрация middleware для API токенов
        $middleware->alias([
            'auth.api_token' => \App\Http\Middleware\AuthenticateApiToken::class,
            'check.user.active' => \App\Http\Middleware\CheckUserActive::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'interface' => \App\Http\Middleware\CheckInterfaceAccess::class,

            // Public API v1 middleware
            'api.auth' => \App\Http\Middleware\AuthenticateApiClient::class,
            'api.scope' => \App\Http\Middleware\CheckApiScope::class,
            'api.rate' => \App\Http\Middleware\ApiRateLimiter::class,
            'api.log' => \App\Http\Middleware\ApiRequestLogger::class,
            'api.idempotency' => \App\Http\Middleware\ApiIdempotency::class,
        ]);

        // Исключаем POS маршруты из проверки CSRF
        $middleware->validateCsrfTokens(except: [
            'pos/*',
            'api/*',
            'broadcasting/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle ReservationException and its subclasses
        $exceptions->render(function (ReservationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('pos/*')) {
                return $e->toResponse();
            }
            return null;
        });

        // Стандартизированные API-ответы для всех исключений
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('pos/*')) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'success' => false,
                    'message' => "Запись не найдена ({$model})",
                ], 404);
            }
            return null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('pos/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Маршрут не найден',
                ], 404);
            }
            return null;
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('pos/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
            return null;
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('pos/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Недостаточно прав',
                ], 403);
            }
            return null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('pos/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Необходима авторизация',
                ], 401);
            }
            return null;
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('pos/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Слишком много запросов. Попробуйте позже.',
                ], 429);
            }
            return null;
        });

        // Fallback для всех остальных исключений в API
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('pos/*')) {
                $statusCode = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException
                    ? $e->getStatusCode()
                    : 500;
                $isDebug = config('app.debug');
                return response()->json([
                    'success' => false,
                    'message' => ($isDebug || $statusCode !== 500) ? $e->getMessage() : 'Внутренняя ошибка сервера',
                    ...($isDebug ? ['exception' => get_class($e), 'trace' => array_slice($e->getTrace(), 0, 5)] : []),
                ], $statusCode);
            }
            return null;
        });
    })->create();