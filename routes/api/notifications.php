<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GuestMenuController;
use App\Http\Controllers\Api\StaffNotificationController;
use App\Http\Controllers\Api\TelegramStaffBotController;

// =====================================================
// REAL-TIME: Теперь используется Laravel Reverb (WebSocket)
// Старый SSE endpoint удалён, см. app/Traits/BroadcastsEvents.php
// =====================================================

// =====================================================
// ГОСТЕВОЕ МЕНЮ - ПУБЛИЧНЫЕ (для гостей без авторизации)
// =====================================================
Route::prefix('guest')->middleware('throttle:60,1')->group(function () {
    Route::get('/menu/{code}', [GuestMenuController::class, 'getMenuByCode']);
    Route::get('/dish/{dish}', [GuestMenuController::class, 'getDish']);
    Route::post('/call', [GuestMenuController::class, 'callWaiter']);
    Route::post('/call/cancel', [GuestMenuController::class, 'cancelCall']);
    Route::post('/review', [GuestMenuController::class, 'submitReview']);
});

// =====================================================
// ГОСТЕВОЕ МЕНЮ - АДМИН (требуется авторизация)
// =====================================================
Route::prefix('guest')->middleware(['auth.api_token', 'throttle:60,1'])->group(function () {
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
    // Web Push (публичный VAPID-ключ)
    Route::get('/vapid-key', [\App\Http\Controllers\Api\NotificationController::class, 'getVapidKey']);

    // Управление подписками и настройка — требует авторизации
    Route::middleware('auth.api_token')->group(function () {
        Route::post('/push/subscribe', [\App\Http\Controllers\Api\NotificationController::class, 'subscribePush']);
        Route::post('/push/unsubscribe', [\App\Http\Controllers\Api\NotificationController::class, 'unsubscribePush']);

        // Telegram
        Route::get('/telegram/bot', [\App\Http\Controllers\Api\NotificationController::class, 'getTelegramBot']);
        Route::get('/telegram/subscribe-link', [\App\Http\Controllers\Api\NotificationController::class, 'getTelegramSubscribeLink']);
        Route::post('/telegram/set-webhook', [\App\Http\Controllers\Api\NotificationController::class, 'setTelegramWebhook']);

        // Тестирование
        Route::post('/test', [\App\Http\Controllers\Api\NotificationController::class, 'sendTestNotification']);
    });
});

// Telegram Webhook (публичный — вызывается Telegram серверами, проверка подписи внутри контроллера)
Route::post('/telegram/webhook', [\App\Http\Controllers\Api\NotificationController::class, 'telegramWebhook']);

// =====================================================
// TELEGRAM GUEST BOT (White-Label per Restaurant)
// =====================================================
// Webhook with bot_id for multi-tenant routing
Route::post('/telegram/guest-bot/webhook/{botId}', [\App\Http\Controllers\Api\TelegramBotController::class, 'webhook'])
    ->where('botId', '[0-9]+');

// Legacy route (fallback to platform bot)
Route::post('/telegram/guest-bot/webhook', [\App\Http\Controllers\Api\TelegramBotController::class, 'webhook']);

// =====================================================
// GUEST CHANNEL MANAGEMENT (для гостей — управление уведомлениями)
// =====================================================
Route::prefix('guest/channels')->group(function () {
    // Generate Telegram link (by phone, public with throttle)
    Route::post('/telegram/link', [\App\Http\Controllers\Api\GuestChannelController::class, 'generateTelegramLink'])
        ->middleware('throttle:5,1');

    // Check linking status by phone
    Route::post('/status', [\App\Http\Controllers\Api\GuestChannelController::class, 'getStatus'])
        ->middleware('throttle:20,1');

    // Update preferences
    Route::post('/preferences', [\App\Http\Controllers\Api\GuestChannelController::class, 'updatePreferences'])
        ->middleware('throttle:10,1');
});

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

// Telegram Staff Bot Webhook (публичный — вызывается Telegram серверами)
Route::post('/telegram/staff-bot/webhook', [TelegramStaffBotController::class, 'webhook']);

// Управление webhook — требует авторизации
Route::middleware('auth.api_token')->group(function () {
    Route::post('/telegram/staff-bot/set-webhook', [TelegramStaffBotController::class, 'setWebhook']);
    Route::get('/telegram/staff-bot/webhook-info', [TelegramStaffBotController::class, 'getWebhookInfo']);
});

// =====================================================
// RESTAURANT TELEGRAM BOT MANAGEMENT (Backoffice)
// =====================================================
Route::prefix('restaurant/telegram-bot')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\RestaurantTelegramBotController::class, 'status']);
    Route::post('/', [\App\Http\Controllers\Api\RestaurantTelegramBotController::class, 'configure']);
    Route::delete('/', [\App\Http\Controllers\Api\RestaurantTelegramBotController::class, 'remove']);

    Route::post('/webhook', [\App\Http\Controllers\Api\RestaurantTelegramBotController::class, 'setupWebhook']);
    Route::delete('/webhook', [\App\Http\Controllers\Api\RestaurantTelegramBotController::class, 'removeWebhook']);

    Route::post('/test', [\App\Http\Controllers\Api\RestaurantTelegramBotController::class, 'sendTest']);
});
