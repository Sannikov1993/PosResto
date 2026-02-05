<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\OrderPaymentController;
use App\Http\Controllers\Api\OrderCancellationController;
use App\Http\Controllers\Api\PrinterController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Pos\TableOrderController;

// =====================================================
// ЗАКАЗЫ
// =====================================================
Route::prefix('orders')->middleware('auth.api_token')->group(function () {
    // Чтение — orders.view
    Route::middleware('permission:orders.view')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/count-by-dates', [OrderController::class, 'countByDates']);
        Route::get('/write-offs', [OrderCancellationController::class, 'writeOffs']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::get('/{order}/payment-split-preview', [OrderPaymentController::class, 'paymentSplitPreview']);
    });
    // Создание
    Route::post('/', [OrderController::class, 'store'])->middleware('permission:orders.create');
    // Редактирование
    Route::middleware('permission:orders.edit')->group(function () {
        Route::put('/{order}', [OrderController::class, 'update']);
        Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);
        Route::post('/{order}/pay', [OrderPaymentController::class, 'pay']);
        Route::post('/{order}/transfer', [OrderController::class, 'transfer']);
        Route::patch('/{order}/delivery-status', [OrderController::class, 'updateDeliveryStatus']);
        Route::post('/{order}/assign-courier', [OrderController::class, 'assignCourier']);
        Route::post('/{order}/items', [OrderItemController::class, 'addItem']);
        Route::patch('/{order}/items/{item}/status', [OrderItemController::class, 'updateItemStatus']);
        Route::delete('/{order}/items/{item}', [OrderItemController::class, 'removeItem']);
        Route::post('/{order}/call-waiter', [OrderController::class, 'callWaiter']);
        Route::post('/{order}/print/receipt', [PrinterController::class, 'printReceipt']);
        Route::post('/{order}/print/precheck', [PrinterController::class, 'printPrecheck']);
        Route::post('/{order}/print/kitchen', [PrinterController::class, 'printToKitchen']);
        Route::get('/{order}/print/data', [PrinterController::class, 'getReceiptData']);
        Route::get('/{order}/preview/precheck', [PrinterController::class, 'previewPrecheck']);
        Route::get('/{order}/preview/receipt', [PrinterController::class, 'previewReceipt']);
    });
    // Отмена
    Route::post('/{order}/cancel-with-writeoff', [OrderPaymentController::class, 'cancelWithWriteOff'])->middleware('permission:orders.cancel');
});

// =====================================================
// ЗАКАЗЫ ПО СТОЛАМ (несколько заказов на одном столе)
// =====================================================
Route::prefix('tables/{tableId}/orders')->middleware('auth.api_token')->group(function () {
    Route::get('/', [OrderController::class, 'tableOrders']);
    Route::post('/', [OrderController::class, 'createTableOrder']);
});

// =====================================================
// ОТМЕНЫ ПОЗИЦИЙ
// =====================================================
Route::prefix('order-items')->middleware(['auth.api_token', 'permission:orders.cancel'])->group(function () {
    Route::post('/{item}/cancel', [OrderItemController::class, 'cancelItem']);
    Route::post('/{item}/request-cancellation', [OrderItemController::class, 'requestItemCancellation']);
    Route::post('/{item}/approve-cancellation', [OrderItemController::class, 'approveItemCancellation']);
    Route::post('/{item}/reject-cancellation', [OrderItemController::class, 'rejectItemCancellation']);
});

Route::prefix('cancellations')->middleware('auth.api_token')->group(function () {
    Route::get('/reasons', [OrderCancellationController::class, 'getCancellationReasons']);
    Route::get('/pending', [OrderCancellationController::class, 'pendingCancellations'])->middleware('permission:orders.cancel');
    Route::post('/{order}/approve', [OrderCancellationController::class, 'approveCancellation'])->middleware('permission:orders.cancel');
    Route::post('/{order}/reject', [OrderCancellationController::class, 'rejectCancellation'])->middleware('permission:orders.cancel');
});

// Заявка на отмену заказа
Route::post('/orders/{order}/request-cancellation', [OrderCancellationController::class, 'requestCancellation'])->middleware(['auth.api_token', 'permission:orders.cancel']);

// История списаний (отменённые заказы и позиции) - legacy
Route::get('/write-offs/cancelled-orders', [OrderCancellationController::class, 'writeOffs'])->middleware('auth.api_token');

// =====================================================
// СПИСАНИЯ (новая система)
// =====================================================
Route::prefix('write-offs')->middleware('auth.api_token')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\WriteOffController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\WriteOffController::class, 'store']);
    Route::get('/settings', [\App\Http\Controllers\Api\WriteOffController::class, 'settings']);
    Route::post('/verify-manager', [\App\Http\Controllers\Api\WriteOffController::class, 'verifyManager']);
    Route::get('/{writeOff}', [\App\Http\Controllers\Api\WriteOffController::class, 'show']);
});

// =====================================================
// TABLE ORDER - CUSTOMER
// =====================================================
Route::middleware('auth.api_token')->group(function () {
    Route::post('/table-order/{order}/customer', [TableOrderController::class, 'attachCustomer']);
    Route::delete('/table-order/{order}/customer', [TableOrderController::class, 'detachCustomer']);
});

// =====================================================
// АЛИАСЫ ДЛЯ POS ИНТЕРФЕЙСА
// =====================================================
Route::middleware('auth.api_token')->group(function () {
    Route::get('/categories', [MenuController::class, 'categories'])->middleware('permission:menu.view');
    Route::get('/dishes', [MenuController::class, 'dishes'])->middleware('permission:menu.view');
    Route::post('/dishes', [MenuController::class, 'storeDish'])->middleware('permission:menu.create');
    Route::put('/dishes/{dish}', [MenuController::class, 'updateDish'])->middleware('permission:menu.edit');
    Route::delete('/dishes/{dish}', [MenuController::class, 'destroyDish'])->middleware('permission:menu.delete');
});

// Смены (алиас для /finance/shifts)
Route::prefix('shifts')->middleware('auth.api_token')->group(function () {
    Route::get('/current', [\App\Http\Controllers\Api\FinanceController::class, 'currentShift'])->middleware('permission:finance.view');
    Route::post('/open', [\App\Http\Controllers\Api\FinanceController::class, 'openShift'])->middleware('permission:finance.shifts');
    Route::post('/{shift}/close', [\App\Http\Controllers\Api\FinanceController::class, 'closeShift'])->middleware('permission:finance.shifts');
});
