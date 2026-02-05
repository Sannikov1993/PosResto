<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Exceptions;

use App\Models\Reservation;

/**
 * Exception thrown when attempting an invalid state transition.
 *
 * Used by the ReservationStateMachine to enforce valid status changes.
 * Provides clear information about current state and allowed transitions.
 */
final class InvalidStateTransitionException extends ReservationException
{
    protected string $errorCode = 'invalid_state_transition';
    protected int $httpStatus = 422;

    /**
     * Current reservation status.
     */
    public readonly string $currentState;

    /**
     * Attempted target status.
     */
    public readonly string $targetState;

    /**
     * List of allowed transitions from current state.
     */
    public readonly array $allowedTransitions;

    /**
     * Reservation ID (if available).
     */
    public readonly ?int $reservationId;

    private function __construct(
        string $message,
        string $currentState,
        string $targetState,
        array $allowedTransitions,
        ?int $reservationId = null
    ) {
        parent::__construct($message);

        $this->currentState = $currentState;
        $this->targetState = $targetState;
        $this->allowedTransitions = $allowedTransitions;
        $this->reservationId = $reservationId;

        $this->context = [
            'current_state' => $currentState,
            'target_state' => $targetState,
            'allowed_transitions' => $allowedTransitions,
            'reservation_id' => $reservationId,
        ];
    }

    /**
     * Create exception for generic state transition failure.
     */
    public static function create(
        string $from,
        string $to,
        array $allowed,
        ?int $reservationId = null
    ): self {
        $allowedStr = empty($allowed) ? 'нет доступных переходов' : implode(', ', $allowed);

        $message = sprintf(
            "Невозможно изменить статус с '%s' на '%s'. Доступные переходы: %s.",
            self::translateStatus($from),
            self::translateStatus($to),
            self::translateStatuses($allowed)
        );

        return new self($message, $from, $to, $allowed, $reservationId);
    }

    /**
     * Create exception when trying to seat a reservation.
     */
    public static function cannotSeat(Reservation $reservation): self
    {
        $allowed = ['confirmed']; // Only confirmed can be seated

        $message = sprintf(
            "Невозможно посадить гостей: бронь #%d имеет статус '%s'. Посадка возможна только для подтверждённых броней.",
            $reservation->id,
            self::translateStatus($reservation->status)
        );

        return new self($message, $reservation->status, 'seated', $allowed, $reservation->id);
    }

    /**
     * Create exception when trying to unseat a reservation.
     */
    public static function cannotUnseat(Reservation $reservation): self
    {
        $allowed = ['seated']; // Only seated can be unseated

        $message = sprintf(
            "Невозможно снять гостей со стола: бронь #%d имеет статус '%s'. Снятие возможно только для посаженных гостей.",
            $reservation->id,
            self::translateStatus($reservation->status)
        );

        return new self($message, $reservation->status, 'confirmed', $allowed, $reservation->id);
    }

    /**
     * Create exception when trying to complete a reservation.
     */
    public static function cannotComplete(Reservation $reservation): self
    {
        $allowed = ['seated']; // Only seated can be completed

        $message = sprintf(
            "Невозможно завершить бронь #%d: текущий статус '%s'. Завершить можно только посаженных гостей.",
            $reservation->id,
            self::translateStatus($reservation->status)
        );

        return new self($message, $reservation->status, 'completed', $allowed, $reservation->id);
    }

    /**
     * Create exception when trying to cancel a reservation.
     */
    public static function cannotCancel(Reservation $reservation): self
    {
        $allowed = ['pending', 'confirmed'];

        $message = sprintf(
            "Невозможно отменить бронь #%d: текущий статус '%s'. Отмена возможна только для ожидающих или подтверждённых броней.",
            $reservation->id,
            self::translateStatus($reservation->status)
        );

        return new self($message, $reservation->status, 'cancelled', $allowed, $reservation->id);
    }

    /**
     * Create exception when trying to confirm a reservation.
     */
    public static function cannotConfirm(Reservation $reservation): self
    {
        $allowed = ['pending'];

        $message = sprintf(
            "Невозможно подтвердить бронь #%d: текущий статус '%s'. Подтвердить можно только ожидающие брони.",
            $reservation->id,
            self::translateStatus($reservation->status)
        );

        return new self($message, $reservation->status, 'confirmed', $allowed, $reservation->id);
    }

    /**
     * Create exception when trying to mark no-show.
     */
    public static function cannotMarkNoShow(Reservation $reservation): self
    {
        $allowed = ['confirmed'];

        $message = sprintf(
            "Невозможно отметить неявку для брони #%d: текущий статус '%s'. Неявку можно отметить только для подтверждённых броней.",
            $reservation->id,
            self::translateStatus($reservation->status)
        );

        return new self($message, $reservation->status, 'no_show', $allowed, $reservation->id);
    }

    /**
     * Create exception when reservation is already in target state.
     */
    public static function alreadyInState(Reservation $reservation, string $targetState): self
    {
        $message = sprintf(
            "Бронь #%d уже имеет статус '%s'.",
            $reservation->id,
            self::translateStatus($targetState)
        );

        return new self($message, $reservation->status, $targetState, [], $reservation->id);
    }

    /**
     * Translate status to Russian.
     */
    private static function translateStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'ожидает подтверждения',
            'confirmed' => 'подтверждено',
            'seated' => 'гости за столом',
            'completed' => 'завершено',
            'cancelled' => 'отменено',
            'no_show' => 'неявка',
            default => $status,
        };
    }

    /**
     * Translate array of statuses to Russian.
     */
    private static function translateStatuses(array $statuses): string
    {
        if (empty($statuses)) {
            return 'нет доступных переходов';
        }

        return implode(', ', array_map([self::class, 'translateStatus'], $statuses));
    }
}
