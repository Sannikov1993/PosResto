<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Trait for handling order exceptions in controllers.
 *
 * Provides consistent error response formatting and logging.
 *
 * Usage in controller:
 *   use HandlesOrderExceptions;
 *
 *   public function store(Request $request) {
 *       return $this->handleOrderAction(function () use ($request) {
 *           // Your logic here
 *           return $order;
 *       });
 *   }
 */
trait HandlesOrderExceptions
{
    /**
     * Execute an action and handle order exceptions.
     *
     * @param callable $action Action to execute
     * @param string|null $context Additional context for logging
     * @return JsonResponse
     */
    protected function handleOrderAction(callable $action, ?string $context = null): JsonResponse
    {
        try {
            $result = $action();

            // If result is already a JsonResponse, return it
            if ($result instanceof JsonResponse) {
                return $result;
            }

            // Default success response
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (ValidationException $e) {
            return $this->handleOrderValidationException($e, $context);

        } catch (OrderException $e) {
            return $this->handleOrderException($e, $context);

        } catch (Throwable $e) {
            return $this->handleUnexpectedOrderException($e, $context);
        }
    }

    /**
     * Handle a validation exception.
     */
    protected function handleOrderValidationException(
        ValidationException $e,
        ?string $context = null
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'error' => 'validation_error',
            'message' => $e->getMessage(),
            'errors' => $e->errors(),
        ], 422);
    }

    /**
     * Handle a known order exception.
     */
    protected function handleOrderException(
        OrderException $e,
        ?string $context = null
    ): JsonResponse {
        Log::warning('Order error', [
            'error_code' => $e->getErrorCode(),
            'message' => $e->getMessage(),
            'context' => $e->getContext(),
            'action_context' => $context,
        ]);

        return response()->json([
            'success' => false,
            ...$e->toArray(),
        ], $e->getHttpStatus());
    }

    /**
     * Handle unexpected exceptions.
     */
    protected function handleUnexpectedOrderException(
        Throwable $e,
        ?string $context = null
    ): JsonResponse {
        Log::error('Unexpected order error', [
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'action_context' => $context,
        ]);

        $isDebug = config('app.debug', false);

        return response()->json([
            'success' => false,
            'error' => 'internal_error',
            'message' => $isDebug
                ? $e->getMessage()
                : 'Произошла внутренняя ошибка. Пожалуйста, попробуйте позже.',
        ], 500);
    }

    /**
     * Wrap result in success response.
     */
    protected function orderSuccessResponse(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    /**
     * Create success response with order data.
     */
    protected function orderResponse(
        mixed $order,
        ?string $message = null,
        int $status = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'order' => $order,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        return response()->json($response, $status);
    }

    /**
     * Create error response with custom message.
     */
    protected function orderErrorResponse(
        string $message,
        string $errorCode = 'error',
        int $status = 422,
        array $context = []
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'error' => $errorCode,
            'message' => $message,
            ...$context,
        ], $status);
    }
}
