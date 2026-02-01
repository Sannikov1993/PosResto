<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InventoryController;

// =====================================================
// СКЛАД
// =====================================================
Route::prefix('inventory')->middleware(['auth.api_token', 'permission:inventory.view'])->group(function () {
    // Склады
    Route::get('/warehouses', [InventoryController::class, 'warehouses']);
    Route::post('/warehouses', [InventoryController::class, 'storeWarehouse']);
    Route::put('/warehouses/{warehouse}', [InventoryController::class, 'updateWarehouse']);
    Route::delete('/warehouses/{warehouse}', [InventoryController::class, 'destroyWarehouse']);
    Route::get('/warehouse-types', [InventoryController::class, 'warehouseTypes']);

    // Ингредиенты
    Route::get('/ingredients', [InventoryController::class, 'ingredients']);
    Route::post('/ingredients', [InventoryController::class, 'storeIngredient']);
    Route::get('/ingredients/{ingredient}', [InventoryController::class, 'showIngredient']);
    Route::put('/ingredients/{ingredient}', [InventoryController::class, 'updateIngredient']);
    Route::delete('/ingredients/{ingredient}', [InventoryController::class, 'destroyIngredient']);

    // Фасовки ингредиентов
    Route::get('/ingredients/{ingredient}/packagings', [InventoryController::class, 'ingredientPackagings']);
    Route::post('/ingredients/{ingredient}/packagings', [InventoryController::class, 'storePackaging']);
    Route::put('/packagings/{packaging}', [InventoryController::class, 'updatePackaging']);
    Route::delete('/packagings/{packaging}', [InventoryController::class, 'destroyPackaging']);

    // Конвертация единиц измерения
    Route::post('/convert-units', [InventoryController::class, 'convertUnits']);
    Route::post('/calculate-brutto-netto', [InventoryController::class, 'calculateBruttoNetto']);
    Route::get('/ingredients/{ingredient}/available-units', [InventoryController::class, 'availableUnits']);
    Route::get('/ingredients/{ingredient}/suggest-parameters', [InventoryController::class, 'suggestParameters']);

    // Категории ингредиентов
    Route::get('/categories', [InventoryController::class, 'categories']);
    Route::post('/categories', [InventoryController::class, 'storeCategory']);
    Route::put('/categories/{category}', [InventoryController::class, 'updateCategory']);
    Route::delete('/categories/{category}', [InventoryController::class, 'destroyCategory']);

    // Единицы измерения
    Route::get('/units', [InventoryController::class, 'units']);
    Route::post('/units', [InventoryController::class, 'storeUnit']);
    Route::put('/units/{unit}', [InventoryController::class, 'updateUnit']);
    Route::delete('/units/{unit}', [InventoryController::class, 'destroyUnit']);

    // Накладные
    Route::get('/invoices', [InventoryController::class, 'invoices']);
    Route::post('/invoices', [InventoryController::class, 'storeInvoice']);
    Route::get('/invoices/{invoice}', [InventoryController::class, 'showInvoice']);
    Route::post('/invoices/{invoice}/complete', [InventoryController::class, 'completeInvoice']);
    Route::post('/invoices/{invoice}/cancel', [InventoryController::class, 'cancelInvoice']);

    // Быстрые операции
    Route::post('/quick-income', [InventoryController::class, 'quickIncome']);
    Route::post('/quick-write-off', [InventoryController::class, 'quickWriteOff']);

    // Движение товаров
    Route::get('/movements', [InventoryController::class, 'movements']);

    // Остатки
    Route::get('/stock', [InventoryController::class, 'stock']);
    Route::post('/stock/income', [InventoryController::class, 'stockIncome']);
    Route::post('/stock/write-off', [InventoryController::class, 'stockWriteOff']);

    // Рецепты блюд (техкарты)
    Route::get('/dishes/{dish}/recipe', [InventoryController::class, 'dishRecipe']);
    Route::post('/dishes/{dish}/recipe', [InventoryController::class, 'saveDishRecipe']);

    // Поставщики
    Route::get('/suppliers', [InventoryController::class, 'suppliers']);
    Route::post('/suppliers', [InventoryController::class, 'storeSupplier']);
    Route::put('/suppliers/{supplier}', [InventoryController::class, 'updateSupplier']);
    Route::delete('/suppliers/{supplier}', [InventoryController::class, 'destroySupplier']);

    // Инвентаризации
    Route::get('/checks', [InventoryController::class, 'inventoryChecks']);
    Route::post('/checks', [InventoryController::class, 'createInventoryCheck']);
    Route::get('/checks/{inventoryCheck}', [InventoryController::class, 'showInventoryCheck']);
    Route::put('/checks/{inventoryCheck}/items/{item}', [InventoryController::class, 'updateInventoryCheckItem']);
    Route::post('/checks/{inventoryCheck}/complete', [InventoryController::class, 'completeInventoryCheck']);
    Route::post('/checks/{inventoryCheck}/items', [InventoryController::class, 'addInventoryCheckItem']);
    Route::post('/checks/{inventoryCheck}/cancel', [InventoryController::class, 'cancelInventoryCheck']);

    // Статистика и алерты
    Route::get('/stats', [InventoryController::class, 'stats']);
    Route::get('/alerts/low-stock', [InventoryController::class, 'lowStockAlerts']);

    // Интеграция с POS (проверка доступности и списание)
    Route::post('/check-availability', [InventoryController::class, 'checkDishAvailability']);
    Route::post('/deduct-for-order/{order}', [InventoryController::class, 'deductForOrder']);

    // Распознавание накладных по фото (Yandex Vision OCR)
    Route::post('/invoices/recognize', [InventoryController::class, 'recognizeInvoice']);
    Route::get('/vision/check', [InventoryController::class, 'checkVisionConfig']);
});
