<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Inventory\WarehouseController;
use App\Http\Controllers\Api\Inventory\IngredientController;
use App\Http\Controllers\Api\Inventory\InvoiceController;
use App\Http\Controllers\Api\Inventory\InventoryCheckController;
use App\Http\Controllers\Api\Inventory\StockController;
use App\Http\Controllers\Api\Inventory\SupplierController;
use App\Http\Controllers\Api\Inventory\RecipeController;
use App\Http\Controllers\Api\Inventory\UnitController;
use App\Http\Controllers\Api\Inventory\CategoryController;

// =====================================================
// СКЛАД
// =====================================================
Route::prefix('inventory')->middleware(['auth.api_token', 'permission:inventory.view'])->group(function () {

    // === Read-only — inventory.view (базовый доступ) ===

    // Склады (types ПЕРЕД resource routes чтобы не перехватывалось {warehouse})
    Route::get('/warehouses/types', [WarehouseController::class, 'types']);
    Route::get('/warehouses', [WarehouseController::class, 'index']);
    Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show']);

    // Ингредиенты
    Route::get('/ingredients', [IngredientController::class, 'index']);
    Route::get('/ingredients/{ingredient}', [IngredientController::class, 'show']);
    Route::get('/ingredients/{ingredient}/packagings', [IngredientController::class, 'packagings']);
    Route::get('/ingredients/{ingredient}/available-units', [IngredientController::class, 'availableUnits']);
    Route::get('/ingredients/{ingredient}/suggest-parameters', [IngredientController::class, 'suggestParameters']);

    // Категории ингредиентов
    Route::get('/categories', [CategoryController::class, 'index']);

    // Единицы измерения
    Route::get('/units', [UnitController::class, 'index']);

    // Накладные
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);

    // Движение товаров
    Route::get('/movements', [StockController::class, 'movements']);

    // Поставщики
    Route::get('/suppliers', [SupplierController::class, 'index']);

    // Инвентаризации
    Route::get('/checks', [InventoryCheckController::class, 'index']);
    Route::get('/checks/{inventoryCheck}', [InventoryCheckController::class, 'show']);

    // Статистика и алерты
    Route::get('/stats', [StockController::class, 'stats']);
    Route::get('/alerts/low-stock', [StockController::class, 'lowStockAlerts']);

    // Конвертация единиц измерения
    Route::post('/convert-units', [StockController::class, 'convertUnits']);
    Route::post('/calculate-brutto-netto', [StockController::class, 'calculateBruttoNetto']);

    // Рецепты блюд (техкарты) — read
    Route::get('/dishes/{dish}/recipe', [RecipeController::class, 'dishRecipe']);

    // Yandex Vision config check
    Route::get('/vision/check', [InvoiceController::class, 'checkVisionConfig']);

    // Интеграция с POS (проверка доступности и списание)
    Route::post('/check-availability', [StockController::class, 'checkDishAvailability']);
    Route::post('/deduct-for-order/{order}', [StockController::class, 'deductForOrder']);

    // === Mutations — granular permissions ===

    // Склады — inventory.settings
    Route::post('/warehouses', [WarehouseController::class, 'store'])->middleware('permission:inventory.settings');
    Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:inventory.settings');
    Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:inventory.settings');

    // Ингредиенты — inventory.ingredients
    Route::post('/ingredients', [IngredientController::class, 'store'])->middleware('permission:inventory.ingredients');
    Route::put('/ingredients/{ingredient}', [IngredientController::class, 'update'])->middleware('permission:inventory.ingredients');
    Route::delete('/ingredients/{ingredient}', [IngredientController::class, 'destroy'])->middleware('permission:inventory.ingredients');
    Route::post('/ingredients/{ingredient}/packagings', [IngredientController::class, 'storePackaging'])->middleware('permission:inventory.ingredients');
    Route::put('/ingredients/{ingredient}/packagings/{packaging}', [IngredientController::class, 'updatePackaging'])->middleware('permission:inventory.ingredients');
    Route::delete('/ingredients/{ingredient}/packagings/{packaging}', [IngredientController::class, 'destroyPackaging'])->middleware('permission:inventory.ingredients');

    // Категории — inventory.settings
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:inventory.settings');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:inventory.settings');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:inventory.settings');

    // Единицы измерения — inventory.settings
    Route::post('/units', [UnitController::class, 'store'])->middleware('permission:inventory.settings');
    Route::put('/units/{unit}', [UnitController::class, 'update'])->middleware('permission:inventory.settings');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->middleware('permission:inventory.settings');

    // Накладные — inventory.invoices
    Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:inventory.invoices');
    Route::post('/invoices/{invoice}/complete', [InvoiceController::class, 'complete'])->middleware('permission:inventory.invoices');
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:inventory.invoices');
    Route::post('/invoices/recognize', [InvoiceController::class, 'recognize'])->middleware('permission:inventory.invoices');

    // Быстрые операции
    Route::post('/quick-income', [StockController::class, 'quickIncome'])->middleware('permission:inventory.invoices');
    Route::post('/quick-write-off', [StockController::class, 'quickWriteOff'])->middleware('permission:inventory.write_off');

    // Рецепты блюд (техкарты) — write
    Route::post('/dishes/{dish}/recipe', [RecipeController::class, 'saveDishRecipe'])->middleware('permission:inventory.ingredients');

    // Поставщики — inventory.suppliers
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('permission:inventory.suppliers');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:inventory.suppliers');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:inventory.suppliers');

    // Инвентаризации — inventory.checks
    Route::post('/checks', [InventoryCheckController::class, 'store'])->middleware('permission:inventory.checks');
    Route::put('/checks/{inventoryCheck}/items/{item}', [InventoryCheckController::class, 'updateItem'])->middleware('permission:inventory.checks');
    Route::post('/checks/{inventoryCheck}/items', [InventoryCheckController::class, 'addItem'])->middleware('permission:inventory.checks');
    Route::post('/checks/{inventoryCheck}/complete', [InventoryCheckController::class, 'complete'])->middleware('permission:inventory.checks');
    Route::post('/checks/{inventoryCheck}/cancel', [InventoryCheckController::class, 'cancel'])->middleware('permission:inventory.checks');
});
