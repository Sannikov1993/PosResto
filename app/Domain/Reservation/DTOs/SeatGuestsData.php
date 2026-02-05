<?php

declare(strict_types=1);

namespace App\Domain\Reservation\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for seating guests action.
 *
 * Encapsulates options for the SeatGuests action.
 *
 * Usage:
 *   $data = SeatGuestsData::fromRequest($request);
 *   $result = $seatGuestsAction->execute($reservation, $data);
 */
final class SeatGuestsData extends ReservationData
{
    public function __construct(
        public readonly bool $createOrder = true,
        public readonly bool $transferDeposit = true,
        public readonly ?int $guestsCount = null,
        public readonly ?int $userId = null,
    ) {}

    /**
     * Create from HTTP request.
     */
    public static function fromRequest(Request $request): static
    {
        return new static(
            createOrder: $request->boolean('create_order', true),
            transferDeposit: $request->boolean('transfer_deposit', true),
            guestsCount: $request->filled('guests_count')
                ? (int) $request->input('guests_count')
                : null,
            userId: auth()->id(),
        );
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): static
    {
        return new static(
            createOrder: $data['create_order'] ?? true,
            transferDeposit: $data['transfer_deposit'] ?? true,
            guestsCount: isset($data['guests_count']) ? (int) $data['guests_count'] : null,
            userId: $data['user_id'] ?? null,
        );
    }

    /**
     * Validation rules.
     */
    public static function rules(): array
    {
        return [
            'create_order' => ['sometimes', 'boolean'],
            'transfer_deposit' => ['sometimes', 'boolean'],
            'guests_count' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * Create default instance (create order, transfer deposit).
     */
    public static function default(?int $userId = null): static
    {
        return new static(
            createOrder: true,
            transferDeposit: true,
            userId: $userId,
        );
    }

    /**
     * Create instance without order creation.
     */
    public static function withoutOrder(?int $userId = null): static
    {
        return new static(
            createOrder: false,
            transferDeposit: false,
            userId: $userId,
        );
    }
}
