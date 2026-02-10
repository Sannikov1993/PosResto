<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WaiterApiController;
use App\Http\Controllers\Api\DeliveryOrderController;
use App\Http\Controllers\Api\DeliveryZoneController;
use App\Http\Controllers\Api\DeliveryCourierController;
use App\Http\Controllers\Api\DeliverySettingsController;

// =====================================================
// ДОСТАВКА (расширенный модуль)
// =====================================================
Route::prefix('delivery')->middleware(['auth.api_token', 'throttle:60,1'])->group(function () {
    // Расчёт стоимости доставки
    Route::post('/calculate', [DeliveryZoneController::class, 'detectZone']);

    // Заказы доставки
    Route::get('/orders', [DeliveryOrderController::class, 'orders']);
    Route::post('/orders', [DeliveryOrderController::class, 'createOrder']);
    Route::get('/orders/{order}', [DeliveryOrderController::class, 'showOrder']);
    Route::patch('/orders/{order}/status', [DeliveryOrderController::class, 'updateStatus']);
    Route::post('/orders/{order}/assign-courier', [DeliveryOrderController::class, 'assignCourier']);

    // Курьеры
    Route::get('/couriers', [DeliveryCourierController::class, 'couriers']);
    Route::patch('/couriers/{user}/status', [DeliveryCourierController::class, 'updateCourierStatus']);

    // Зоны доставки
    Route::get('/zones', [DeliveryZoneController::class, 'zones']);
    Route::post('/zones', [DeliveryZoneController::class, 'createZone']);
    Route::put('/zones/{zone}', [DeliveryZoneController::class, 'updateZone']);
    Route::delete('/zones/{zone}', [DeliveryZoneController::class, 'deleteZone']);

    // Геокодирование (Yandex)
    Route::post('/detect-zone', [DeliveryZoneController::class, 'detectZone']);
    Route::get('/suggest-address', [DeliveryZoneController::class, 'suggestAddress']);
    Route::post('/geocode', [DeliveryZoneController::class, 'geocode']);

    // Умное назначение курьера
    Route::get('/orders/{order}/suggest-courier', [DeliveryCourierController::class, 'suggestCourier']);
    Route::get('/orders/{order}/ranked-couriers', [DeliveryCourierController::class, 'rankedCouriers']);
    Route::post('/orders/{order}/auto-assign', [DeliveryCourierController::class, 'autoAssignCourier']);

    // Настройки
    Route::get('/settings', [DeliverySettingsController::class, 'settings']);
    Route::put('/settings', [DeliverySettingsController::class, 'updateSettings']);

    // Аналитика
    Route::get('/analytics', [DeliverySettingsController::class, 'analytics']);

    // Карта курьеров
    Route::get('/map-data', [DeliveryCourierController::class, 'mapData']);

    // Проблемы доставки
    Route::get('/problems', [DeliverySettingsController::class, 'problems']);
    Route::post('/orders/{order}/problem', [DeliverySettingsController::class, 'createProblem']);
    Route::patch('/problems/{problem}/resolve', [DeliverySettingsController::class, 'resolveProblem']);
    Route::delete('/problems/{problem}', [DeliverySettingsController::class, 'cancelProblem']);
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
