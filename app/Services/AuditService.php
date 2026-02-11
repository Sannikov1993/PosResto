<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Сервис аудит-логирования
 *
 * Записывает security-значимые события в таблицу audit_logs.
 * Использует fire-and-forget паттерн — ошибки записи не блокируют основной flow.
 */
class AuditService
{
    /**
     * Записать событие аудита
     */
    public static function log(
        string $eventType,
        string $severity = AuditLog::SEVERITY_INFO,
        ?int $userId = null,
        ?int $tenantId = null,
        ?string $resourceType = null,
        ?int $resourceId = null,
        array $metadata = [],
        ?Request $request = null,
    ): void {
        try {
            $request ??= request();

            AuditLog::create([
                'tenant_id' => $tenantId ?? auth()->user()?->tenant_id,
                'user_id' => $userId ?? auth()->id(),
                'event_type' => $eventType,
                'severity' => $severity,
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'metadata' => !empty($metadata) ? $metadata : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Fire-and-forget: не ломаем основной flow
            Log::error('AuditService: failed to write audit log', [
                'error' => $e->getMessage(),
                'event_type' => $eventType,
            ]);
        }
    }

    /**
     * Записать событие входа
     */
    public static function logLogin(int $userId, ?int $tenantId = null, array $extra = []): void
    {
        self::log(
            eventType: AuditLog::EVENT_LOGIN,
            userId: $userId,
            tenantId: $tenantId,
            resourceType: 'User',
            resourceId: $userId,
            metadata: $extra,
        );
    }

    /**
     * Записать неудачную попытку входа
     */
    public static function logLoginFailed(string $email, array $extra = []): void
    {
        self::log(
            eventType: AuditLog::EVENT_LOGIN_FAILED,
            severity: AuditLog::SEVERITY_WARNING,
            metadata: array_merge(['email' => $email], $extra),
        );
    }

    /**
     * Записать получение webhook
     */
    public static function logWebhook(string $source, array $extra = []): void
    {
        self::log(
            eventType: AuditLog::EVENT_WEBHOOK_RECEIVED,
            metadata: array_merge(['source' => $source], $extra),
        );
    }

    /**
     * Записать привязку устройства
     */
    public static function logDeviceLinked(int $deviceId, string $deviceType, array $extra = []): void
    {
        self::log(
            eventType: AuditLog::EVENT_DEVICE_LINKED,
            resourceType: $deviceType,
            resourceId: $deviceId,
            metadata: $extra,
        );
    }
}
