<?php

declare(strict_types=1);

namespace App\Domain\Reservation\StateMachine;

use App\Domain\Reservation\Exceptions\InvalidStateTransitionException;
use App\Models\Reservation;

/**
 * State Machine for Reservation lifecycle management.
 *
 * Defines and enforces valid status transitions:
 *
 *   ┌─────────┐     ┌───────────┐     ┌────────┐     ┌───────────┐
 *   │ PENDING │────▶│ CONFIRMED │────▶│ SEATED │────▶│ COMPLETED │
 *   └─────────┘     └───────────┘     └────────┘     └───────────┘
 *        │               │                │
 *        │ seat          │                │
 *        └───────────────┼────────────────┘
 *        │               │                │
 *        ▼               ▼                ▼
 *   ┌───────────┐   ┌───────────┐   ┌───────────┐
 *   │ CANCELLED │   │ CANCELLED │   │ CONFIRMED │ (unseat)
 *   └───────────┘   └───────────┘   └───────────┘
 *                        │
 *                        ▼
 *                   ┌─────────┐
 *                   │ NO_SHOW │
 *                   └─────────┘
 *
 * Usage:
 *   $stateMachine = new ReservationStateMachine();
 *
 *   if ($stateMachine->canSeat($reservation)) {
 *       $stateMachine->transitionTo($reservation, ReservationStatus::SEATED);
 *   }
 */
final class ReservationStateMachine
{
    /**
     * Allowed transitions: from_status => [to_statuses].
     */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled', 'seated'], // seated = guests arrived without confirmation
        'confirmed' => ['seated', 'cancelled', 'no_show'],
        'seated' => ['completed', 'confirmed'], // confirmed = unseat
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    /**
     * Check if transition from current status to target is allowed.
     */
    public function canTransitionTo(Reservation $reservation, ReservationStatus|string $target): bool
    {
        $targetValue = $target instanceof ReservationStatus ? $target->value : $target;
        $currentStatus = $reservation->status;

        $allowedTransitions = self::TRANSITIONS[$currentStatus] ?? [];

        return in_array($targetValue, $allowedTransitions, true);
    }

    /**
     * Get all allowed transitions from current status.
     *
     * @return ReservationStatus[]
     */
    public function getAllowedTransitions(Reservation $reservation): array
    {
        $currentStatus = $reservation->status;
        $allowed = self::TRANSITIONS[$currentStatus] ?? [];

        return array_map(
            fn($status) => ReservationStatus::from($status),
            $allowed
        );
    }

    /**
     * Get allowed transitions as string array.
     */
    public function getAllowedTransitionValues(Reservation $reservation): array
    {
        return self::TRANSITIONS[$reservation->status] ?? [];
    }

    /**
     * Assert that transition is allowed, throw exception if not.
     *
     * @throws InvalidStateTransitionException
     */
    public function assertCanTransitionTo(Reservation $reservation, ReservationStatus|string $target): void
    {
        $targetValue = $target instanceof ReservationStatus ? $target->value : $target;

        if ($reservation->status === $targetValue) {
            throw InvalidStateTransitionException::alreadyInState($reservation, $targetValue);
        }

        if (!$this->canTransitionTo($reservation, $target)) {
            throw InvalidStateTransitionException::create(
                $reservation->status,
                $targetValue,
                $this->getAllowedTransitionValues($reservation),
                $reservation->id
            );
        }
    }

    /**
     * Transition reservation to new status (validates first).
     *
     * Note: This only validates and updates the status field.
     * Side effects (table status, orders, etc.) should be handled by Actions.
     *
     * @throws InvalidStateTransitionException
     */
    public function transitionTo(Reservation $reservation, ReservationStatus|string $target): Reservation
    {
        $this->assertCanTransitionTo($reservation, $target);

        $targetValue = $target instanceof ReservationStatus ? $target->value : $target;
        $reservation->status = $targetValue;

        return $reservation;
    }

    /**
     * Transition and save reservation.
     *
     * @throws InvalidStateTransitionException
     */
    public function transitionToAndSave(Reservation $reservation, ReservationStatus|string $target): Reservation
    {
        $this->transitionTo($reservation, $target);
        $reservation->save();

        return $reservation;
    }

    // ==================== Convenience Methods ====================

    /**
     * Check if reservation can be confirmed (only from pending).
     * Note: Transition from seated to confirmed is "unseat", not "confirm".
     */
    public function canConfirm(Reservation $reservation): bool
    {
        // Only pending reservations can be confirmed
        return $reservation->status === ReservationStatus::PENDING->value
            && $this->canTransitionTo($reservation, ReservationStatus::CONFIRMED);
    }

    /**
     * Check if reservation can be seated (guests arrive).
     */
    public function canSeat(Reservation $reservation): bool
    {
        return $this->canTransitionTo($reservation, ReservationStatus::SEATED);
    }

    /**
     * Check if reservation can be unseated (return to confirmed).
     */
    public function canUnseat(Reservation $reservation): bool
    {
        return $reservation->status === ReservationStatus::SEATED->value
            && $this->canTransitionTo($reservation, ReservationStatus::CONFIRMED);
    }

