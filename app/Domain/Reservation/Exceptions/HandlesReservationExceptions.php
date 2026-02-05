<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Trait for handling reservation exceptions in controllers.
 *
 * Provides consistent error response formatting and logging.
 *
 * Usage in controller:
 *   use HandlesReservationExceptions;
 *
 *   public function store(Request $request) {
 *       return $this->handleReservationAction(function () use ($request) {
 *           // Your logic here
 *           return $reservation;
 *       });
 *   }
 */
trait HandlesReservationExceptions
{
    /**
     * Execute an action and handle reservation exceptions.
     *
     * @param callable $action Action to execute
     * @param string|null $context Additional context for logging
     * @return JsonResponse
     */
    protected function handleReservationAction(callable $action, ?string $context = null): JsonResponse
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
            return $this->handleValidationException($e, $context);

        } catch (ReservationException $e) {
            return $this->handleReservationException($e, $context);

        } catch (Throwable $e) {
            return $this->handleUnexpectedException($e, $context);
        }
    }

    /**
     * Handle a validation exception.
     */
    protected function handleValidationException(
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
     * Handle a known reservation exception.
     */
    protected function handleReservationException(
        ReservationException $e,
        ?string $context = null
    ): JsonResponse {
        // Log warning for business logic errors
        Log::warning('Reservation error', [
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
    protected function handleUnexpectedException(
        Throwable $e,
        ?string $context = null
    ): JsonResponse {
        // Log error for unexpected exceptions
        Log::error('Unexpected reservation error', [
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'action_context' => $context,
        ]);

        // Don't expose internal errors to users
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
    protected function successResponse(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    /**
     * Create success response with reservation data.
     */
    protected function reservationResponse(
        mixed $reservation,
        ?string $message = null,
        int $status = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'reservation' => $reservation,
        ];

        if ($message) {
            $response['message'] = $message;
        }

        return response()->json($response, $status);
    }

    /**
     * Create error response with custom message.
     */
    protected function errorResponse(
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
