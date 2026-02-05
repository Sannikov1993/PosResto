<?php

declare(strict_types=1);

namespace App\Domain\Reservation\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for cancelling a reservation.
 *
 * Usage:
 *   $data = CancelReservationData::fromRequest($request);
 *   $result = $cancelAction->execute($reservation, $data);
 */
final class CancelReservationData extends ReservationData
{
    public function __construct(
        public readonly ?string $reason = null,
        public readonly bool $refundDeposit = true,
        public readonly ?int $userId = null,
    ) {}

    /**
     * Create from HTTP request.
     */
    public static function fromRequest(Request $request): static
    {
        return new static(
            reason: $request->input('reason'),
            refundDeposit: $request->boolean('refund_deposit', true),
            userId: auth()->id(),
        );
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): static
    {
        return new static(
            reason: $data['reason'] ?? null,
            refundDeposit: $data['refund_deposit'] ?? true,
            userId: $data['user_id'] ?? null,
        );
    }

    /**
     * Validation rules.
     */
    public static function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
            'refund_deposit' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Common cancellation reasons.
     */
    public static function commonReasons(): array
    {
        return [
            'customer_request' => 'По просьбе гостя',
            'no_show' => 'Гости не пришли',
            'restaurant_closed' => 'Ресторан закрыт',
            'overbooking' => 'Овербукинг',
            'other' => 'Другая причина',
        ];
    }

    /**
     * Create for customer request.
     */
    public static function customerRequest(?int $userId = null): static
    {
        return new static(
            reason: 'По просьбе гостя',
            refundDeposit: true,
            userId: $userId,
        );
    }

    /**
     * Create for restaurant-initiated cancellation (no refund).
     */
    public static function byRestaurant(string $reason, ?int $userId = null): static
    {
        return new static(
            reason: $reason,
            refundDeposit: false,
            userId: $userId,
        );
    }
}