    /**
     * Check if reservation can be completed.
     */
    public function canComplete(Reservation $reservation): bool
    {
        return $this->canTransitionTo($reservation, ReservationStatus::COMPLETED);
    }

    /**
     * Check if reservation can be cancelled.
     */
    public function canCancel(Reservation $reservation): bool
    {
        return $this->canTransitionTo($reservation, ReservationStatus::CANCELLED);
    }

    /**
     * Check if reservation can be marked as no-show.
     */
    public function canMarkNoShow(Reservation $reservation): bool
    {
        return $this->canTransitionTo($reservation, ReservationStatus::NO_SHOW);
    }

    // ==================== Assert Methods ====================

    /**
     * Assert reservation can be confirmed.
     *
     * @throws InvalidStateTransitionException
     */
    public function assertCanConfirm(Reservation $reservation): void
    {
        if (!$this->canConfirm($reservation)) {
            throw InvalidStateTransitionException::cannotConfirm($reservation);
        }
    }

    /**
     * Assert reservation can be seated.
     *
     * @throws InvalidStateTransitionException
     */
    public function assertCanSeat(Reservation $reservation): void
    {
        if (!$this->canSeat($reservation)) {
            throw InvalidStateTransitionException::cannotSeat($reservation);
        }
    }

    /**
     * Assert reservation can be unseated.
     *
     * @throws InvalidStateTransitionException
     */
    public function assertCanUnseat(Reservation $reservation): void
    {
        if (!$this->canUnseat($reservation)) {
            throw InvalidStateTransitionException::cannotUnseat($reservation);
        }
    }

    /**
     * Assert reservation can be completed.
     *
     * @throws InvalidStateTransitionException
     */
    public function assertCanComplete(Reservation $reservation): void
    {
        if (!$this->canComplete($reservation)) {
            throw InvalidStateTransitionException::cannotComplete($reservation);
        }
    }

    /**
     * Assert reservation can be cancelled.
     *
     * @throws InvalidStateTransitionException
     */
    public function assertCanCancel(Reservation $reservation): void
    {
        if (!$this->canCancel($reservation)) {
            throw InvalidStateTransitionException::cannotCancel($reservation);
        }
    }

    /**
     * Assert reservation can be marked as no-show.
     *
     * @throws InvalidStateTransitionException
     */
    public function assertCanMarkNoShow(Reservation $reservation): void
    {
        if (!$this->canMarkNoShow($reservation)) {
            throw InvalidStateTransitionException::cannotMarkNoShow($reservation);
        }
    }

    // ==================== Status Queries ====================

    /**
     * Check if reservation is in terminal (final) state.
     */
    public function isTerminal(Reservation $reservation): bool
    {
        $status = ReservationStatus::tryFrom($reservation->status);
        return $status?->isTerminal() ?? false;
    }

    /**
     * Check if reservation is active (not finished).
     */
    public function isActive(Reservation $reservation): bool
    {
        $status = ReservationStatus::tryFrom($reservation->status);
        return $status?->isActive() ?? false;
    }

    /**
     * Check if guests are currently seated.
     */
    public function isSeated(Reservation $reservation): bool
    {
        return $reservation->status === ReservationStatus::SEATED->value;
    }

    /**
     * Check if reservation is editable.
     */
    public function isEditable(Reservation $reservation): bool
    {
        $status = ReservationStatus::tryFrom($reservation->status);
        return $status?->isEditable() ?? false;
    }

    /**
     * Get current status as enum.
     */
    public function getCurrentStatus(Reservation $reservation): ?ReservationStatus
    {
        return ReservationStatus::tryFrom($reservation->status);
    }

    // ==================== Static Helpers ====================

    /**
     * Get all defined statuses.
     *
     * @return ReservationStatus[]
     */
    public static function getAllStatuses(): array
    {
        return ReservationStatus::cases();
    }

    /**
     * Get transitions map for documentation/debugging.
     */
    public static function getTransitionsMap(): array
    {
        return self::TRANSITIONS;
    }

    /**
     * Visualize the state machine as ASCII diagram.
     */
    public static function visualize(): string
    {
        return <<<'DIAGRAM'
        Reservation State Machine
        ═════════════════════════

        ┌──────────┐     confirm      ┌───────────┐      seat       ┌────────┐    complete    ┌───────────┐
        │ PENDING  │─────────────────▶│ CONFIRMED │────────────────▶│ SEATED │───────────────▶│ COMPLETED │
        └──────────┘                  └───────────┘                 └────────┘               └───────────┘
             │                              │                            │
             │ cancel                       │ cancel                     │ unseat
             ▼                              ▼                            ▼
        ┌───────────┐                 ┌───────────┐                ┌───────────┐
        │ CANCELLED │                 │ CANCELLED │                │ CONFIRMED │
        └───────────┘                 └───────────┘                └───────────┘
                                           │
                                           │ no_show
                                           ▼
                                      ┌─────────┐
                                      │ NO_SHOW │
                                      └─────────┘

        Terminal states: COMPLETED, CANCELLED, NO_SHOW
        DIAGRAM;
    }
}
