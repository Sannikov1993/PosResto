<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeviceSession;
use Illuminate\Support\Facades\Cache;

/**
 * Сервис ротации токенов устройств
 *
 * Позволяет безопасно обновлять токены с grace period для старого токена.
 * Grace period: 5 минут — старый токен продолжает работать после ротации.
 */
class TokenRotationService
{
    private const GRACE_PERIOD_SECONDS = 300; // 5 минут

    /**
     * Ротация токена устройства
     *
     * @return array{new_token: string, expires_at: string, grace_period_seconds: int}
     */
    public function rotate(DeviceSession $session): array
    {
        $newToken = DeviceSession::generate();
        $newHash = hash('sha256', $newToken);

        // Сохраняем старый хеш в rotation_token_hash для grace period
        $oldHash = $session->token_hash;

        $session->update([
            'token' => $newToken,
            'token_hash' => $newHash,
            'rotation_token_hash' => $oldHash,
            'rotated_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        // Ставим grace period через Redis/Cache
        if ($oldHash) {
            Cache::put(
                "token_grace:{$oldHash}",
                $session->id,
                self::GRACE_PERIOD_SECONDS,
            );
        }

        AuditService::log(
            eventType: 'token_rotated',
            userId: $session->user_id,
            tenantId: $session->tenant_id,
            resourceType: 'DeviceSession',
            resourceId: $session->id,
        );

        return [
            'new_token' => $newToken,
            'expires_at' => $session->expires_at->toIso8601String(),
            'grace_period_seconds' => self::GRACE_PERIOD_SECONDS,
        ];
    }

    /**
     * Найти сессию по rotated (старому) токену в grace period
     */
    public function findByGracePeriodToken(string $token): ?DeviceSession
    {
        $hash = hash('sha256', $token);
        $sessionId = Cache::get("token_grace:{$hash}");

        if (!$sessionId) {
            return null;
        }

        return DeviceSession::find($sessionId);
    }

    /**
     * Проверка, не превышен ли максимальный срок жизни сессии
     */
    public function isMaxLifetimeExceeded(DeviceSession $session): bool
    {
        return $session->max_lifetime_at && $session->max_lifetime_at->isPast();
    }
}
