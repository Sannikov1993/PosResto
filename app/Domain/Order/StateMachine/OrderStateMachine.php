<?php

declare(strict_types=1);

namespace App\Domain\Order\StateMachine;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Exceptions\InvalidOrderStateTransitionException;
use App\Models\Order;

/**
 * State Machine for Order lifecycle management.
 *
 * Defines and enforces valid status transitions:
 *
 *   ┌─────┐     confirm     ┌───────────┐    cook     ┌─────────┐   ready   ┌───────┐
 *   │ NEW │───────────────▶│ CONFIRMED │──────────▶│ COOKING │────────▶│ READY │
 *   └─────┘                └───────────┘           └─────────┘        └───────┘
 *      │                                                                  │
 *      │ cook (skip confirm)                                    ┌─────────┤
 *      └──────────────────────────────────────────▶│ COOKING │  │         │
 *                                                              │  serve   │ deliver
 *                                                              ▼         ▼
 *                                                         ┌────────┐ ┌────────────┐
 *                                                         │ SERVED │ │ DELIVERING │
 *                                                         └────────┘ └────────────┘
 *                                                              │         │
 *                                                              └────┬────┘
 *                                                                   ▼
 *                                                            ┌───────────┐
 *                                                            │ COMPLETED │
 *                                                            └───────────┘
 *
 *   Any active status ──cancel──▶ CANCELLED
 *
 * Usage:
 *   $sm = new OrderStateMachine();
 *
 *   if ($sm->canConfirm($order)) {
 *       $sm->transitionTo($order, OrderStatus::CONFIRMED);
 *   }
 */
