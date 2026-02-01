<?php

namespace App\Traits;

use App\Services\TenantManager;
use App\Exceptions\TenantNotSetException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait для моделей, принадлежащих ресторану
 *
 * СТРОГИЙ РЕЖИМ: Для HTTP-запросов требует установленный TenantManager.
 * Для консольных команд и очередей — работает без ограничений.
 *
 * Функционал:
 * 1. Global Scope — автоматическая фильтрация всех запросов по restaurant_id
 * 2. Auto-fill — автоматическое заполнение restaurant_id при создании
 * 3. Валидация — exception если restaurant_id не установлен при сохранении
 *
 * Использование:
 *   class Order extends Model
 *   {
 *       use BelongsToRestaurant;
 *   }
 *
 * Отключение Global Scope:
 *   Order::withoutGlobalScope('restaurant')->get();   // Для конкретного запроса
 *   Order::withoutRestaurantScope()->get();           // Алиас
 *   Order::forRestaurant($restaurantId)->get();       // Для конкретного ресторана
 */
trait BelongsToRestaurant
{
    /**
     * Boot trait
     */
    public static function bootBelongsToRestaurant(): void
    {
        // Global Scope — автоматическая фильтрация
        static::addGlobalScope('restaurant', function (Builder $builder) {
            $tenantManager = app(TenantManager::class);

            // Если tenant установлен — фильтруем
            if ($tenantManager->isSet()) {
                $builder->where(
                    $builder->getModel()->getTable() . '.restaurant_id',
                    $tenantManager->get()
                );
                return;
            }

            // Для консольных команд и очередей — не требуем tenant
            if (app()->runningInConsole()) {
                return;
            }

            // Для HTTP-запросов без tenant — СТРОГИЙ РЕЖИМ
            // Логируем и выбрасываем exception
            \Illuminate\Support\Facades\Log::error(
                'BelongsToRestaurant: TenantManager not set for HTTP request',
                [
                    'model' => $builder->getModel()->getTable(),
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                    'user_id' => auth()->id(),
                ]
            );

            throw new TenantNotSetException(
                'Restaurant context required. TenantManager not initialized for model: ' .
                get_class($builder->getModel())
            );
        });

        // Auto-fill при создании
        static::creating(function (Model $model) {
            if (!empty($model->restaurant_id)) {
                return; // Уже установлен — не трогаем
            }

            $tenantManager = app(TenantManager::class);

            if ($tenantManager->isSet()) {
                $model->restaurant_id = $tenantManager->get();
                return;
            }

            // Пробуем определить из связанных моделей
            $resolved = static::resolveRestaurantIdFromRelations($model);
            if ($resolved !== null) {
                $model->restaurant_id = $resolved;
                return;
            }

            // Для HTTP-запросов — exception
            if (!app()->runningInConsole()) {
                throw new TenantNotSetException(
                    'Cannot create ' . get_class($model) . ' without restaurant_id. ' .
                    'TenantManager not set and could not resolve from relations.'
                );
            }

            // Для консоли — warning
            \Illuminate\Support\Facades\Log::warning(
                'BelongsToRestaurant: Creating model without restaurant_id',
                [
                    'model' => get_class($model),
                    'attributes' => $model->getAttributes(),
                ]
            );
        });

        // Валидация при сохранении
        static::saving(function (Model $model) {
            // Для HTTP-запросов проверяем наличие restaurant_id
            if (!app()->runningInConsole() && empty($model->restaurant_id)) {
                throw new TenantNotSetException(
                    'Cannot save ' . get_class($model) . ' without restaurant_id'
                );
            }
        });
    }

    /**
     * Попытаться определить restaurant_id из связанных моделей
     */
    protected static function resolveRestaurantIdFromRelations(Model $model): ?int
    {
        // Из table_id
        if (!empty($model->table_id)) {
            $table = \App\Models\Table::withoutGlobalScope('restaurant')->find($model->table_id);
            if ($table) {
                return $table->restaurant_id;
            }
        }

        // Из order_id
        if (!empty($model->order_id)) {
            $order = \App\Models\Order::withoutGlobalScope('restaurant')->find($model->order_id);
            if ($order) {
                return $order->restaurant_id;
            }
        }

        // Из category_id
        if (!empty($model->category_id)) {
            $category = \App\Models\Category::withoutGlobalScope('restaurant')->find($model->category_id);
            if ($category) {
                return $category->restaurant_id;
            }
        }

        // Из zone_id
        if (!empty($model->zone_id)) {
            $zone = \App\Models\Zone::withoutGlobalScope('restaurant')->find($model->zone_id);
            if ($zone) {
                return $zone->restaurant_id;
            }
        }

        // Из user (created_by, user_id, waiter_id)
        foreach (['created_by', 'user_id', 'waiter_id', 'cashier_id'] as $userField) {
            if (!empty($model->$userField)) {
                $user = \App\Models\User::find($model->$userField);
                if ($user && $user->restaurant_id) {
                    return $user->restaurant_id;
                }
            }
        }

        return null;
    }

    /**
     * Scope: без фильтрации по ресторану (ОСТОРОЖНО!)
     */
    public function scopeWithoutRestaurantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('restaurant');
    }

    /**
     * Scope: для конкретного ресторана
     * Безопасный способ запросить данные конкретного ресторана
     */
    public function scopeForRestaurant(Builder $query, int $restaurantId): Builder
    {
        return $query->withoutGlobalScope('restaurant')
            ->where($this->getTable() . '.restaurant_id', $restaurantId);
    }

    /**
     * Найти запись по ID с проверкой принадлежности к ресторану
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findForRestaurant(int $id, int $restaurantId): ?static
    {
        return static::forRestaurant($restaurantId)->find($id);
    }

    /**
     * Найти запись по ID с проверкой принадлежности к ресторану или exception
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findForRestaurantOrFail(int $id, int $restaurantId): static
    {
        return static::forRestaurant($restaurantId)->findOrFail($id);
    }

    /**
     * Связь с рестораном
     */
    public function restaurant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Restaurant::class);
    }

    /**
     * Проверить, принадлежит ли модель указанному ресторану
     */
    public function belongsToRestaurant(int $restaurantId): bool
    {
        return $this->restaurant_id === $restaurantId;
    }

    /**
     * Проверить, принадлежит ли модель текущему ресторану
     */
    public function belongsToCurrentRestaurant(): bool
    {
        $tenantManager = app(TenantManager::class);

        if (!$tenantManager->isSet()) {
            return false; // Нет текущего ресторана — не принадлежит
        }

        return $this->restaurant_id === $tenantManager->get();
    }

    /**
     * Требовать принадлежность к текущему ресторану или abort(404)
     */
    public function requireCurrentRestaurant(): static
    {
        if (!$this->belongsToCurrentRestaurant()) {
            abort(404, 'Resource not found');
        }
        return $this;
    }
}
