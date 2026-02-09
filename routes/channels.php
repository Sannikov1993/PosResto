<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Авторизация: пользователь получает доступ к каналам того ресторана,
| в котором он сейчас работает (user.restaurant_id из БД).
|
| Переключение ресторана: POST /api/tenant/restaurants/{id}/switch
| обновляет user.restaurant_id → затем фронтенд переподключает WS.
|
*/

$authorizeRestaurant = function (User $user, $restaurantId) {
    return (int) $user->restaurant_id === (int) $restaurantId;
};

Broadcast::channel('restaurant.{restaurantId}', $authorizeRestaurant);
Broadcast::channel('restaurant.{restaurantId}.orders', $authorizeRestaurant);
Broadcast::channel('restaurant.{restaurantId}.kitchen', $authorizeRestaurant);
Broadcast::channel('restaurant.{restaurantId}.delivery', $authorizeRestaurant);
Broadcast::channel('restaurant.{restaurantId}.tables', $authorizeRestaurant);
Broadcast::channel('restaurant.{restaurantId}.reservations', $authorizeRestaurant);
Broadcast::channel('restaurant.{restaurantId}.bar', $authorizeRestaurant);
Broadcast::channel('restaurant.{restaurantId}.cash', $authorizeRestaurant);
Broadcast::channel('restaurant.{restaurantId}.global', $authorizeRestaurant);
