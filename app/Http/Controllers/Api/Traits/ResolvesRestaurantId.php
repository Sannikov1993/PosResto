<?php

namespace App\Http\Controllers\Api\Traits;

use Illuminate\Http\Request;
use App\Models\Restaurant;

trait ResolvesRestaurantId
{
    /**
     * Get restaurant ID from authenticated user
     * With tenant isolation check for explicit restaurant_id parameter
     */
    protected function getRestaurantId(Request $request): int
    {
        // Используем auth()->user() т.к. middleware AuthenticateApiToken
        // устанавливает пользователя через Auth::setUser()
        $user = auth()->user();

        // Если есть явный параметр restaurant_id
        if ($request->has('restaurant_id')) {
            // Суперадмин может выбрать любой ресторан
            if ($user && $user->isSuperAdmin()) {
                return (int) $request->input('restaurant_id');
            }

            // Обычный пользователь - проверяем принадлежность к tenant
            if ($user && $user->tenant_id) {
                $restaurant = Restaurant::where('id', $request->input('restaurant_id'))
                    ->where('tenant_id', $user->tenant_id)
                    ->first();
                if ($restaurant) {
                    return $restaurant->id;
                }
            }
        }

        // Берём restaurant_id из авторизованного пользователя
        if ($user && $user->restaurant_id) {
            return $user->restaurant_id;
        }

        // Для защищённых роутов с middleware auth - это ошибка
        // Для публичных роутов (гостевое меню) - может быть допустимо
        return $request->input('restaurant_id', 1);
    }
}
