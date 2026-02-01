<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Исключение: tenant (restaurant_id) не установлен для запроса
 *
 * Возвращает HTTP 403 Forbidden для API-запросов.
 */
class TenantNotSetException extends HttpException
{
    public function __construct(
        string $message = 'Restaurant context required',
        ?\Throwable $previous = null
    ) {
        parent::__construct(403, $message, $previous);
    }

    /**
     * Render exception для API
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => 'TENANT_NOT_SET',
                'message' => $this->getMessage(),
            ], 403);
        }

        return response($this->getMessage(), 403);
    }
}
