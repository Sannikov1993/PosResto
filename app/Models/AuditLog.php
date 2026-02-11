<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель аудит-лога
 *
 * Записывает security-значимые события:
 * - Аутентификация (login, failed login)
 * - Привязка устройств (kitchen device link)
 * - Webhook-события (Telegram, ATOL)
 * - Изменение прав доступа
 *
 * NOTE: Intentionally does NOT use BelongsToTenant trait.
 * Written during auth events (login, failed login) before tenant scope is established.
 * Uses explicit tenant_id column and scopeForTenant() for manual filtering.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'event_type',
        'severity',
        'ip',
        'user_agent',
        'resource_type',
        'resource_id',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // Severity levels
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    // Event types
    public const EVENT_LOGIN = 'login';
    public const EVENT_LOGIN_FAILED = 'login_failed';
    public const EVENT_LOGOUT = 'logout';
    public const EVENT_DEVICE_LINKED = 'device_linked';
    public const EVENT_DEVICE_LINK_FAILED = 'device_link_failed';
    public const EVENT_WEBHOOK_RECEIVED = 'webhook_received';
    public const EVENT_TOKEN_ROTATED = 'token_rotated';
    public const EVENT_SESSION_REVOKED = 'session_revoked';
    public const EVENT_PERMISSION_CHANGED = 'permission_changed';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', self::SEVERITY_CRITICAL);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
