<?php

namespace App\Services;

use App\Models\DeviceSession;
use App\Models\User;
use Carbon\Carbon;

class DeviceSessionService
{
    /**
     * Маппинг ролей на приложения
     */
    private static array $roleAppAccess = [
        'super_admin' => ['pos', 'waiter', 'courier', 'kitchen', 'backoffice'],
        'owner' => ['pos', 'waiter', 'courier', 'kitchen', 'backoffice'],
        'admin' => ['pos', 'kitchen', 'backoffice'],
        'manager' => ['pos', 'waiter', 'backoffice'],
        'waiter' => ['waiter'],
        'cook' => ['kitchen'],
        'cashier' => ['pos'],
        'courier' => ['courier'],
        'hostess' => ['backoffice'],
    ];

    /**
     * Создать сессию устройства
     */
    public function createSession(
        User $user,
        string $deviceFingerprint,
        string $appType,
        ?string $deviceName = null
    ): DeviceSession {
        // Проверяем право доступа к приложению
        if (!$this->canAccessApp($user, $appType)) {
            throw new \Exception("Роль {$user->role} не имеет доступа к приложению {$appType}");
        }

        // ✅ Проверяем существующую активную сессию для этой комбинации
        $existingSession = DeviceSession::where('user_id', $user->id)
            ->where('device_fingerprint', $deviceFingerprint)
            ->where('app_type', $appType)
            ->active()
            ->first();

        if ($existingSession) {
            // Обновляем активность и продлеваем срок действия
            $existingSession->update([
                'last_activity_at' => now(),
                'expires_at' => now()->addDays(30),
                'device_name' => $deviceName ?? $existingSession->device_name,
            ]);

            return $existingSession;
        }

        // Создаем новую сессию если не нашли существующую
        $token = DeviceSession::generate();

        return DeviceSession::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'device_fingerprint' => $deviceFingerprint,
            'device_name' => $deviceName,
            'app_type' => $appType,
            'token' => $token,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    /**
     * Получить пользователя по токену устройства
     */
    public function getUserByToken(string $token): ?User
    {
        $session = DeviceSession::where('token', $token)
            ->active()
            ->first();

        if (!$session) {
            return null;
        }

        // Проверяем активность пользователя
        $user = User::where('id', $session->user_id)
            ->where('is_active', true)
            ->first();

        if (!$user) {
            // Удаляем сессию неактивного пользователя
            $session->delete();
            return null;
        }

        // Обновляем активность
        $session->updateActivity();

        return $user;
    }

    /**
     * Получить список пользователей на устройстве
     * Используется для терминалов (POS, Kitchen) где работают несколько человек
     * Фильтрует по tenant_id и restaurant_id для мультитенантной изоляции
     */
    public function getDeviceUsers(string $deviceFingerprint, string $appType, ?int $restaurantId = null): array
    {
        // Определяем tenant_id устройства по первой активной сессии
        $deviceTenantId = DeviceSession::where('device_fingerprint', $deviceFingerprint)
            ->where('app_type', $appType)
            ->whereNotNull('tenant_id')
            ->active()
            ->value('tenant_id');

        $query = DeviceSession::where('device_fingerprint', $deviceFingerprint)
            ->where('app_type', $appType)
            ->active()
            ->with('user');

        // Фильтруем по tenant_id если он определён
        if ($deviceTenantId) {
            $query->where('tenant_id', $deviceTenantId);
        }

        $sessions = $query->get();
        $users = [];

        foreach ($sessions as $session) {
            $user = $session->user;

            // Фильтруем неактивных пользователей
            if (!$user || !$user->is_active) {
                $session->delete();
                continue;
            }

            // Проверяем права доступа к приложению
            if (!$this->canAccessApp($user, $appType)) {
                continue;
            }

            // Фильтруем по restaurant_id если указан
            // Пользователи без restaurant_id (super_admin, owner) видны на всех устройствах
            if ($restaurantId !== null && $user->restaurant_id !== null && (int)$user->restaurant_id !== (int)$restaurantId) {
                continue;
            }

            $users[] = [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'role_label' => $user->role_label,
                'avatar' => $user->avatar,
                'has_pin' => $user->has_pin,
            ];
        }

        return $users;
    }

    /**
     * Удалить все сессии пользователя
     * Используется при увольнении
     */
    public function revokeAllUserSessions(int $userId): void
    {
        DeviceSession::where('user_id', $userId)->delete();
    }

    /**
     * Удалить конкретную сессию
     */
    public function revokeSession(string $token): bool
    {
        return DeviceSession::where('token', $token)->delete() > 0;
    }

    /**
     * Удалить сессии пользователя для конкретного приложения
     */
    public function revokeUserAppSessions(int $userId, string $appType): int
    {
        return DeviceSession::where('user_id', $userId)
            ->where('app_type', $appType)
            ->delete();
    }

    /**
     * Очистить истекшие сессии
     */
    public function cleanupExpired(): int
    {
        return DeviceSession::cleanupExpired();
    }

    /**
     * Получить активные сессии пользователя
     */
    public function getUserSessions(int $userId): array
    {
        return DeviceSession::where('user_id', $userId)
            ->active()
            ->orderBy('last_activity_at', 'desc')
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'app_type' => $session->app_type,
                    'app_name' => DeviceSession::getAppTypes()[$session->app_type] ?? $session->app_type,
                    'device_name' => $session->device_name,
                    'last_activity_at' => $session->last_activity_at,
                    'is_current' => false, // Будет установлено на фронтенде
                ];
            })
            ->toArray();
    }

    /**
     * Проверка доступа роли к приложению (Enterprise-level)
     * Использует Role из БД, fallback на статический массив
     */
    public function canAccessApp(User $user, string $appType): bool
    {
        // Superadmin и tenant owner имеют полный доступ
        if ($user->isSuperAdmin() || $user->isTenantOwner()) {
            return true;
        }

        // Маппинг app_type на поле Role
        $interfaceMap = [
            'pos' => 'can_access_pos',
            'backoffice' => 'can_access_backoffice',
            'kitchen' => 'can_access_kitchen',
            'delivery' => 'can_access_delivery',
            'waiter' => 'can_access_pos', // Waiter app использует POS доступ
            'courier' => 'can_access_delivery',
        ];

        $field = $interfaceMap[$appType] ?? null;

        if (!$field) {
            // Неизвестный app_type - fallback на старую логику
            $allowedApps = self::$roleAppAccess[$user->role] ?? [];
            return in_array($appType, $allowedApps);
        }

        // Проверяем через Role из БД
        $role = $user->getEffectiveRole();

        if ($role) {
            return (bool) $role->$field;
        }

        // Fallback на статический массив если роль не найдена в БД
        $allowedApps = self::$roleAppAccess[$user->role] ?? [];
        return in_array($appType, $allowedApps);
    }

    /**
     * Получить список приложений доступных роли
     */
    public static function getAppAccessForRole(string $role): array
    {
        return self::$roleAppAccess[$role] ?? [];
    }
}
