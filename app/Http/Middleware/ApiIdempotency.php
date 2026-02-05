<?php

namespace App\Http\Middleware;

use App\Models\ApiIdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiIdempotency
{
    /**
     * Idempotency key header name
     */
    const HEADER_NAME = 'X-Idempotency-Key';

    /**
     * Methods that support idempotency
     */
    const IDEMPOTENT_METHODS = ['POST', 'PATCH', 'PUT'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only process for mutating methods
        if (!in_array($request->method(), self::IDEMPOTENT_METHODS)) {
            return $next($request);
        }

        $idempotencyKey = $request->header(self::HEADER_NAME);

        // If no key provided, proceed normally
        if (empty($idempotencyKey)) {
            return $next($request);
        }

        // Validate key format (max 64 chars, alphanumeric + dashes)
        if (!preg_match('/^[a-zA-Z0-9\-_]{1,64}$/', $idempotencyKey)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_IDEMPOTENCY_KEY',
                    'message' => 'Idempotency key must be 1-64 alphanumeric characters (dashes and underscores allowed)',
                ],
            ], 400);
        }

        // Get client/user ID
        $apiClientId = $request->attributes->get('api_client_id');
        $userId = auth()->id();

        // Look for existing key
        $existing = ApiIdempotencyKey::findForClient($apiClientId, $userId, $idempotencyKey);

        if ($existing) {
            // Verify request hash matches (same request body)
            $currentHash = $this->hashRequest($request);

            if ($existing->request_hash !== $currentHash) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'IDEMPOTENCY_KEY_REUSED',
                        'message' => 'Idempotency key already used with different request parameters',
                    ],
                ], 422);
            }

            // Return cached response
            return response($existing->response_body, $existing->status_code)
                ->withHeaders(array_merge(
                    $existing->response_headers ?? [],
                    [
                        'X-Idempotent-Replayed' => 'true',
                        'X-Idempotent-Original-Request-Id' => $existing->id,
                    ]
                ));
        }

        // Process request
        $response = $next($request);

        // Only cache successful responses (2xx) or client errors (4xx)
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 200 && $statusCode < 500) {
            try {
                ApiIdempotencyKey::store(
                    key: $idempotencyKey,
                    apiClientId: $apiClientId,
                    userId: $userId,
                    method: $request->method(),
                    path: $request->path(),
                    requestHash: $this->hashRequest($request),
                    statusCode: $statusCode,
                    responseBody: $response->getContent(),
                    responseHeaders: $this->getResponseHeaders($response)
                );
            } catch (\Exception $e) {
                // Log but don't fail the request
                report($e);
            }
        }

        return $response;
    }

    /**
     * Create hash of request for comparison
     */
    protected function hashRequest(Request $request): string
    {
        $data = [
            'method' => $request->method(),
            'path' => $request->path(),
            'body' => $request->getContent(),
        ];

        return hash('sha256', json_encode($data));
    }

    /**
     * Extract headers to cache
     */
    protected function getResponseHeaders(Response $response): array
    {
        $headers = [];
        $include = ['Content-Type', 'X-Request-ID'];

        foreach ($include as $name) {
            if ($response->headers->has($name)) {
                $headers[$name] = $response->headers->get($name);
            }
        }

        return $headers;
    }
}
