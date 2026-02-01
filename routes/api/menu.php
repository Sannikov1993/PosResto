<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\PriceListController;

// =====================================================
// МЕНЮ
// =====================================================
Route::prefix('menu')->middleware('auth.api_token')->group(function () {
    // Чтение — menu.view
    Route::middleware('permission:menu.view')->group(function () {
        Route::get('/', [MenuController::class, 'index']);
        Route::get('/categories', [MenuController::class, 'categories']);
        Route::get('/dishes', [MenuController::class, 'dishes']);
        Route::get('/dishes/{dish}', [MenuController::class, 'showDish']);
        Route::get('/modifiers', [MenuController::class, 'modifiers']);
    });
    // Создание — menu.create
    Route::middleware('permission:menu.create')->group(function () {
        Route::post('/', [MenuController::class, 'storeDish']);
        Route::post('/categories', [MenuController::class, 'storeCategory']);
        Route::post('/dishes', [MenuController::class, 'storeDish']);
    });
    // Редактирование — menu.edit
    Route::middleware('permission:menu.edit')->group(function () {
        Route::put('/{dish}', [MenuController::class, 'updateDish']);
        Route::put('/categories/{category}', [MenuController::class, 'updateCategory']);
        Route::put('/dishes/{dish}', [MenuController::class, 'updateDish']);
        Route::patch('/dishes/{dish}/toggle', [MenuController::class, 'toggleAvailability']);
    });
    // Удаление — menu.delete
    Route::middleware('permission:menu.delete')->group(function () {
        Route::delete('/{dish}', [MenuController::class, 'destroyDish']);
        Route::delete('/categories/{category}', [MenuController::class, 'destroyCategory']);
        Route::delete('/dishes/{dish}', [MenuController::class, 'destroyDish']);
    });
});

// =====================================================
// ПРАЙС-ЛИСТЫ
// =====================================================
Route::prefix('price-lists')->middleware('auth.api_token')->group(function () {
    Route::get('/', [PriceListController::class, 'index']);
    Route::post('/', [PriceListController::class, 'store']);
    Route::get('/{priceList}', [PriceListController::class, 'show']);
    Route::put('/{priceList}', [PriceListController::class, 'update']);
    Route::delete('/{priceList}', [PriceListController::class, 'destroy']);
    Route::post('/{priceList}/toggle', [PriceListController::class, 'toggle']);
    Route::post('/{priceList}/default', [PriceListController::class, 'setDefault']);
    Route::get('/{priceList}/items', [PriceListController::class, 'items']);
    Route::post('/{priceList}/items', [PriceListController::class, 'saveItems']);
    Route::delete('/{priceList}/items/{dishId}', [PriceListController::class, 'removeItem']);
});
