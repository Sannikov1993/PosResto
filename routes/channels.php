<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Основной канал ресторана (для общих событий)
Broadcast::channel('restaurant.{restaurantId}', function (User $user, int $restaurantId) {
    return $user->restaurant_id === $restaurantId;
});

// Канал заказов
Broadcast::channel('restaurant.{restaurantId}.orders', function (User $user, int $restaurantId) {
    return $user->restaurant_id === $restaurantId;
});

// Канал кухни
Broadcast::channel('restaurant.{restaurantId}.kitchen', function (User $user, int $restaurantId) {
    return $user->restaurant_id === $restaurantId;
});

// Канал доставки
Broadcast::channel('restaurant.{restaurantId}.delivery', function (User $user, int $restaurantId) {
    return $user->restaurant_id === $restaurantId;
});

// Канал столов
Broadcast::channel('restaurant.{restaurantId}.tables', function (User $user, int $restaurantId) {
    return $user->restaurant_id === $restaurantId;
});

// Канал бронирований
Broadcast::channel('restaurant.{restaurantId}.reservations', function (User $user, int $restaurantId) {
    return $user->restaurant_id === $restaurantId;
});

// Канал бара
Broadcast::channel('restaurant.{restaurantId}.bar', function (User $user, int $restaurantId) {
    return $user->restaurant_id === $restaurantId;
});

// Канал кассы
Broadcast::channel('restaurant.{restaurantId}.cash', function (User $user, int $restaurantId) {
    return $user->restaurant_id === $restaurantId;
});

// Канал глобальных событий (стоп-лист, настройки)
Broadcast::channel('restaurant.{restaurantId}.global', function (User $user, int $restaurantId) {
    return $user->restaurant_id === $restaurantId;
});
