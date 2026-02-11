<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiIdempotencyKey extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'idempotency_key',
        'api_client_id',
        'user_id',
        'method',
        'path',
        'request_hash',
        'status_code',
        'response_body',
        'response_headers',
        'created_at',
        'expires_at',
    ];

    protected $casts = [
        'response_headers' => 'array',
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Default TTL: 24 hours
    const DEFAULT_TTL_HOURS = 24;

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the key has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Find existing key for client
     */
    public static function findForClient(?int $apiClientId, ?int $userId, string $key): ?self
    {
        if (!$apiClientId && !$userId) {
            return null; // No scope = cannot match safely
        }

        return self::where('idempotency_key', $key)
            ->where(function ($q) use ($apiClientId, $userId) {
                if ($apiClientId) {
                    $q->where('api_client_id', $apiClientId);
                }
                if ($userId) {
                    $q->where('user_id', $userId);
                }
            })
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Create new idempotency record
     */
    public static function store(
        string $key,
        ?int $apiClientId,
        ?int $userId,
        string $method,
        string $path,
        string $requestHash,
        int $statusCode,
        string $responseBody,
        ?array $responseHeaders = null,
        int $ttlHours = self::DEFAULT_TTL_HOURS
    ): self {
        if (!$apiClientId && !$userId) {
            throw new \InvalidArgumentException('Idempotency key requires api_client_id or user_id');
        }

        return self::create([
            'idempotency_key' => $key,
            'api_client_id' => $apiClientId,
            'user_id' => $userId,
            'method' => $method,
            'path' => $path,
            'request_hash' => $requestHash,
            'status_code' => $statusCode,
            'response_body' => $responseBody,
            'response_headers' => $responseHeaders,
            'created_at' => now(),
            'expires_at' => now()->addHours($ttlHours),
        ]);
    }

    /**
     * Cleanup expired keys
     */
    public static function cleanup(): int
    {
        return self::where('expires_at', '<', now())->delete();
    }
}
