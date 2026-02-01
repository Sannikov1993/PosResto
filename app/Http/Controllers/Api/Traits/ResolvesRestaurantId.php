<?php

namespace App\Http\Controllers\Api\Traits;

use Illuminate\Http\Request;
use App\Models\Restaurant;
use App\Exceptions\TenantNotSetException;

trait ResolvesRestaurantId
{
    /**
     * Get restaurant ID from authenticated user
     * With tenant isolation check for explicit restaurant_id parameter
     *
     * @throws TenantNotSetException если restaurant_id не может быть определён
     */
    protected function getRestaurantId(Request $request): int
    {
        $user = auth()->user();

        // Если есть явный параметр restaurant_id
        if ($request->has('restaurant_id')) {
            $requestedId = (int) $request->input('restaurant_id');

            // Суперадмин может выбрать любой ресторан
            if ($user && ($user->is_superadmin ?? false)) {
                return $requestedId;
            }

            // Tenant owner может выбрать любой ресторан своей сети
            if ($user && ($user->is_tenant_owner ?? false) && $user->tenant_id) {
                $restaurant = Restaurant::where('id', $requestedId)
                    ->where('tenant_id', $user->tenant_id)
                    ->first();
                if ($restaurant) {
                    return $restaurant->id;
                }
            }

            // Обычный пользователь — только свой ресторан
            if ($user && $user->restaurant_id === $requestedId) {
                return $requestedId;
            }

            // Запрошенный ресторан недоступен — игнорируем параметр
        }

        // Берём restaurant_id из авторизованного пользователя
        if ($user && $user->restaurant_id) {
            return $user->restaurant_id;
        }

        // Пробуем из TenantManager (установленного middleware)
        $tenantManager = app(\App\Services\TenantManager::class);
        if ($tenantManager->isSet()) {
            return $tenantManager->get();
        }

        // Для публичных роутов (гостевое меню) — требуем явный restaurant_id
        if ($request->has('restaurant_id')) {
            return (int) $request->input('restaurant_id');
        }

        // Не удалось определить restaurant_id — exception
        throw new TenantNotSetException(
            'Restaurant ID could not be determined. ' .
            'Please authenticate or provide restaurant_id parameter.'
        );
    }

    /**
     * Get restaurant ID or null (для опциональных случаев)
     */
    protected function getRestaurantIdOrNull(Request $request): ?int
    {
        try {
            return $this->getRestaurantId($request);
        } catch (TenantNotSetException) {
            return null;
        }
    }
}
