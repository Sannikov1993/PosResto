<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

use App\Models\Order;

/**
 * Exception thrown when attempting an invalid order state transition.
 *
 * Used by the OrderStateMachine to enforce valid status changes.
 * Provides clear information about current state and allowed transitions.
 */
final class InvalidOrderStateTransitionException extends OrderException
{
    protected string $errorCode = 'invalid_state_transition';
    protected int $httpStatus = 422;

    /**
     * Current order status.
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
     * Order ID (if available).
     */
    public readonly ?int $orderId;

    private function __construct(
        string $message,
        string $currentState,
        string $targetState,
        array $allowedTransitions,
        ?int $orderId = null
    ) {
        parent::__construct($message);

        $this->currentState = $currentState;
        $this->targetState = $targetState;
        $this->allowedTransitions = $allowedTransitions;
        $this->orderId = $orderId;

        $this->context = [
            'current_state' => $currentState,
            'target_state' => $targetState,
            'allowed_transitions' => $allowedTransitions,
            'order_id' => $orderId,
        ];
    }

    /**
     * Create exception for generic state transition failure.
     */
    public static function create(
        string $from,
        string $to,
        array $allowed,
        ?int $orderId = null
    ): self {
        $message = sprintf(
            "Невозможно изменить статус заказа с '%s' на '%s'. Доступные переходы: %s.",
            self::translateStatus($from),
            self::translateStatus($to),
            self::translateStatuses($allowed)
        );

        return new self($message, $from, $to, $allowed, $orderId);
    }

    /**
     * Create exception when trying to confirm an order.
     */
    public static function cannotConfirm(Order $order): self
    {
        $allowed = ['new'];

        $message = sprintf(
            "Невозможно подтвердить заказ #%d: текущий статус '%s'. Подтвердить можно только новые заказы.",
            $order->id,
            self::translateStatus($order->status)
        );

        return new self($message, $order->status, 'confirmed', $allowed, $order->id);
    }

    /**
     * Create exception when trying to start cooking.
     */
    public static function cannotStartCooking(Order $order): self
    {
        $allowed = ['new', 'confirmed'];

        $message = sprintf(
            "Невозможно начать готовку заказа #%d: текущий статус '%s'. Готовка возможна только для новых или подтверждённых заказов.",
            $order->id,
            self::translateStatus($order->status)
        );

        return new self($message, $order->status, 'cooking', $allowed, $order->id);
    }

    /**
     * Create exception when trying to mark order as ready.
     */
    public static function cannotMarkReady(Order $order): self
    {
        $allowed = ['cooking'];

        $message = sprintf(
            "Невозможно отметить заказ #%d как готовый: текущий статус '%s'. Отметить готовым можно только готовящийся заказ.",
            $order->id,
            self::translateStatus($order->status)
        );

        return new self($message, $order->status, 'ready', $allowed, $order->id);
    }

    /**
     * Create exception when trying to mark order as served.
     */
    public static function cannotMarkServed(Order $order): self
    {
        $allowed = ['ready'];

        $message = sprintf(
            "Невозможно отметить заказ #%d как поданный: текущий статус '%s'. Подать можно только готовый заказ.",
            $order->id,
            self::translateStatus($order->status)
        );

        return new self($message, $order->status, 'served', $allowed, $order->id);
    }

    /**
     * Create exception when trying to start delivering.
     */
    public static function cannotStartDelivering(Order $order): self
    {
        $allowed = ['ready'];

        $message = sprintf(
            "Невозможно начать доставку заказа #%d: текущий статус '%s'. Доставка возможна только для готовых заказов.",
            $order->id,
            self::translateStatus($order->status)
        );

        return new self($message, $order->status, 'delivering', $allowed, $order->id);
    }

    /**
     * Create exception when trying to complete an order.
     */
    public static function cannotComplete(Order $order): self
    {
        $allowed = ['ready', 'served', 'delivering'];

        $message = sprintf(
            "Невозможно завершить заказ #%d: текущий статус '%s'. Завершить можно только готовый, поданный или доставляемый заказ.",
            $order->id,
            self::translateStatus($order->status)
        );

        return new self($message, $order->status, 'completed', $allowed, $order->id);
    }

    /**
     * Create exception when trying to cancel an order.
     */
    public static function cannotCancel(Order $order): self
    {
        $allowed = ['new', 'confirmed', 'cooking', 'ready', 'served', 'delivering'];

        $message = sprintf(
            "Невозможно отменить заказ #%d: текущий статус '%s'. Отмена невозможна для завершённых или уже отменённых заказов.",
            $order->id,
            self::translateStatus($order->status)
        );

        return new self($message, $order->status, 'cancelled', $allowed, $order->id);
    }

    /**
     * Create exception when order is already in target state.
     */
    public static function alreadyInState(Order $order, string $targetState): self
    {
        $message = sprintf(
            "Заказ #%d уже имеет статус '%s'.",
            $order->id,
            self::translateStatus($targetState)
        );

        return new self($message, $order->status, $targetState, [], $order->id);
    }

    /**
     * Translate status to Russian.
     */
    private static function translateStatus(string $status): string
    {
        return match ($status) {
            'new' => 'новый',
            'confirmed' => 'подтверждён',
            'cooking' => 'готовится',
            'ready' => 'готов',
            'served' => 'подан',
            'delivering' => 'доставляется',
            'completed' => 'завершён',
            'cancelled' => 'отменён',
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
