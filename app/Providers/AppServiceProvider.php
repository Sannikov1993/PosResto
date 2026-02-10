<?php

namespace App\Providers;

use App\Domain\Reservation\Events\ReservationEvent;
use App\Domain\Reservation\Listeners\LogReservationActivity;
use App\Domain\Reservation\Listeners\SendReservationNotifications;
use App\Domain\Reservation\Listeners\UpdateCustomerStats;
use App\Models\Order;
use App\Models\Table;
use App\Models\CashShift;
use App\Observers\OrderObserver;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Регистрируем TenantManager как singleton
        $this->app->singleton(TenantManager::class, function () {
            return new TenantManager();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Регистрация Observer для автоматических уведомлений
        Order::observe(OrderObserver::class);

        // Регистрация Reservation Domain Events
        $this->registerReservationEvents();

        // Регистрация Policy для Reservation
        $this->registerPolicies();

        // Explicit Route Model Binding для multi-tenant моделей
        $this->registerExplicitModelBindings();

        // Slow query logging (dev only)
        if ($this->app->isLocal()) {
            DB::listen(function ($query) {
                if ($query->time > 100) { // >100ms
                    Log::channel('single')->warning('Slow query', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time_ms' => $query->time,
                    ]);
                }
            });
        }
    }

    /**
     * Регистрация Policy для моделей.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(\App\Models\Reservation::class, \App\Policies\ReservationPolicy::class);
        Gate::policy(\App\Models\Order::class, \App\Policies\OrderPolicy::class);
        Gate::policy(\App\Models\User::class, \App\Policies\StaffPolicy::class);
        Gate::policy(\App\Models\CashShift::class, \App\Policies\FinancePolicy::class);
    }

    /**
     * Регистрация слушателей для Reservation Domain Events.
     */
    protected function registerReservationEvents(): void
    {
        // Log all reservation events (for audit trail)
        Event::listen(
            ReservationEvent::class,
            LogReservationActivity::class
        );

        // Subscribe notification handlers
        Event::subscribe(SendReservationNotifications::class);

        // Subscribe customer stats updates
        Event::subscribe(UpdateCustomerStats::class);
    }

    /**
     * Регистрация explicit route model binding для multi-tenant моделей.
     *
     * Это решает проблему "курицы и яйца":
     * - Global Scope требует TenantManager
     * - Route Model Binding происходит ДО middleware
     * - Middleware SetRestaurant устанавливает TenantManager
     *
     * Решение: загружаем модели БЕЗ global scope.
     * SetRestaurant middleware потом:
     * 1. Получит restaurant_id из уже загруженной модели
     * 2. Проверит права доступа пользователя
     * 3. Установит TenantManager
     */
    protected function registerExplicitModelBindings(): void
    {
        // Table binding - используется в POS routes: /pos/table/{table}/...
        Route::bind('table', function ($value) {
            $table = Table::withoutGlobalScopes()->find($value);

            if (!$table) {
                abort(404, 'Table not found');
            }

            // НЕ устанавливаем TenantManager здесь!
            // Это сделает SetRestaurant middleware после проверки доступа.

            return $table;
        });

        // Order binding - используется в POS и API routes
        Route::bind('order', function ($value) {
            $order = Order::withoutGlobalScopes()->find($value);

            if (!$order) {
                abort(404, 'Order not found');
            }

            return $order;
        });

        // CashShift binding - используется в Finance routes: /finance/shifts/{shift}
        Route::bind('shift', function ($value) {
            $shift = CashShift::withoutGlobalScopes()->find($value);

            if (!$shift) {
                abort(404, 'Shift not found');
            }

            return $shift;
        });
    }
}
