<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\GiftCertificateController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\AnalyticsController;

// =====================================================
// ПРОГРАММА ЛОЯЛЬНОСТИ
// =====================================================
Route::prefix('loyalty')->middleware(['auth.api_token', 'permission:loyalty.view'])->group(function () {
    // Уровни лояльности
    Route::get('/levels', [LoyaltyController::class, 'levels']);
    Route::post('/levels', [LoyaltyController::class, 'storeLevel'])->middleware('permission:loyalty.edit');
    Route::put('/levels/{level}', [LoyaltyController::class, 'updateLevel'])->middleware('permission:loyalty.edit');
    Route::delete('/levels/{level}', [LoyaltyController::class, 'destroyLevel'])->middleware('permission:loyalty.edit');
    Route::post('/levels/recalculate', [LoyaltyController::class, 'recalculateLevels'])->middleware('permission:loyalty.edit');

    // Промокоды
    Route::get('/promo-codes', [LoyaltyController::class, 'promoCodes']);
    Route::post('/promo-codes', [LoyaltyController::class, 'storePromoCode']);
    Route::put('/promo-codes/{promotion}', [LoyaltyController::class, 'updatePromoCode']);
    Route::delete('/promo-codes/{promotion}', [LoyaltyController::class, 'destroyPromoCode']);
    Route::post('/promo-codes/validate', [LoyaltyController::class, 'validatePromoCode']);
    Route::post('/validate-promo', [LoyaltyController::class, 'validatePromoCode']);
    Route::post('/promo-codes/generate', [LoyaltyController::class, 'generatePromoCode']);
    Route::get('/promo-codes/available', [LoyaltyController::class, 'availablePromoCodes']);

    // Акции
    Route::get('/promotions', [LoyaltyController::class, 'promotions']);
    Route::get('/promotions/active', [LoyaltyController::class, 'activePromotions']);
    Route::get('/promotions/{promotion}', [LoyaltyController::class, 'showPromotion']);
    Route::post('/promotions', [LoyaltyController::class, 'storePromotion']);
    Route::put('/promotions/{promotion}', [LoyaltyController::class, 'updatePromotion']);
    Route::delete('/promotions/{promotion}', [LoyaltyController::class, 'destroyPromotion']);
    Route::post('/promotions/{promotion}/toggle', [LoyaltyController::class, 'togglePromotion']);

    // Бонусы
    Route::get('/bonus-history', [LoyaltyController::class, 'bonusHistory']);
    Route::get('/transactions', [LoyaltyController::class, 'bonusHistory']);
    Route::post('/bonus/earn', [LoyaltyController::class, 'earnBonus']);
    Route::post('/bonus/spend', [LoyaltyController::class, 'spendBonus']);

    // Настройки бонусной программы
    Route::get('/bonus-settings', [LoyaltyController::class, 'bonusSettings']);
    Route::put('/bonus-settings', [LoyaltyController::class, 'updateBonusSettings']);

    // Расчёт и настройки
    Route::post('/calculate', [LoyaltyController::class, 'calculateDiscount']);
    Route::post('/calculate-discount', [LoyaltyController::class, 'calculateDiscount']);
    Route::get('/settings', [LoyaltyController::class, 'settings']);
    Route::put('/settings', [LoyaltyController::class, 'updateSettings']);
    Route::get('/stats', [LoyaltyController::class, 'stats']);
    Route::post('/recalculate-level', [LoyaltyController::class, 'recalculateCustomerLevel']);
});

// =====================================================
// ПОДАРОЧНЫЕ СЕРТИФИКАТЫ
// =====================================================
// Публичный эндпоинт для проверки сертификата (киоски, клиенты)
Route::prefix('gift-certificates')->group(function () {
    Route::post('/check', [GiftCertificateController::class, 'check']);
});

// Защищённые эндпоинты (требуют авторизации)
Route::prefix('gift-certificates')->middleware('auth.api_token')->group(function () {
    Route::get('/', [GiftCertificateController::class, 'index']);
    Route::post('/', [GiftCertificateController::class, 'store']);
    Route::get('/stats', [GiftCertificateController::class, 'stats']);
    Route::get('/{giftCertificate}', [GiftCertificateController::class, 'show']);
    Route::put('/{giftCertificate}', [GiftCertificateController::class, 'update']);
    Route::post('/{giftCertificate}/use', [GiftCertificateController::class, 'use']);
    Route::post('/{giftCertificate}/activate', [GiftCertificateController::class, 'activate']);
    Route::post('/{giftCertificate}/cancel', [GiftCertificateController::class, 'cancel']);
});

// =====================================================
// КЛИЕНТЫ
// =====================================================
Route::prefix('customers')->middleware('auth.api_token')->group(function () {
    // Чтение — customers.view
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::get('/search', [CustomerController::class, 'search']);
        Route::get('/top', [CustomerController::class, 'top']);
        Route::get('/birthdays', [CustomerController::class, 'birthdays']);
        Route::get('/{customer}', [CustomerController::class, 'show']);
        Route::get('/{customer}/rfm', [AnalyticsController::class, 'customerRfm']);
        Route::get('/{customer}/addresses', [CustomerController::class, 'addresses']);
        Route::get('/{customer}/orders', [CustomerController::class, 'orders']);
        Route::get('/{customer}/all-orders', [CustomerController::class, 'allOrders']);
        Route::get('/{customer}/bonus-history', [CustomerController::class, 'bonusHistory']);
    });
    // Создание — customers.create
    Route::post('/', [CustomerController::class, 'store'])->middleware('permission:customers.create');
    // Редактирование — customers.edit
    Route::middleware('permission:customers.edit')->group(function () {
        Route::put('/{customer}', [CustomerController::class, 'update']);
        Route::post('/{customer}/bonus/add', [CustomerController::class, 'addBonus']);
        Route::post('/{customer}/bonus/use', [CustomerController::class, 'useBonus']);
        Route::post('/{customer}/blacklist', [CustomerController::class, 'blacklist']);
        Route::post('/{customer}/unblacklist', [CustomerController::class, 'unblacklist']);
        Route::post('/{customer}/addresses', [CustomerController::class, 'addAddress']);
        Route::post('/{customer}/save-delivery-address', [CustomerController::class, 'saveDeliveryAddress']);
        Route::post('/{customer}/addresses/{address}/set-default', [CustomerController::class, 'setDefaultAddress']);
        Route::post('/{customer}/toggle-blacklist', [CustomerController::class, 'toggleBlacklist']);
    });
    // Удаление — customers.delete
    Route::middleware('permission:customers.delete')->group(function () {
        Route::delete('/{customer}', [CustomerController::class, 'destroy']);
        Route::delete('/{customer}/addresses/{address}', [CustomerController::class, 'deleteAddress']);
    });
});
