<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Pos\TableOrderController;

// =====================================================
// СТОЛЫ (требуется авторизация для tenant-изоляции)
// =====================================================
Route::prefix('tables')->middleware('auth.api_token')->group(function () {
    Route::get('/floor-plan', [TableController::class, 'floorPlan']);
    Route::post('/layout', [TableController::class, 'saveLayout']);
    Route::get('/zones', [TableController::class, 'zones']);
    Route::post('/zones', [TableController::class, 'storeZone']);
    Route::put('/zones/{zone}', [TableController::class, 'updateZone']);
    Route::delete('/zones/{zone}', [TableController::class, 'destroyZone']);
    Route::get('/', [TableController::class, 'index']);
    Route::post('/', [TableController::class, 'store']);
    Route::get('/{table}', [TableController::class, 'show']);
    Route::put('/{table}', [TableController::class, 'update']);
    Route::delete('/{table}', [TableController::class, 'destroy']);
    Route::patch('/{table}/status', [TableController::class, 'updateStatus']);
    // POS Table Order Data (для работы с заказами на столе)
    Route::get('/{table}/order-data', [TableOrderController::class, 'getData']);
});

// =====================================================
// БРОНИРОВАНИЕ (требуется авторизация для tenant-изоляции)
// =====================================================
Route::prefix('reservations')->middleware('auth.api_token')->group(function () {
    Route::get('/', [ReservationController::class, 'index']);
    Route::post('/', [ReservationController::class, 'store']);
    Route::get('/calendar', [ReservationController::class, 'calendar']);
    Route::get('/stats', [ReservationController::class, 'stats']);
    Route::get('/business-date', [ReservationController::class, 'businessDate']);
    Route::get('/available-slots', [ReservationController::class, 'availableSlots']);
    Route::get('/{reservation}', [ReservationController::class, 'show']);
    Route::put('/{reservation}', [ReservationController::class, 'update']);
    Route::delete('/{reservation}', [ReservationController::class, 'destroy']);
    Route::post('/{reservation}/confirm', [ReservationController::class, 'confirm']);
    Route::post('/{reservation}/cancel', [ReservationController::class, 'cancel']);
    Route::post('/{reservation}/seat', [ReservationController::class, 'seat']);
    Route::post('/{reservation}/seat-with-order', [ReservationController::class, 'seatWithOrder']);
    Route::post('/{reservation}/unseat', [ReservationController::class, 'unseat']);
    Route::post('/{reservation}/preorder', [ReservationController::class, 'preorder']);
    Route::get('/{reservation}/preorder-items', [ReservationController::class, 'preorderItems']);
    Route::post('/{reservation}/preorder-items', [ReservationController::class, 'addPreorderItem']);
    Route::patch('/{reservation}/preorder-items/{itemId}', [ReservationController::class, 'updatePreorderItem']);
    Route::delete('/{reservation}/preorder-items/{itemId}', [ReservationController::class, 'removePreorderItem']);
    Route::post('/{reservation}/complete', [ReservationController::class, 'complete']);
    Route::post('/{reservation}/no-show', [ReservationController::class, 'noShow']);
    Route::post('/{reservation}/prepayment', [ReservationController::class, 'prepayment']);
    // Депозит
    Route::get('/{reservation}/deposit', [ReservationController::class, 'depositSummary']);
    Route::post('/{reservation}/deposit/pay', [ReservationController::class, 'payDeposit']);
    Route::post('/{reservation}/deposit/refund', [ReservationController::class, 'refundDeposit']);
    // Печать предзаказа на кухню
    Route::post('/{reservation}/print-preorder', [ReservationController::class, 'printPreorder']);
});

// =====================================================
// ЗОНЫ (требуется авторизация для tenant-изоляции)
// =====================================================
Route::prefix('zones')->middleware('auth.api_token')->group(function () {
    Route::get('/', [TableController::class, 'zones']);
    Route::post('/', [TableController::class, 'storeZone']);
    Route::put('/{zone}', [TableController::class, 'updateZone']);
    Route::delete('/{zone}', [TableController::class, 'destroyZone']);
});

// =====================================================
// РЕСТОРАНЫ
// =====================================================
Route::prefix('restaurants')->middleware('auth.api_token')->group(function () {
    Route::get('/{restaurant}', [DashboardController::class, 'getRestaurant']);
    Route::put('/{restaurant}', [DashboardController::class, 'updateRestaurant']);
});
