<?php

namespace App\Http\Resources\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

trait ApiResponses
{
    /**
     * Return a success response
     */
    protected function success(
        mixed $data = null,
        ?string $message = null,
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($message) {
            $response['message'] = $message;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $this->respond($response, $statusCode);
    }

    /**
     * Return a created response (201)
     */
    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message ?? 'Created', 201);
    }

    /**
     * Return an accepted response (202)
     */
    protected function accepted(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message ?? 'Accepted', 202);
    }

    /**
     * Return a no content response (204)
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Return an error response
     */
    protected function error(
        string $code,
        string $message,
        int $statusCode = 400,
        ?array $errors = null,
        ?array $meta = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($errors !== null) {
            $response['error']['errors'] = $errors;
        }

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return $this->respond($response, $statusCode);
    }

    /**
     * Return a validation error response
     */
    protected function validationError(array $errors, ?string $message = null): JsonResponse
    {
        return $this->error(
            'VALIDATION_ERROR',
            $message ?? config('api.error_codes.VALIDATION_ERROR', 'Validation failed'),
            422,
            $errors
        );
    }

    /**
     * Return an unauthorized response
     */
    protected function unauthorized(?string $message = null): JsonResponse
    {
        return $this->error(
            'UNAUTHORIZED',
            $message ?? config('api.error_codes.UNAUTHORIZED', 'Authentication required'),
            401
        );
    }

    /**
     * Return a forbidden response (insufficient permissions)
     */
    protected function forbidden(?string $message = null): JsonResponse
    {
        return $this->error(
            'INSUFFICIENT_SCOPE',
            $message ?? config('api.error_codes.INSUFFICIENT_SCOPE', 'Insufficient permissions'),
            403
        );
    }

    /**
     * Return a not found response
     */
    protected function notFound(?string $message = null): JsonResponse
    {
        return $this->error(
            'NOT_FOUND',
            $message ?? config('api.error_codes.NOT_FOUND', 'Resource not found'),
            404
        );
    }

    /**
     * Return a conflict response
     */
    protected function conflict(?string $message = null): JsonResponse
    {
        return $this->error(
            'CONFLICT',
            $message ?? config('api.error_codes.CONFLICT', 'Conflict'),
            409
        );
    }

    /**
     * Return a rate limit exceeded response
     */
    protected function rateLimitExceeded(int $retryAfter = 60): JsonResponse
    {
        return $this->error(
            'RATE_LIMIT_EXCEEDED',
            config('api.error_codes.RATE_LIMIT_EXCEEDED', 'Rate limit exceeded'),
            429,
            null,
            ['retry_after' => $retryAfter]
        );
    }

    /**
     * Return a server error response
     */
    protected function serverError(?string $message = null): JsonResponse
    {
        return $this->error(
            'INTERNAL_ERROR',
            $message ?? config('api.error_codes.INTERNAL_ERROR', 'Internal server error'),
            500
        );
    }

    /**
     * Return a service unavailable response
     */
    protected function serviceUnavailable(?string $message = null): JsonResponse
    {
        return $this->error(
            'SERVICE_UNAVAILABLE',
            $message ?? config('api.error_codes.SERVICE_UNAVAILABLE', 'Service temporarily unavailable'),
            503
        );
    }

    /**
     * Return a business logic error response
     */
    protected function businessError(string $code, string $message, int $statusCode = 422): JsonResponse
    {
        return $this->error($code, $message, $statusCode);
    }

    /**
     * Return paginated response
     */
    protected function paginated(
        LengthAwarePaginator $paginator,
        ?string $resourceClass = null,
        array $additionalMeta = []
    ): JsonResponse {
        $items = $paginator->items();

        // Transform items if resource class provided
        if ($resourceClass && class_exists($resourceClass)) {
            $items = $resourceClass::collection(collect($items))->resolve();
        }

        $meta = array_merge([
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ], $additionalMeta);

        return $this->success($items, null, 200, $meta);
    }

    /**
     * Return collection response with optional transformation
     */
    protected function collection(
        Collection $collection,
        ?string $resourceClass = null,
        array $meta = []
    ): JsonResponse {
        $items = $collection;

        if ($resourceClass && class_exists($resourceClass)) {
            $items = $resourceClass::collection($collection)->resolve();
        }

        return $this->success($items, null, 200, $meta);
    }

    /**
     * Build JSON response with standard headers
     */
    protected function respond(array $data, int $statusCode = 200): JsonResponse
    {
        $response = response()->json($data, $statusCode, [
            'Content-Type' => 'application/json',
        ]);

        // Add request ID if available
        $requestId = request()->attributes->get('api_request_id');
        if ($requestId && config('api.response.include_request_id', true)) {
            $response->header('X-Request-ID', $requestId);
        }

        return $response;
    }

    /**
     * Format datetime according to API config
     */
    protected function formatDateTime(?\DateTimeInterface $dateTime): ?string
    {
        if (!$dateTime) {
            return null;
        }

        return $dateTime->format(config('api.response.datetime_format', 'Y-m-d\TH:i:s.uP'));
    }

    /**
     * Format date according to API config
     */
    protected function formatDate(?\DateTimeInterface $date): ?string
    {
        if (!$date) {
            return null;
        }

        return $date->format(config('api.response.date_format', 'Y-m-d'));
    }
}
