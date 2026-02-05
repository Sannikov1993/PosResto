<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebhookDelivery extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'api_client_id',
        'restaurant_id',
        'event_type',
        'payload',
        'signature',
        'status',
        'attempt_count',
        'max_attempts',
        'last_status_code',
        'last_response',
        'last_error',
        'last_response_time_ms',
        'created_at',
        'next_attempt_at',
        'delivered_at',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';
    const STATUS_EXPIRED = 'expired';

    // Exponential backoff intervals in minutes
    const RETRY_INTERVALS = [1, 5, 30, 60, 120, 240, 480, 1440]; // 1min, 5min, 30min, 1hr, 2hr, 4hr, 8hr, 24hr

    // Default expiry: 7 days
    const DEFAULT_EXPIRES_DAYS = 7;

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * Create a new webhook delivery
     */
    public static function createEvent(
        ApiClient $apiClient,
        string $eventType,
        array $data,
        int $restaurantId
    ): self {
        $eventId = (string) Str::uuid();
        $timestamp = now()->toIso8601String();

        $payload = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'timestamp' => $timestamp,
            'data' => $data,
        ];

        $signature = hash_hmac('sha256', json_encode($payload), $apiClient->webhook_secret ?? '');

        return self::create([
            'event_id' => $eventId,
            'api_client_id' => $apiClient->id,
            'restaurant_id' => $restaurantId,
            'event_type' => $eventType,
            'payload' => $payload,
            'signature' => $signature,
            'status' => self::STATUS_PENDING,
            'attempt_count' => 0,
            'max_attempts' => count(self::RETRY_INTERVALS),
            'created_at' => now(),
            'next_attempt_at' => now(),
            'expires_at' => now()->addDays(self::DEFAULT_EXPIRES_DAYS),
        ]);
    }

    /**
     * Mark as delivered
     */
    public function markDelivered(int $statusCode, ?string $response, int $responseTimeMs): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'last_status_code' => $statusCode,
            'last_response' => $response ? Str::limit($response, 2000) : null,
            'last_response_time_ms' => $responseTimeMs,
            'delivered_at' => now(),
            'next_attempt_at' => null,
        ]);
    }

    /**
     * Mark attempt failed and schedule retry
     */
    public function markFailed(int $statusCode, ?string $error, int $responseTimeMs): void
    {
        $this->increment('attempt_count');

        $updates = [
            'last_status_code' => $statusCode,
            'last_error' => $error ? Str::limit($error, 2000) : null,
            'last_response_time_ms' => $responseTimeMs,
        ];

        if ($this->attempt_count >= $this->max_attempts) {
            $updates['status'] = self::STATUS_FAILED;
            $updates['next_attempt_at'] = null;
        } else {
            $intervalMinutes = self::RETRY_INTERVALS[$this->attempt_count - 1] ?? 1440;
            $updates['next_attempt_at'] = now()->addMinutes($intervalMinutes);
        }

        $this->update($updates);
    }

    /**
     * Mark as expired (event too old to retry)
     */
    public function markExpired(): void
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
            'next_attempt_at' => null,
        ]);
    }

    /**
     * Check if should retry
     */
    public function shouldRetry(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->attempt_count < $this->max_attempts
            && $this->expires_at->isFuture();
    }

    /**
     * Get pending deliveries ready for attempt
     */
    public static function getPendingForDelivery(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('status', self::STATUS_PENDING)
            ->where('next_attempt_at', '<=', now())
            ->where('expires_at', '>', now())
            ->orderBy('next_attempt_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Cleanup old delivered/failed events
     */
    public static function cleanup(int $daysOld = 30): int
    {
        return self::where('created_at', '<', now()->subDays($daysOld))
            ->whereIn('status', [self::STATUS_DELIVERED, self::STATUS_FAILED, self::STATUS_EXPIRED])
            ->delete();
    }

    /**
     * Scope for API client
     */
    public function scopeForClient($query, int $apiClientId)
    {
        return $query->where('api_client_id', $apiClientId);
    }

    /**
     * Scope for event type
     */
    public function scopeOfType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }
}
