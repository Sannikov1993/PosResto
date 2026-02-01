<?php

use Illuminate\Support\Facades\Route;

// =====================================================
// УЧЁТ РАБОЧЕГО ВРЕМЕНИ (Attendance - публичные webhook)
// =====================================================

// Webhook для устройств биометрии (авторизация по API-ключу, rate limiting)
Route::middleware('throttle:100,1')->group(function () {
    Route::post('/attendance/webhook/{type}', [\App\Http\Controllers\Api\AttendanceWebhookController::class, 'handle']);
    Route::post('/attendance/heartbeat', [\App\Http\Controllers\Api\AttendanceWebhookController::class, 'heartbeat']);
});

// QR-код для отображения в ресторане (rate limiting для защиты от DoS)
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/attendance/qr/{restaurantId}', [\App\Http\Controllers\Api\AttendanceController::class, 'getQrCode']);
    Route::post('/attendance/qr/{restaurantId}/refresh', [\App\Http\Controllers\Api\AttendanceController::class, 'refreshQrCode']);
});

// Эндпоинты для личного кабинета сотрудника (с авторизацией)
Route::prefix('cabinet/attendance')->middleware('auth:sanctum')->group(function () {
    Route::get('/status', [\App\Http\Controllers\Api\AttendanceController::class, 'status']);
    Route::post('/qr/clock-in', [\App\Http\Controllers\Api\AttendanceController::class, 'clockInQr']);
    Route::post('/qr/clock-out', [\App\Http\Controllers\Api\AttendanceController::class, 'clockOutQr']);
    Route::post('/qr/validate', [\App\Http\Controllers\Api\AttendanceController::class, 'validateQr']);
    Route::get('/history', [\App\Http\Controllers\Api\AttendanceController::class, 'history']);
});
