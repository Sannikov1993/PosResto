<?php

namespace App\Http\Middleware;

use App\Jobs\LogApiRequest;
use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Log all API requests for analytics and debugging
 *
 * Supports async logging via queues for minimal latency impact.
 */
class ApiRequestLogger
{
    /**
     * Request start time
     */
    protected float $startTime;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if logging is enabled
        if (!config('api.logging.enabled', true)) {
            return $next($request);
        }

        // Check if path is excluded
        $path = $request->path();
        $excludedPaths = config('api.logging.excluded_paths', []);

        foreach ($excludedPaths as $excluded) {
            if (Str::is($excluded, $path)) {
                return $next($request);
            }
        }

        // Generate request ID
        $requestId = (string) Str::uuid();
        $request->attributes->set('api_request_id', $requestId);

        // Record start time
        $this->startTime = microtime(true);

        // Process request
        $response = $next($request);

        // Calculate response time
        $responseTimeMs = (microtime(true) - $this->startTime) * 1000;

        // Add request ID to response header
        $response->headers->set('X-Request-ID', $requestId);

        // Log the request
        $this->logRequest($request, $response, $requestId, $responseTimeMs);

        return $response;
    }

    /**
     * Log the request/response
     */
    protected function logRequest(
        Request $request,
        Response $response,
        string $requestId,
        float $responseTimeMs
    ): void {
        /** @var ApiClient|null $apiClient */
        $apiClient = $request->attributes->get('api_client');

        $data = [
            'request_id' => $requestId,
            'tenant_id' => $request->attributes->get('tenant_id'),
            'restaurant_id' => $request->attributes->get('restaurant_id'),
            'api_client_id' => $apiClient?->id,
            'user_id' => auth()->id(),

            // HTTP data
            'method' => $request->method(),
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'query_params' => $request->query() ?: null,

            // Request details
            'request_headers' => $this->filterHeaders($request->headers->all()),
            'request_body' => $this->shouldLogBody('request')
                ? $this->getRequestBody($request)
                : null,
            'request_size' => strlen($request->getContent()),

            // Response details
            'status_code' => $response->getStatusCode(),
            'response_headers' => $this->filterHeaders($response->headers->all()),
            'response_body' => $this->shouldLogBody('response')
                ? $this->getResponseBody($response)
                : null,
            'response_size' => strlen($response->getContent()),

            // Performance
            'response_time_ms' => round($responseTimeMs, 2),
            'db_queries_time_ms' => $this->getDbQueriesTime(),
            'db_queries_count' => $this->getDbQueriesCount(),
            'memory_peak_mb' => (int) (memory_get_peak_usage(true) / 1024 / 1024),

            // Client info
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),

            // Rate limiting info
            'rate_limit' => $request->attributes->get('rate_limit'),
            'rate_remaining' => $request->attributes->get('rate_remaining'),
            'rate_limited' => $response->getStatusCode() === 429,

            // Error info
            'error_code' => $this->getErrorCode($response),
            'error_message' => $this->getErrorMessage($response),

            // API version
            'api_version' => $this->extractApiVersion($request->path()),

            'created_at' => now(),
        ];

        // Dispatch logging job
        if (config('api.logging.async', true)) {
            LogApiRequest::dispatch($data);
        } else {
            // Sync logging (not recommended for production)
            (new LogApiRequest($data))->handle();
        }

        // Update API client statistics
        if ($apiClient) {
            $isError = $response->getStatusCode() >= 400;
            $apiClient->recordRequest($isError);
        }
    }

    /**
     * Check if body should be logged
     */
    protected function shouldLogBody(string $type): bool
    {
        return config("api.logging.log_{$type}_body", false);
    }

    /**
     * Get request body for logging
     */
    protected function getRequestBody(Request $request): ?string
    {
        $content = $request->getContent();

        if (empty($content)) {
            return null;
        }

        $maxSize = config('api.logging.max_body_size', 10000);

        if (strlen($content) > $maxSize) {
            return substr($content, 0, $maxSize) . '...[truncated]';
        }

        return $content;
    }

    /**
     * Get response body for logging
     */
    protected function getResponseBody(Response $response): ?string
    {
        $content = $response->getContent();

        if (empty($content)) {
            return null;
        }

        $maxSize = config('api.logging.max_body_size', 10000);

        if (strlen($content) > $maxSize) {
            return substr($content, 0, $maxSize) . '...[truncated]';
        }

        return $content;
    }

    /**
     * Filter sensitive headers
     */
    protected function filterHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'x-api-secret',
            'cookie',
            'set-cookie',
        ];

        $filtered = [];

        foreach ($headers as $key => $values) {
            $lowerKey = strtolower($key);

            if (in_array($lowerKey, $sensitiveHeaders)) {
                $filtered[$key] = ['***REDACTED***'];
            } else {
                // Keep only first value for each header
                $filtered[$key] = is_array($values) ? $values[0] : $values;
            }
        }

        return $filtered;
    }

    /**
     * Extract error code from response
     */
    protected function getErrorCode(Response $response): ?string
    {
        if ($response->getStatusCode() < 400) {
            return null;
        }

        $content = json_decode($response->getContent(), true);

        return $content['error']['code'] ?? null;
    }

    /**
     * Extract error message from response
     */
    protected function getErrorMessage(Response $response): ?string
    {
        if ($response->getStatusCode() < 400) {
            return null;
        }

        $content = json_decode($response->getContent(), true);

        return $content['error']['message'] ?? null;
    }

    /**
     * Extract API version from path
     */
    protected function extractApiVersion(string $path): ?string
    {
        if (preg_match('/^api\/v(\d+)/', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get total DB queries time
     */
    protected function getDbQueriesTime(): ?float
    {
        if (!app()->bound('db')) {
            return null;
        }

        try {
            $queryLog = \DB::getQueryLog();

            if (empty($queryLog)) {
                return null;
            }

            $totalTime = collect($queryLog)->sum('time');

            return round($totalTime, 2);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get total DB queries count
     */
    protected function getDbQueriesCount(): ?int
    {
        if (!app()->bound('db')) {
            return null;
        }

        try {
            return count(\DB::getQueryLog());
        } catch (\Exception $e) {
            return null;
        }
    }
}
