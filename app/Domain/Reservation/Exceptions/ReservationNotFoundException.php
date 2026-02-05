<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Exceptions;

/**
 * Exception thrown when a reservation is not found.
 */
final class ReservationNotFoundException extends ReservationException
{
    protected string $errorCode = 'reservation_not_found';
    protected int $httpStatus = 404;

    /**
     * Reservation ID that was not found.
     */
    public readonly int $reservationId;

    private function __construct(string $message, int $reservationId)
    {
        parent::__construct($message);

        $this->reservationId = $reservationId;
        $this->context = [
            'reservation_id' => $reservationId,
        ];
    }

    /**
     * Create exception for missing reservation.
     */
    public static function withId(int $id): self
    {
        return new self(
            sprintf('Бронирование #%d не найдено.', $id),
            $id
        );
    }

    /**
     * Create exception for missing reservation with additional context.
     */
    public static function forRestaurant(int $id, int $restaurantId): self
    {
        $instance = new self(
            sprintf('Бронирование #%d не найдено в данном ресторане.', $id),
            $id
        );

        $instance->context['restaurant_id'] = $restaurantId;

        return $instance;
    }
}
