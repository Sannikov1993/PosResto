<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WaiterApiController;

// =====================================================
// ДОСТАВКА (расширенный модуль)
// =====================================================
Route::prefix('delivery')->middleware(['auth.api_token', 'throttle:60,1'])->group(function () {
    // Расчёт стоимости доставки
    Route::post('/calculate', [\App\Http\Controllers\Api\DeliveryController::class, 'detectZone']);

    // Заказы доставки
    Route::get('/orders', [\App\Http\Controllers\Api\DeliveryController::class, 'orders']);
    Route::post('/orders', [\App\Http\Controllers\Api\DeliveryController::class, 'createOrder']);
    Route::get('/orders/{order}', [\App\Http\Controllers\Api\DeliveryController::class, 'showOrder']);
    Route::patch('/orders/{order}/status', [\App\Http\Controllers\Api\DeliveryController::class, 'updateStatus']);
    Route::post('/orders/{order}/assign-courier', [\App\Http\Controllers\Api\DeliveryController::class, 'assignCourier']);

    // Курьеры
    Route::get('/couriers', [\App\Http\Controllers\Api\DeliveryController::class, 'couriers']);
    Route::patch('/couriers/{user}/status', [\App\Http\Controllers\Api\DeliveryController::class, 'updateCourierStatus']);

    // Зоны доставки
    Route::get('/zones', [\App\Http\Controllers\Api\DeliveryController::class, 'zones']);
    Route::post('/zones', [\App\Http\Controllers\Api\DeliveryController::class, 'createZone']);
    Route::put('/zones/{zone}', [\App\Http\Controllers\Api\DeliveryController::class, 'updateZone']);
    Route::delete('/zones/{zone}', [\App\Http\Controllers\Api\DeliveryController::class, 'deleteZone']);

    // Геокодирование (Yandex)
    Route::post('/detect-zone', [\App\Http\Controllers\Api\DeliveryController::class, 'detectZone']);
    Route::get('/suggest-address', [\App\Http\Controllers\Api\DeliveryController::class, 'suggestAddress']);
    Route::post('/geocode', [\App\Http\Controllers\Api\DeliveryController::class, 'geocode']);

    // Умное назначение курьера
    Route::get('/orders/{order}/suggest-courier', [\App\Http\Controllers\Api\DeliveryController::class, 'suggestCourier']);
    Route::get('/orders/{order}/ranked-couriers', [\App\Http\Controllers\Api\DeliveryController::class, 'rankedCouriers']);
    Route::post('/orders/{order}/auto-assign', [\App\Http\Controllers\Api\DeliveryController::class, 'autoAssignCourier']);

    // Настройки
    Route::get('/settings', [\App\Http\Controllers\Api\DeliveryController::class, 'settings']);
    Route::put('/settings', [\App\Http\Controllers\Api\DeliveryController::class, 'updateSettings']);

    // Аналитика
    Route::get('/analytics', [\App\Http\Controllers\Api\DeliveryController::class, 'analytics']);

    // Карта курьеров
    Route::get('/map-data', [\App\Http\Controllers\Api\DeliveryController::class, 'mapData']);

    // Проблемы доставки
    Route::get('/problems', [\App\Http\Controllers\Api\DeliveryController::class, 'problems']);
    Route::post('/orders/{order}/problem', [\App\Http\Controllers\Api\DeliveryController::class, 'createProblem']);
    Route::patch('/problems/{problem}/resolve', [\App\Http\Controllers\Api\DeliveryController::class, 'resolveProblem']);
    Route::delete('/problems/{problem}', [\App\Http\Controllers\Api\DeliveryController::class, 'cancelProblem']);
});

// =====================================================
// LIVE-ТРЕКИНГ КУРЬЕРА
// =====================================================
Route::prefix('tracking')->middleware('throttle:60,1')->group(function () {
    // Публичные эндпоинты (по токену, без авторизации)
    Route::get('/{token}/data', [\App\Http\Controllers\Api\LiveTrackingController::class, 'getTrackingData']);
    Route::get('/{token}/stream', [\App\Http\Controllers\Api\LiveTrackingController::class, 'stream']);
    Route::get('/{token}/poll', [\App\Http\Controllers\Api\LiveTrackingController::class, 'poll']);
});

// Обновление позиции курьера (требует авторизации)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/courier/location', [\App\Http\Controllers\Api\LiveTrackingController::class, 'updateCourierLocation']);
});

// =====================================================
// PWA ОФИЦИАНТА
// =====================================================
Route::prefix('waiter')->middleware('auth.api_token')->group(function () {
    // Столы и зоны
    Route::get('/tables', [WaiterApiController::class, 'tables']);
    Route::get('/table/{id}', [WaiterApiController::class, 'table']);

    // Меню
    Route::get('/menu/categories', [WaiterApiController::class, 'menuCategories']);
    Route::get('/menu/category/{id}/products', [WaiterApiController::class, 'categoryProducts']);

    // Управление заказом
    Route::post('/order/add-item', [WaiterApiController::class, 'addOrderItem'])->middleware('permission:orders.create');
    Route::patch('/order/item/{id}', [WaiterApiController::class, 'updateOrderItem'])->middleware('permission:orders.edit');
    Route::delete('/order/item/{id}', [WaiterApiController::class, 'deleteOrderItem'])->middleware('permission:orders.edit');
    Route::post('/order/{id}/send-kitchen', [WaiterApiController::class, 'sendToKitchen'])->middleware('permission:orders.edit');
    Route::post('/order/{id}/serve', [WaiterApiController::class, 'serveOrder'])->middleware('permission:orders.edit');
    Route::post('/order/{id}/pay', [WaiterApiController::class, 'payOrder'])->middleware('permission:orders.edit');

    // Список заказов
    Route::get('/orders', [WaiterApiController::class, 'orders']);

    // Статистика профиля
    Route::get('/profile/stats', [WaiterApiController::class, 'profileStats']);
});