final class OrderStateMachine
{
    /**
     * Allowed transitions: from_status => [to_statuses].
     */
    private const TRANSITIONS = [
        'new' => ['confirmed', 'cooking', 'cancelled'],
        'confirmed' => ['cooking', 'cancelled'],
        'cooking' => ['ready', 'cancelled'],
        'ready' => ['served', 'delivering', 'completed', 'cancelled'],
        'served' => ['completed', 'cancelled'],
        'delivering' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * Check if transition from current status to target is allowed.
     */
    public function canTransitionTo(Order $order, OrderStatus|string $target): bool
    {
        $targetValue = $target instanceof OrderStatus ? $target->value : $target;
        $currentStatus = $order->status;

        $allowedTransitions = self::TRANSITIONS[$currentStatus] ?? [];

        return in_array($targetValue, $allowedTransitions, true);
    }

    /**
     * Get all allowed transitions from current status.
     *
     * @return OrderStatus[]
     */
    public function getAllowedTransitions(Order $order): array
    {
        $currentStatus = $order->status;
        $allowed = self::TRANSITIONS[$currentStatus] ?? [];

        return array_map(
            fn($status) => OrderStatus::from($status),
            $allowed
        );
    }

    /**
     * Get allowed transitions as string array.
     */
    public function getAllowedTransitionValues(Order $order): array
    {
        return self::TRANSITIONS[$order->status] ?? [];
    }

    /**
     * Assert that transition is allowed, throw exception if not.
     *
     * @throws InvalidOrderStateTransitionException
     */
    public function assertCanTransitionTo(Order $order, OrderStatus|string $target): void
    {
        $targetValue = $target instanceof OrderStatus ? $target->value : $target;

        if ($order->status === $targetValue) {
            throw InvalidOrderStateTransitionException::alreadyInState($order, $targetValue);
        }

        if (!$this->canTransitionTo($order, $target)) {
            throw InvalidOrderStateTransitionException::create(
                $order->status,
                $targetValue,
                $this->getAllowedTransitionValues($order),
                $order->id
            );
        }
    }

    /**
     * Transition order to new status (validates first).
     *
     * Note: This only validates and updates the status field.
     * Side effects (table status, notifications, etc.) should be handled by the caller.
     *
     * @throws InvalidOrderStateTransitionException
     */
    public function transitionTo(Order $order, OrderStatus|string $target): Order
    {
        $this->assertCanTransitionTo($order, $target);

        $targetValue = $target instanceof OrderStatus ? $target->value : $target;
        $order->status = $targetValue;

        return $order;
    }

    /**
     * Transition and save order.
     *
     * @throws InvalidOrderStateTransitionException
     */
    public function transitionToAndSave(Order $order, OrderStatus|string $target): Order
    {
        $this->transitionTo($order, $target);
        $order->save();

        return $order;
    }

    // ==================== Convenience Methods ====================

    /**
     * Check if order can be confirmed.
     */
    public function canConfirm(Order $order): bool
    {
        return $order->status === OrderStatus::NEW->value
            && $this->canTransitionTo($order, OrderStatus::CONFIRMED);
    }

    /**
     * Check if order can start cooking.
     */
    public function canStartCooking(Order $order): bool
    {
        return $this->canTransitionTo($order, OrderStatus::COOKING);
    }

    /**
     * Check if order can be marked as ready.
     */
    public function canMarkReady(Order $order): bool
    {
        return $this->canTransitionTo($order, OrderStatus::READY);
    }

    /**
     * Check if order can be marked as served.
     */
    public function canMarkServed(Order $order): bool
    {
        return $this->canTransitionTo($order, OrderStatus::SERVED);
    }

    /**
     * Check if order can start delivering.
     */
    public function canStartDelivering(Order $order): bool
    {
        return $this->canTransitionTo($order, OrderStatus::DELIVERING);
    }

    /**
     * Check if order can be completed.
     */
    public function canComplete(Order $order): bool
    {
        return $this->canTransitionTo($order, OrderStatus::COMPLETED);
    }

    /**
     * Check if order can be cancelled.
     */
    public function canCancel(Order $order): bool
    {
        return $this->canTransitionTo($order, OrderStatus::CANCELLED);
    }

    // ==================== Assert Methods ====================

    /**
     * @throws InvalidOrderStateTransitionException
     */
    public function assertCanConfirm(Order $order): void
    {
        if (!$this->canConfirm($order)) {
            throw InvalidOrderStateTransitionException::cannotConfirm($order);
        }
    }

    /**
     * @throws InvalidOrderStateTransitionException
     */
    public function assertCanStartCooking(Order $order): void
    {
        if (!$this->canStartCooking($order)) {
            throw InvalidOrderStateTransitionException::cannotStartCooking($order);
        }
    }

    /**
     * @throws InvalidOrderStateTransitionException
     */
    public function assertCanMarkReady(Order $order): void
    {
        if (!$this->canMarkReady($order)) {
            throw InvalidOrderStateTransitionException::cannotMarkReady($order);
        }
    }

    /**
     * @throws InvalidOrderStateTransitionException
     */
    public function assertCanMarkServed(Order $order): void
    {
        if (!$this->canMarkServed($order)) {
            throw InvalidOrderStateTransitionException::cannotMarkServed($order);
        }
    }

    /**
     * @throws InvalidOrderStateTransitionException
     */
    public function assertCanStartDelivering(Order $order): void
    {
        if (!$this->canStartDelivering($order)) {
            throw InvalidOrderStateTransitionException::cannotStartDelivering($order);
        }
    }

    /**
     * @throws InvalidOrderStateTransitionException
     */
    public function assertCanComplete(Order $order): void
    {
        if (!$this->canComplete($order)) {
            throw InvalidOrderStateTransitionException::cannotComplete($order);
        }
    }

    /**
     * @throws InvalidOrderStateTransitionException
     */
    public function assertCanCancel(Order $order): void
    {
        if (!$this->canCancel($order)) {
            throw InvalidOrderStateTransitionException::cannotCancel($order);
        }
    }

    // ==================== Status Queries ====================

    /**
     * Check if order is in terminal (final) state.
     */
    public function isTerminal(Order $order): bool
    {
        $status = OrderStatus::tryFrom($order->status);
        return $status?->isTerminal() ?? false;
    }

    /**
     * Check if order is active (not finished).
     */
    public function isActive(Order $order): bool
    {
        $status = OrderStatus::tryFrom($order->status);
        return $status?->isActive() ?? false;
    }

    /**
     * Check if order is visible on kitchen display.
     */
    public function isKitchen(Order $order): bool
    {
        $status = OrderStatus::tryFrom($order->status);
        return $status?->isKitchen() ?? false;
    }

    /**
     * Check if order is editable.
     */
    public function isEditable(Order $order): bool
    {
        $status = OrderStatus::tryFrom($order->status);
        return $status?->isEditable() ?? false;
    }

    /**
     * Get current status as enum.
     */
    public function getCurrentStatus(Order $order): ?OrderStatus
    {
        return OrderStatus::tryFrom($order->status);
    }

    // ==================== Static Helpers ====================

    /**
     * Get all defined statuses.
     *
     * @return OrderStatus[]
     */
    public static function getAllStatuses(): array
    {
        return OrderStatus::cases();
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
        Order State Machine
        ===================

        ┌─────┐    confirm    ┌───────────┐     cook      ┌─────────┐    ready    ┌───────┐
        │ NEW │──────────────▶│ CONFIRMED │─────────────▶│ COOKING │───────────▶│ READY │
        └─────┘               └───────────┘              └─────────┘           └───────┘
           │                                                                       │
           │ cook (skip confirm)                                          ┌────────┤
           └────────────────────────────────────────────▶ COOKING         │        │
                                                                   serve │        │ deliver
                                                                         ▼        ▼
                                                                    ┌────────┐ ┌────────────┐
                                                                    │ SERVED │ │ DELIVERING │
                                                                    └────────┘ └────────────┘
                                                                         │         │
                                                                         └────┬────┘
                                                                              ▼
                                                                       ┌───────────┐
                                                                       │ COMPLETED │
                                                                       └───────────┘

        Any active status ──cancel──▶ CANCELLED

        Terminal states: COMPLETED, CANCELLED
        DIAGRAM;
    }
}
