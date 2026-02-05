<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\KitchenStationController;
use App\Http\Controllers\Api\KitchenDeviceController;
use App\Http\Controllers\Api\OrderBoardController;
use App\Http\Controllers\Api\StopListController;

// =====================================================
// ЦЕХА КУХНИ (Kitchen Stations)
// =====================================================
Route::prefix('kitchen-stations')->middleware('auth.api_token')->group(function () {
    Route::get('/', [KitchenStationController::class, 'index']);
    Route::get('/active', [KitchenStationController::class, 'active']);
    Route::post('/', [KitchenStationController::class, 'store']);
    Route::post('/reorder', [KitchenStationController::class, 'reorder']);
    Route::get('/{kitchenStation}', [KitchenStationController::class, 'show']);
    Route::put('/{kitchenStation}', [KitchenStationController::class, 'update']);
    Route::delete('/{kitchenStation}', [KitchenStationController::class, 'destroy']);
    Route::patch('/{kitchenStation}/toggle', [KitchenStationController::class, 'toggle']);
});

// =====================================================
// БАР (Bar Panel for POS)
// =====================================================
Route::prefix('bar')->middleware('auth.api_token')->group(function () {
    Route::get('/check', [KitchenStationController::class, 'getBar']);
    Route::get('/orders', [KitchenStationController::class, 'getBarOrders']);
    Route::post('/item-status', [KitchenStationController::class, 'updateBarItemStatus']);
});

// =====================================================
// УСТРОЙСТВА КУХНИ (Kitchen Devices)
// =====================================================
Route::prefix('kitchen-devices')->group(function () {
    // Для планшетов (без авторизации пользователя, используется device_id)
    Route::post('/link', [KitchenDeviceController::class, 'link']);
    Route::get('/my-station', [KitchenDeviceController::class, 'myStation']);
    Route::post('/change-station', [KitchenDeviceController::class, 'changeStation']);
    Route::get('/orders', [KitchenDeviceController::class, 'orders']);
    Route::get('/orders/count-by-dates', [KitchenDeviceController::class, 'countByDates']);
    Route::patch('/orders/{order}/status', [KitchenDeviceController::class, 'updateOrderStatus']);
    Route::patch('/order-items/{item}/status', [KitchenDeviceController::class, 'updateItemStatus']);
    Route::post('/orders/{order}/call-waiter', [KitchenDeviceController::class, 'callWaiter']);

    // Для админки (требуют авторизации)
    Route::middleware('auth.api_token')->group(function () {
        Route::get('/', [KitchenDeviceController::class, 'index']);
        Route::post('/', [KitchenDeviceController::class, 'store']);
        Route::put('/{kitchenDevice}', [KitchenDeviceController::class, 'update']);
        Route::delete('/{kitchenDevice}', [KitchenDeviceController::class, 'destroy']);
        Route::post('/{kitchenDevice}/regenerate-code', [KitchenDeviceController::class, 'regenerateLinkingCode']);
        Route::post('/{kitchenDevice}/unlink', [KitchenDeviceController::class, 'unlink']);
    });
});

// =====================================================
// ТАБЛО ЗАКАЗОВ (Order Board) — требует авторизацию для защиты данных
// =====================================================
Route::middleware(['auth.api_token', 'throttle:60,1'])->get('/order-board', [OrderBoardController::class, 'index']);

// =====================================================
// СТОП-ЛИСТ
// =====================================================
Route::prefix('stop-list')->middleware('auth.api_token')->group(function () {
    Route::get('/', [StopListController::class, 'index']);
    Route::post('/', [StopListController::class, 'store']);
    Route::put('/{dish}', [StopListController::class, 'update']);
    Route::delete('/{dish}', [StopListController::class, 'destroy']);
    Route::get('/dish-ids', [StopListController::class, 'dishIds']);
    Route::get('/search-dishes', [StopListController::class, 'searchDishes']);
});
