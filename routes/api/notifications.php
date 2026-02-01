<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GuestMenuController;
use App\Http\Controllers\Api\RealtimeController;
use App\Http\Controllers\Api\StaffNotificationController;
use App\Http\Controllers\Api\TelegramStaffBotController;

// =====================================================
// REAL-TIME
// =====================================================
Route::prefix('realtime')->middleware(['auth.api_token', 'throttle:120,1'])->group(function () {
    Route::get('/stream', [RealtimeController::class, 'stream']);
    Route::get('/poll', [RealtimeController::class, 'poll']);
    Route::get('/recent', [RealtimeController::class, 'recent']);
    Route::post('/send', [RealtimeController::class, 'send']);
    Route::get('/status', [RealtimeController::class, 'status']);
    Route::post('/cleanup', [RealtimeController::class, 'cleanup']);
});

// =====================================================
// ГОСТЕВОЕ МЕНЮ (публичные и админ эндпоинты)
// =====================================================
Route::prefix('guest')->middleware('throttle:60,1')->group(function () {
    // Публичные (для гостей)
    Route::get('/menu/{code}', [GuestMenuController::class, 'getMenuByCode']);
    Route::get('/dish/{dish}', [GuestMenuController::class, 'getDish']);
    Route::post('/call', [GuestMenuController::class, 'callWaiter']);
    Route::post('/call/cancel', [GuestMenuController::class, 'cancelCall']);
    Route::post('/review', [GuestMenuController::class, 'submitReview']);

    // Админ
    Route::get('/calls', [GuestMenuController::class, 'activeCalls']);
    Route::post('/calls/{call}/accept', [GuestMenuController::class, 'acceptCall']);
    Route::post('/calls/{call}/complete', [GuestMenuController::class, 'completeCall']);

    Route::get('/reviews', [GuestMenuController::class, 'reviews']);
    Route::get('/reviews/stats', [GuestMenuController::class, 'reviewStats']);
    Route::post('/reviews/{review}/toggle', [GuestMenuController::class, 'toggleReview']);
    Route::post('/reviews/{review}/respond', [GuestMenuController::class, 'respondToReview']);

    Route::get('/qr-codes', [GuestMenuController::class, 'qrCodes']);
    Route::post('/qr-codes', [GuestMenuController::class, 'generateQr']);
    Route::post('/qr-codes/generate-all', [GuestMenuController::class, 'generateAllQr']);
    Route::post('/qr-codes/{qrCode}/regenerate', [GuestMenuController::class, 'regenerateQr']);
    Route::post('/qr-codes/{qrCode}/toggle', [GuestMenuController::class, 'toggleQr']);

    Route::get('/settings', [GuestMenuController::class, 'settings']);
    Route::put('/settings', [GuestMenuController::class, 'updateSettings']);
});

// =====================================================
// УВЕДОМЛЕНИЯ (Telegram + Web Push)
// =====================================================
Route::prefix('notifications')->group(function () {
    // Web Push
    Route::get('/vapid-key', [\App\Http\Controllers\Api\NotificationController::class, 'getVapidKey']);
    Route::post('/push/subscribe', [\App\Http\Controllers\Api\NotificationController::class, 'subscribePush']);
    Route::post('/push/unsubscribe', [\App\Http\Controllers\Api\NotificationController::class, 'unsubscribePush']);

    // Telegram
    Route::get('/telegram/bot', [\App\Http\Controllers\Api\NotificationController::class, 'getTelegramBot']);
    Route::get('/telegram/subscribe-link', [\App\Http\Controllers\Api\NotificationController::class, 'getTelegramSubscribeLink']);
    Route::post('/telegram/set-webhook', [\App\Http\Controllers\Api\NotificationController::class, 'setTelegramWebhook']);

    // Тестирование
    Route::post('/test', [\App\Http\Controllers\Api\NotificationController::class, 'sendTestNotification']);
});

// Telegram Webhook (отдельный маршрут)
Route::post('/telegram/webhook', [\App\Http\Controllers\Api\NotificationController::class, 'telegramWebhook']);

// =====================================================
// УВЕДОМЛЕНИЯ СОТРУДНИКОВ (Staff Notifications)
// =====================================================
Route::prefix('staff-notifications')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [StaffNotificationController::class, 'index']);
        Route::get('/unread-count', [StaffNotificationController::class, 'unreadCount']);
        Route::post('/{notification}/read', [StaffNotificationController::class, 'markAsRead']);
        Route::post('/read-all', [StaffNotificationController::class, 'markAllAsRead']);
        Route::delete('/{notification}', [StaffNotificationController::class, 'destroy']);

        Route::get('/settings', [StaffNotificationController::class, 'getSettings']);
        Route::put('/settings', [StaffNotificationController::class, 'updateSettings']);

        Route::get('/telegram-link', [StaffNotificationController::class, 'getTelegramLink']);
        Route::post('/disconnect-telegram', [StaffNotificationController::class, 'disconnectTelegram']);

        Route::post('/push-token', [StaffNotificationController::class, 'savePushToken']);

        Route::post('/send-test', [StaffNotificationController::class, 'sendTest']);
        Route::post('/send-to-user', [StaffNotificationController::class, 'sendToUser']);
        Route::post('/send-to-all', [StaffNotificationController::class, 'sendToAll']);
    });
});

// Telegram Staff Bot Webhook
Route::post('/telegram/staff-bot/webhook', [TelegramStaffBotController::class, 'webhook']);
Route::post('/telegram/staff-bot/set-webhook', [TelegramStaffBotController::class, 'setWebhook']);
Route::get('/telegram/staff-bot/webhook-info', [TelegramStaffBotController::class, 'getWebhookInfo']);
