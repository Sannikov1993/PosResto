<?php

declare(strict_types=1);

namespace App\Domain\Reservation\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for deposit payment operations.
 *
 * Usage:
 *   $data = DepositPaymentData::fromRequest($request);
 *   $depositService->markAsPaid($reservation, $data);
 */
final class DepositPaymentData extends ReservationData
{
    public const METHOD_CASH = 'cash';
    public const METHOD_CARD = 'card';
    public const METHOD_ONLINE = 'online';
    public const METHOD_TRANSFER = 'transfer';

    public const METHODS = [
        self::METHOD_CASH,
        self::METHOD_CARD,
        self::METHOD_ONLINE,
        self::METHOD_TRANSFER,
    ];

    public function __construct(
        public readonly ?string $paymentMethod = null,
        public readonly ?string $transactionId = null,
        public readonly ?float $amount = null,
        public readonly ?int $userId = null,
    ) {}

    /**
     * Create from HTTP request.
     */
    public static function fromRequest(Request $request): static
    {
        return new static(
            paymentMethod: $request->input('payment_method'),
            transactionId: $request->input('transaction_id'),
            amount: $request->filled('amount') ? (float) $request->input('amount') : null,
            userId: auth()->id(),
        );
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): static
    {
        return new static(
            paymentMethod: $data['payment_method'] ?? null,
            transactionId: $data['transaction_id'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            userId: $data['user_id'] ?? null,
        );
    }

    /**
     * Validation rules.
     */
    public static function rules(): array
    {
        return [
            'payment_method' => ['sometimes', 'string', 'in:' . implode(',', self::METHODS)],
            'transaction_id' => ['nullable', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get payment method label.
     */
    public function getMethodLabel(): string
    {
        return match ($this->paymentMethod) {
            self::METHOD_CASH => 'Наличные',
            self::METHOD_CARD => 'Карта',
            self::METHOD_ONLINE => 'Онлайн',
            self::METHOD_TRANSFER => 'Перевод',
            default => 'Не указан',
        };
    }

    /**
     * Create for cash payment.
     */
    public static function cash(?int $userId = null): static
    {
        return new static(
            paymentMethod: self::METHOD_CASH,
            userId: $userId,
        );
    }

    /**
     * Create for card payment.
     */
    public static function card(?string $transactionId = null, ?int $userId = null): static
    {
        return new static(
            paymentMethod: self::METHOD_CARD,
            transactionId: $transactionId,
            userId: $userId,
        );
    }

    /**
     * Create for online payment.
     */
    public static function online(string $transactionId, ?int $userId = null): static
    {
        return new static(
            paymentMethod: self::METHOD_ONLINE,
            transactionId: $transactionId,
            userId: $userId,
        );
    }
}
