<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\StaffNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StaffNotificationController extends Controller
{
    protected StaffNotificationService $notificationService;

    public function __construct(StaffNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get notifications for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $notifications = $user->notifications()
            ->when($request->unread_only, fn($q) => $q->unread())
            ->when($request->type, fn($q) => $q->ofType($request->type))
            ->reorder()
            ->orderBy('created_at', 'desc')
            ->limit($request->limit ?? 50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => $user->notifications()->unread()->count(),
        ]);
    }

    /**
     * Get unread count
     */
    public function unreadCount(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'count' => $user->notifications()->unread()->count(),
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        $user = auth()->user();

        if ($notification->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = auth()->user();

        $user->notifications()->unread()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * Delete notification
     */
    public function destroy(Notification $notification): JsonResponse
    {
        $user = auth()->user();

        if ($notification->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }

    /**
     * Get notification settings
     */
    public function getSettings(): JsonResponse
    {
        // Use fresh() to get latest user data from database
        $user = auth()->user()->fresh();

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $user->notification_settings ?? User::getDefaultNotificationSettings(),
                'telegram_connected' => $user->hasTelegram(),
                'telegram_username' => $user->telegram_username,
                'email' => $user->email,
                'push_enabled' => $user->hasPushToken(),
            ],
        ]);
    }

    /**
     * Update notification settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        $user->update(['notification_settings' => $validated['settings']]);

        return response()->json([
            'success' => true,
            'message' => 'Settings updated',
            'data' => $user->notification_settings,
        ]);
    }

    /**
     * Get Telegram connect link
     */
    public function getTelegramLink(): JsonResponse
    {
        $user = auth()->user();

        $link = $this->notificationService->getTelegramConnectLink($user);

        if (!$link) {
            return response()->json([
                'success' => false,
                'message' => 'Telegram bot not configured',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'link' => $link,
                'connected' => $user->hasTelegram(),
                'username' => $user->telegram_username,
            ],
        ]);
    }

    /**
     * Disconnect Telegram
     */
    public function disconnectTelegram(): JsonResponse
    {
        $user = auth()->user();

        $user->disconnectTelegram();

        return response()->json([
            'success' => true,
            'message' => 'Telegram disconnected',
        ]);
    }

    /**
     * Save push token
     */
    public function savePushToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $user = auth()->user();
        $user->update(['push_token' => $validated['token']]);

        return response()->json([
            'success' => true,
            'message' => 'Push token saved',
        ]);
    }

    /**
     * Send test notification (for admins)
     */
    public function sendTest(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'channel' => 'nullable|in:email,telegram,push,all',
        ]);

        $targetUser = isset($validated['user_id'])
            ? User::find($validated['user_id'])
            : $user;

        $channels = $validated['channel'] === 'all' || !isset($validated['channel'])
            ? null
            : [$validated['channel'], 'in_app'];

        $notification = $this->notificationService->send(
            $targetUser,
            Notification::TYPE_SYSTEM,
            'Тестовое уведомление',
            'Это тестовое уведомление для проверки работы системы.',
            ['test' => true],
            $channels
        );

        return response()->json([
            'success' => true,
            'message' => 'Test notification sent',
            'data' => $notification,
        ]);
    }

    /**
     * Send notification to user (for managers/admins)
     */
    public function sendToUser(Request $request): JsonResponse
    {
        $currentUser = auth()->user();

        if (!$currentUser->isManager()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        $targetUser = User::find($validated['user_id']);

        $notification = $this->notificationService->sendCustom(
            $targetUser,
            $validated['title'],
            $validated['message']
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification sent',
            'data' => $notification,
        ]);
    }

    /**
     * Send notification to all staff (for managers/admins)
     */
    public function sendToAll(Request $request): JsonResponse
    {
        $currentUser = auth()->user();

        if (!$currentUser->isManager()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'roles' => 'nullable|array',
        ]);

        $notifications = $this->notificationService->sendToRestaurantStaff(
            $currentUser->restaurant_id,
            Notification::TYPE_CUSTOM,
            $validated['title'],
            $validated['message'],
            [],
            $validated['roles'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Notifications sent to ' . count($notifications) . ' users',
            'count' => count($notifications),
        ]);
    }
}
