<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

// =====================================================
// АВТОРИЗАЦИЯ
// =====================================================
Route::prefix('auth')->group(function () {
    // Первоначальная настройка системы
    Route::get('/setup-status', [AuthController::class, 'setupStatus']);
    Route::post('/setup', [AuthController::class, 'setup']);

    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/login-pin', [AuthController::class, 'loginByPin']);
    Route::get('/check', [AuthController::class, 'check']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/users', [AuthController::class, 'users']);
    Route::post('/change-pin', [AuthController::class, 'changePin']);

    // Device Sessions
    Route::post('/login-device', [AuthController::class, 'loginWithDevice']);
    Route::post('/device-login', [AuthController::class, 'deviceLogin']);
    Route::get('/device-users', [AuthController::class, 'deviceUsers']);
    Route::post('/logout-device', [AuthController::class, 'logoutDevice']);

    // Device Sessions Management (требуют авторизации)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/device-sessions', [AuthController::class, 'getDeviceSessions']);
        Route::delete('/device-sessions/{id}', [AuthController::class, 'revokeDeviceSession']);
        Route::post('/device-sessions/revoke-all', [AuthController::class, 'revokeAllDeviceSessions']);
    });

    // Восстановление пароля
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/check-reset-token', [AuthController::class, 'checkResetToken']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// =====================================================
// РЕГИСТРАЦИЯ СОТРУДНИКОВ
// =====================================================
Route::prefix('register')->group(function () {
    Route::get('/validate-token', [\App\Http\Controllers\Api\RegistrationController::class, 'validateToken']);
    Route::post('/', [\App\Http\Controllers\Api\RegistrationController::class, 'register']);
    // Регистрация нового тенанта (SaaS)
    Route::post('/tenant', [\App\Http\Controllers\Api\TenantController::class, 'register']);
});

// =====================================================
// ТЕНАНТ И РЕСТОРАНЫ
// =====================================================
Route::prefix('tenant')->middleware('auth:sanctum')->group(function () {
    // Информация о тенанте
    Route::get('/', [\App\Http\Controllers\Api\TenantController::class, 'show']);
    Route::put('/', [\App\Http\Controllers\Api\TenantController::class, 'update']);
    Route::get('/limits', [\App\Http\Controllers\Api\TenantController::class, 'limits']);

    // Тарифы и подписка
    Route::get('/plans', [\App\Http\Controllers\Api\TenantController::class, 'plans']);
    Route::get('/subscription', [\App\Http\Controllers\Api\TenantController::class, 'subscription']);
    Route::post('/subscription/change', [\App\Http\Controllers\Api\TenantController::class, 'changePlan']);
    Route::post('/subscription/extend', [\App\Http\Controllers\Api\TenantController::class, 'extendSubscription']);

    // Управление ресторанами (точками)
    Route::get('/restaurants', [\App\Http\Controllers\Api\TenantController::class, 'restaurants']);
    Route::post('/restaurants', [\App\Http\Controllers\Api\TenantController::class, 'createRestaurant']);
    Route::put('/restaurants/{id}', [\App\Http\Controllers\Api\TenantController::class, 'updateRestaurant']);
    Route::delete('/restaurants/{id}', [\App\Http\Controllers\Api\TenantController::class, 'deleteRestaurant']);
    Route::post('/restaurants/{id}/make-main', [\App\Http\Controllers\Api\TenantController::class, 'makeMain']);
    Route::post('/restaurants/{id}/switch', [\App\Http\Controllers\Api\TenantController::class, 'switchRestaurant']);
});

// =====================================================
// СУПЕР-АДМИН (управление всеми тенантами)
// =====================================================
Route::prefix('super-admin')->middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Api\SuperAdminController::class, 'dashboard']);
    Route::get('/tenants', [\App\Http\Controllers\Api\SuperAdminController::class, 'tenants']);
    Route::get('/tenants/{id}', [\App\Http\Controllers\Api\SuperAdminController::class, 'tenantDetails']);
    Route::put('/tenants/{id}', [\App\Http\Controllers\Api\SuperAdminController::class, 'updateTenant']);
    Route::delete('/tenants/{id}', [\App\Http\Controllers\Api\SuperAdminController::class, 'deleteTenant']);
    Route::post('/tenants/{id}/block', [\App\Http\Controllers\Api\SuperAdminController::class, 'blockTenant']);
    Route::post('/tenants/{id}/unblock', [\App\Http\Controllers\Api\SuperAdminController::class, 'unblockTenant']);
    Route::post('/tenants/{id}/extend', [\App\Http\Controllers\Api\SuperAdminController::class, 'extendTenantSubscription']);
    Route::post('/tenants/{id}/change-plan', [\App\Http\Controllers\Api\SuperAdminController::class, 'changeTenantPlan']);
    Route::post('/tenants/{id}/impersonate', [\App\Http\Controllers\Api\SuperAdminController::class, 'impersonate']);
});
