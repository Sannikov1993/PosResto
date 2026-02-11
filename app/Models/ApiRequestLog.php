<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NOTE: Intentionally does NOT use BelongsToTenant trait.
 * Written from queue jobs / middleware where tenant scope may not be set.
 * Uses explicit tenant_id column for manual filtering.
 */
class ApiRequestLog extends Model
{
    use HasFactory;

    /**
     * Disable auto-timestamps (we only use created_at)
     */
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'tenant_id',
        'restaurant_id',
        'api_client_id',
        'user_id',
        'method',
        'path',
        'full_url',
        'query_params',
        'request_headers',
        'request_body',
        'request_size',
        'status_code',
        'response_headers',
        'response_body',
        'response_size',
        'response_time_ms',
        'db_queries_time_ms',
        'db_queries_count',
        'memory_peak_mb',
        'ip_address',
        'user_agent',
        'country_code',
        'region',
        'rate_limit',
        'rate_remaining',
        'rate_limited',
        'error_code',
        'error_message',
        'api_version',
        'created_at',
    ];

    protected $casts = [
        'query_params' => 'array',
        'request_headers' => 'array',
        'response_headers' => 'array',
        'request_size' => 'integer',
        'response_size' => 'integer',
        'response_time_ms' => 'decimal:2',
        'db_queries_time_ms' => 'decimal:2',
        'db_queries_count' => 'integer',
        'memory_peak_mb' => 'integer',
        'rate_limit' => 'integer',
        'rate_remaining' => 'integer',
        'rate_limited' => 'boolean',
        'status_code' => 'integer',
        'created_at' => 'datetime',
    ];

    // ===== RELATIONSHIPS =====

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ===== SCOPES =====

    public function scopeSuccessful($query)
    {
        return $query->where('status_code', '>=', 200)
            ->where('status_code', '<', 300);
    }

    public function scopeClientErrors($query)
    {
        return $query->where('status_code', '>=', 400)
            ->where('status_code', '<', 500);
    }

    public function scopeServerErrors($query)
    {
        return $query->where('status_code', '>=', 500);
    }

    public function scopeRateLimited($query)
    {
        return $query->where('rate_limited', true);
    }

    public function scopeForPath($query, string $path)
    {
        return $query->where('path', 'like', $path . '%');
    }

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('api_client_id', $clientId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeSlowRequests($query, int $thresholdMs = 1000)
    {
        return $query->where('response_time_ms', '>', $thresholdMs);
    }

    public function scopeForPeriod($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    // ===== HELPERS =====

    /**
     * Check if request was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }

    /**
     * Check if request was a client error
     */
    public function isClientError(): bool
    {
        return $this->status_code >= 400 && $this->status_code < 500;
    }

    /**
     * Check if request was a server error
     */
    public function isServerError(): bool
    {
        return $this->status_code >= 500;
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        return match (true) {
            $this->status_code >= 500 => 'Server Error',
            $this->status_code >= 400 => 'Client Error',
            $this->status_code >= 300 => 'Redirect',
            $this->status_code >= 200 => 'Success',
            default => 'Unknown',
        };
    }

    /**
     * Get formatted response time
     */
    public function getFormattedResponseTime(): string
    {
        if ($this->response_time_ms >= 1000) {
            return round($this->response_time_ms / 1000, 2) . 's';
        }
        return round($this->response_time_ms) . 'ms';
    }

    // ===== STATISTICS =====

    /**
     * Get aggregated stats for a period
     */
    public static function getStats(
        int $tenantId,
        ?int $apiClientId = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $query = self::where('tenant_id', $tenantId);

        if ($apiClientId) {
            $query->where('api_client_id', $apiClientId);
        }

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $totalRequests = $query->count();
        $successfulRequests = (clone $query)->successful()->count();
        $errorRequests = $totalRequests - $successfulRequests;
        $rateLimitedRequests = (clone $query)->rateLimited()->count();

        $avgResponseTime = (clone $query)->avg('response_time_ms') ?? 0;
        $p95ResponseTime = self::calculatePercentile(
            (clone $query)->pluck('response_time_ms')->toArray(),
            95
        );

        return [
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'error_requests' => $errorRequests,
            'rate_limited_requests' => $rateLimitedRequests,
            'success_rate' => $totalRequests > 0
                ? round($successfulRequests / $totalRequests * 100, 2)
                : 0,
            'avg_response_time_ms' => round($avgResponseTime, 2),
            'p95_response_time_ms' => round($p95ResponseTime, 2),
        ];
    }

    /**
     * Calculate percentile
     */
    protected static function calculatePercentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);

        if (floor($index) == $index) {
            return $values[(int) $index];
        }

        $lower = $values[(int) floor($index)];
        $upper = $values[(int) ceil($index)];

        return $lower + ($upper - $lower) * ($index - floor($index));
    }

    // ===== CLEANUP =====

    /**
     * Delete old logs
     */
    public static function cleanup(?int $retentionDays = null): int
    {
        $retentionDays = $retentionDays ?? config('api.logging.retention_days', 90);

        return self::where('created_at', '<', now()->subDays($retentionDays))->delete();
    }
}
