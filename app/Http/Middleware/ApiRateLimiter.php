<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiting middleware for public API
 *
 * Uses Redis sliding window algorithm for precise rate limiting.
 * Supports per-client rate plans and custom limits.
 */
class ApiRateLimiter
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if rate limiting is enabled
        if (!config('api.rate_limiting.enabled', true)) {
            return $next($request);
        }

        // Get API client from request
        /** @var ApiClient|null $apiClient */
        $apiClient = $request->attributes->get('api_client');

        // Determine rate limit key and limits
        $key = $this->getRateLimitKey($request, $apiClient);
        $limits = $this->getLimits($apiClient);

        // Check rate limit
        $result = $this->checkRateLimit($key, $limits);

        // Store rate limit info in request for logging
        $request->attributes->set('rate_limit', $limits['requests_per_minute']);
        $request->attributes->set('rate_remaining', $result['remaining']);

        // Add rate limit headers to response
        $response = $result['allowed']
            ? $next($request)
            : $this->rateLimitExceededResponse($result);

        return $this->addRateLimitHeaders($response, $limits, $result);
    }

    /**
     * Get unique key for rate limiting
     */
    protected function getRateLimitKey(Request $request, ?ApiClient $apiClient): string
    {
        $prefix = 'api_rate_limit:';

        if ($apiClient) {
            return $prefix . 'client:' . $apiClient->id;
        }

        // Fallback to IP-based limiting (shouldn't happen with auth middleware)
        return $prefix . 'ip:' . $request->ip();
    }

    /**
     * Get rate limits for the client
     */
    protected function getLimits(?ApiClient $apiClient): array
    {
        $plans = config('api.rate_limiting.plans', []);
        $defaultPlan = config('api.rate_limiting.default_plan', 'free');

        if ($apiClient) {
            // Check for custom rate limit
            if ($apiClient->custom_rate_limit) {
                return [
                    'requests_per_minute' => $apiClient->custom_rate_limit,
                    'burst' => min(50, $apiClient->custom_rate_limit / 6),
                    'daily_limit' => null,
                ];
            }

            // Use plan-based limits
            $plan = $plans[$apiClient->rate_plan] ?? $plans[$defaultPlan] ?? [];
        } else {
            $plan = $plans[$defaultPlan] ?? [];
        }

        return [
            'requests_per_minute' => $plan['requests_per_minute'] ?? 60,
            'burst' => $plan['burst'] ?? 10,
            'daily_limit' => $plan['daily_limit'] ?? 1000,
        ];
    }

    /**
     * Check rate limit using sliding window algorithm
     */
    protected function checkRateLimit(string $key, array $limits): array
    {
        $now = microtime(true);
        $windowSize = 60; // 1 minute window
        $maxRequests = $limits['requests_per_minute'];

        try {
            $redis = Redis::connection(config('api.rate_limiting.connection', 'default'));

            // Use sorted set for sliding window
            $windowKey = $key . ':window';

            // Remove old entries outside the window
            $redis->zremrangebyscore($windowKey, '-inf', $now - $windowSize);

            // Count current requests in window
            $currentCount = $redis->zcard($windowKey);

            if ($currentCount >= $maxRequests) {
                // Get oldest entry to calculate retry time
                $oldest = $redis->zrange($windowKey, 0, 0, 'WITHSCORES');
                $retryAfter = !empty($oldest)
                    ? (int) ceil($windowSize - ($now - reset($oldest)))
                    : $windowSize;

                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset' => (int) ($now + $retryAfter),
                    'retry_after' => max(1, $retryAfter),
                ];
            }

            // Add current request
            $redis->zadd($windowKey, $now, $now . ':' . uniqid());

            // Set TTL on the key (auto cleanup)
            $redis->expire($windowKey, $windowSize + 10);

            // Check daily limit if set
            if ($limits['daily_limit']) {
                $dailyResult = $this->checkDailyLimit($key, $limits['daily_limit']);
                if (!$dailyResult['allowed']) {
                    return $dailyResult;
                }
            }

            return [
                'allowed' => true,
                'remaining' => $maxRequests - $currentCount - 1,
                'reset' => (int) ($now + $windowSize),
                'retry_after' => 0,
            ];
        } catch (\Exception $e) {
            \Log::critical('Rate limiter Redis unavailable', [
                'error' => $e->getMessage(),
                'key' => $key,
            ]);

            // Fail-closed для мутирующих запросов (POST/PUT/PATCH/DELETE)
            $method = request()->method();
            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset' => (int) ($now + $windowSize),
                    'retry_after' => 60,
                ];
            }

            // Fail-open для GET с in-memory counter fallback
            static $inMemoryCounters = [];
            $counterKey = $key . ':' . floor($now / $windowSize);
            $inMemoryCounters[$counterKey] = ($inMemoryCounters[$counterKey] ?? 0) + 1;

            if ($inMemoryCounters[$counterKey] > $maxRequests) {
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset' => (int) ($now + $windowSize),
                    'retry_after' => $windowSize,
                ];
            }

            return [
                'allowed' => true,
                'remaining' => max(0, $maxRequests - $inMemoryCounters[$counterKey]),
                'reset' => (int) ($now + $windowSize),
                'retry_after' => 0,
            ];
        }
    }

    /**
     * Check daily limit
     */
    protected function checkDailyLimit(string $key, int $dailyLimit): array
    {
        $dailyKey = $key . ':daily:' . date('Y-m-d');

        try {
            $redis = Redis::connection(config('api.rate_limiting.connection', 'default'));

            $count = (int) $redis->incr($dailyKey);

            // Set expiry to end of day (UTC)
            if ($count === 1) {
                $redis->expireat($dailyKey, strtotime('tomorrow'));
            }

            if ($count > $dailyLimit) {
                $resetTime = strtotime('tomorrow');

                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset' => $resetTime,
                    'retry_after' => $resetTime - time(),
                    'daily_exceeded' => true,
                ];
            }

            return ['allowed' => true];
        } catch (\Exception $e) {
            return ['allowed' => true];
        }
    }

    /**
     * Add rate limit headers to response
     */
    protected function addRateLimitHeaders(
        Response $response,
        array $limits,
        array $result
    ): Response {
        $headers = config('api.rate_limiting.headers', [
            'limit' => 'X-RateLimit-Limit',
            'remaining' => 'X-RateLimit-Remaining',
            'reset' => 'X-RateLimit-Reset',
        ]);

        $response->headers->set($headers['limit'], (string) $limits['requests_per_minute']);
        $response->headers->set($headers['remaining'], (string) max(0, $result['remaining']));
        $response->headers->set($headers['reset'], (string) $result['reset']);

        if (!$result['allowed'] && $result['retry_after'] > 0) {
            $response->headers->set('Retry-After', (string) $result['retry_after']);
        }

        return $response;
    }

    /**
     * Return rate limit exceeded response
     */
    protected function rateLimitExceededResponse(array $result): Response
    {
        $code = isset($result['daily_exceeded']) && $result['daily_exceeded']
            ? 'DAILY_LIMIT_EXCEEDED'
            : 'RATE_LIMIT_EXCEEDED';

        $message = config("api.error_codes.{$code}", 'Rate limit exceeded');

        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'meta' => [
                'retry_after' => $result['retry_after'],
                'reset_at' => date('Y-m-d\TH:i:sP', $result['reset']),
            ],
        ], 429);
    }
}
