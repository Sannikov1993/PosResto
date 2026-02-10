<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for all order-related errors.
 *
 * Provides structured error information for API responses
 * and logging with consistent format.
 */
abstract class OrderException extends Exception
{
    /**
     * Error code for API responses (e.g., 'invalid_state', 'payment_error').
     */
    protected string $errorCode = 'order_error';

    /**
     * HTTP status code for API responses.
     */
    protected int $httpStatus = 422;

    /**
     * Additional context data for debugging/logging.
     */
    protected array $context = [];

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get error code for API responses.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get HTTP status code.
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * Get additional context data.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Convert exception to array for JSON response.
     */
    public function toArray(): array
    {
        return [
            'error' => $this->errorCode,
            'message' => $this->getMessage(),
            ...$this->context,
        ];
    }

    /**
     * Create JSON response from exception.
     */
    public function toResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->toArray(), $this->httpStatus);
    }
}
