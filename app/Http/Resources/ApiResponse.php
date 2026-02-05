<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Unified API Response builder.
 *
 * Provides consistent response structure across all endpoints.
 *
 * Usage:
 *   return ApiResponse::success($reservation, 'Created');
 *   return ApiResponse::error('Not found', 404);
 *   return ApiResponse::paginated($reservations);
 */
final class ApiResponse
{
    /**
     * Success response with data.
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = self::transformData($data);
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $status);
    }

    /**
     * Created response (201).
     */
    public static function created(
        mixed $data = null,
        ?string $message = 'Создано успешно'
    ): JsonResponse {
        return self::success($data, $message, 201);
    }

    /**
     * No content response (204).
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Error response.
     */
    public static function error(
        string $message,
        int $status = 400,
        ?string $code = null,
        array $errors = [],
        array $context = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($code !== null) {
            $response['code'] = $code;
        }

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        if (!empty($context)) {
            $response['context'] = $context;
        }

        return response()->json($response, $status);
    }

    /**
     * Validation error response (422).
     */
    public static function validationError(
        array $errors,
        string $message = 'Ошибка валидации'
    ): JsonResponse {
        return self::error($message, 422, 'validation_error', $errors);
    }

    /**
     * Not found response (404).
     */
    public static function notFound(string $message = 'Не найдено'): JsonResponse
    {
        return self::error($message, 404, 'not_found');
    }

    /**
     * Forbidden response (403).
     */
    public static function forbidden(string $message = 'Доступ запрещён'): JsonResponse
    {
        return self::error($message, 403, 'forbidden');
    }

    /**
     * Unauthorized response (401).
     */
    public static function unauthorized(string $message = 'Не авторизован'): JsonResponse
    {
        return self::error($message, 401, 'unauthorized');
    }

    /**
     * Conflict response (409).
     */
    public static function conflict(
        string $message,
        array $context = []
    ): JsonResponse {
        return self::error($message, 409, 'conflict', [], $context);
    }

    /**
     * Paginated response.
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        ?string $resourceClass = null,
        array $meta = []
    ): JsonResponse {
        $items = $paginator->items();

        // Transform items if resource class provided
        if ($resourceClass !== null) {
            $items = $resourceClass::collection($items);
        }

        $paginationMeta = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];

        return response()->json([
            'success' => true,
            'data' => self::transformData($items),
            'meta' => array_merge($paginationMeta, $meta),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Collection response with optional stats.
     */
    public static function collection(
        iterable $items,
        ?string $resourceClass = null,
        array $meta = []
    ): JsonResponse {
        if ($resourceClass !== null) {
            $items = $resourceClass::collection($items);
        }

        return response()->json([
            'success' => true,
            'data' => self::transformData($items),
            'meta' => array_merge(['count' => count($items)], $meta),
        ]);
    }

    /**
     * Transform data to array if needed.
     */
    private static function transformData(mixed $data): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve();
        }

        if ($data instanceof ResourceCollection) {
            return $data->resolve();
        }

        if (is_object($data) && method_exists($data, 'toArray')) {
            return $data->toArray();
        }

        return $data;
    }
}
