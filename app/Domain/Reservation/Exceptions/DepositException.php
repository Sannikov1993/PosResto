<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Exceptions;

use App\Models\Reservation;

/**
 * Exception for deposit-related operations.
 *
 * Covers payment, refund, and transfer errors for reservation deposits.
 */
final class DepositException extends ReservationException
{
    protected string $errorCode = 'deposit_error';
    protected int $httpStatus = 422;

    /**
     * Deposit amount involved.
     */
    public readonly ?float $amount;

    /**
     * Current deposit status.
     */
    public readonly ?string $depositStatus;

    /**
     * Reservation ID.
     */
    public readonly ?int $reservationId;

    private function __construct(
        string $message,
        ?float $amount = null,
        ?string $depositStatus = null,
        ?int $reservationId = null
    ) {
        parent::__construct($message);

        $this->amount = $amount;
        $this->depositStatus = $depositStatus;
        $this->reservationId = $reservationId;

        $this->context = array_filter([
            'amount' => $amount,
            'deposit_status' => $depositStatus,
            'reservation_id' => $reservationId,
        ], fn($v) => $v !== null);
    }

    /**
     * Cannot pay deposit - already paid.
     */
    public static function alreadyPaid(Reservation $reservation): self
    {
        $message = sprintf(
            'Депозит для брони #%d уже оплачен.',
            $reservation->id
        );

        return new self(
            $message,
            (float) (float) $reservation->deposit,
            $reservation->deposit_status,
            $reservation->id
        );
    }

    /**
     * Cannot pay deposit - no deposit required.
     */
    public static function noDepositRequired(Reservation $reservation): self
    {
        $message = sprintf(
            'Для брони #%d депозит не требуется.',
            $reservation->id
        );

        return new self(
            $message,
            0,
            $reservation->deposit_status,
            $reservation->id
        );
    }

    /**
     * Cannot pay deposit - reservation cancelled.
     */
    public static function reservationCancelled(Reservation $reservation): self
    {
        $message = sprintf(
            'Невозможно принять депозит: бронь #%d отменена.',
            $reservation->id
        );

        return new self(
            $message,
            (float) $reservation->deposit,
            $reservation->deposit_status,
            $reservation->id
        );
    }

    /**
     * Cannot refund deposit - not paid.
     */
    public static function notPaidForRefund(Reservation $reservation): self
    {
        $message = sprintf(
            'Невозможно вернуть депозит для брони #%d: депозит не был оплачен.',
            $reservation->id
        );

        return new self(
            $message,
            (float) $reservation->deposit,
            $reservation->deposit_status,
            $reservation->id
        );
    }

    /**
     * Cannot refund deposit - already transferred to order.
     */
    public static function alreadyTransferred(Reservation $reservation, ?int $orderId = null): self
    {
        $message = $orderId
            ? sprintf(
                'Невозможно вернуть депозит для брони #%d: депозит уже перенесён в заказ #%d.',
                $reservation->id,
                $orderId
            )
            : sprintf(
                'Невозможно вернуть депозит для брони #%d: депозит уже перенесён в заказ.',
                $reservation->id
            );

        $instance = new self(
            $message,
            (float) $reservation->deposit,
            'transferred',
            $reservation->id
        );

        if ($orderId) {
            $instance->context['transferred_to_order_id'] = $orderId;
        }

        return $instance;
    }

    /**
     * Cannot refund deposit - already refunded.
     */
    public static function alreadyRefunded(Reservation $reservation): self
    {
        $message = sprintf(
            'Депозит для брони #%d уже был возвращён.',
            $reservation->id
        );

        return new self(
            $message,
            (float) $reservation->deposit,
            'refunded',
            $reservation->id
        );
    }

    /**
     * Cannot transfer deposit - not paid.
     */
    public static function notPaidForTransfer(Reservation $reservation): self
    {
        $message = sprintf(
            'Невозможно перенести депозит в заказ: депозит для брони #%d не оплачен.',
            $reservation->id
        );

        return new self(
            $message,
            (float) $reservation->deposit,
            $reservation->deposit_status,
            $reservation->id
        );
    }

    /**
     * Cannot transfer deposit - no order.
     */
    public static function noOrderForTransfer(Reservation $reservation): self
    {
        $message = sprintf(
            'Невозможно перенести депозит: для брони #%d не создан заказ.',
            $reservation->id
        );

        return new self(
            $message,
            (float) $reservation->deposit,
            $reservation->deposit_status,
            $reservation->id
        );
    }

    /**
     * Payment processing failed.
     */
    public static function paymentFailed(
        Reservation $reservation,
        string $reason,
        ?\Throwable $previous = null
    ): self {
        $message = sprintf(
            'Ошибка при оплате депозита для брони #%d: %s',
            $reservation->id,
            $reason
        );

        $instance = new self(
            $message,
            (float) $reservation->deposit,
            $reservation->deposit_status,
            $reservation->id
        );

        $instance->context['reason'] = $reason;

        return $instance;
    }

    /**
     * Invalid deposit amount.
     */
    public static function invalidAmount(float $amount, ?float $expected = null): self
    {
        $message = $expected !== null
            ? sprintf('Неверная сумма депозита: %.2f ₽. Ожидалось: %.2f ₽.', $amount, $expected)
            : sprintf('Неверная сумма депозита: %.2f ₽.', $amount);

        $instance = new self($message, $amount);
        $instance->context['expected_amount'] = $expected;

        return $instance;
    }

    /**
     * Invalid payment method.
     */
    public static function invalidPaymentMethod(string $method): self
    {
        $allowed = ['cash', 'card', 'online'];

        $message = sprintf(
            "Неверный метод оплаты депозита: '%s'. Доступные методы: %s.",
            $method,
            implode(', ', $allowed)
        );

        $instance = new self($message);
        $instance->context['payment_method'] = $method;
        $instance->context['allowed_methods'] = $allowed;

        return $instance;
    }

    /**
     * Generic invalid operation.
     */
    public static function invalidOperation(
        Reservation $reservation,
        string $operation,
        ?string $reason = null
    ): self {
        $message = $reason
            ?? sprintf(
                'Операция "%s" недоступна для депозита брони #%d.',
                $operation,
                $reservation->id
            );

        $instance = new self(
            $message,
            (float) $reservation->deposit,
            $reservation->deposit_status,
            $reservation->id
        );

        $instance->context['operation'] = $operation;

        return $instance;
    }

    /**
     * Order does not belong to reservation.
     */
    public static function orderMismatch(Reservation $reservation, \App\Models\Order $order): self
    {
        $message = sprintf(
            'Заказ #%d не принадлежит брони #%d. Невозможно перенести депозит.',
            $order->id,
            $reservation->id
        );

        $instance = new self(
            $message,
            (float) $reservation->deposit,
            $reservation->deposit_status,
            $reservation->id
        );

        $instance->context['order_id'] = $order->id;
        $instance->context['order_reservation_id'] = $order->reservation_id;

        return $instance;
    }
}
