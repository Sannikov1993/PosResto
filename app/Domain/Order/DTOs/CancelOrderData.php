<?php

declare(strict_types=1);

namespace App\Domain\Order\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for cancelling an order.
 *
 * Encapsulates all data needed to cancel an order.
 */
final class CancelOrderData extends OrderData
{
    public function __construct(
        public readonly ?string $reason = null,
        public readonly ?int $cancelledBy = null,
        public readonly bool $isWriteOff = false,
    ) {}

    public static function fromRequest(Request $request): static
    {
        return new static(
            reason: $request->input('reason') ?? $request->input('cancel_reason'),
            cancelledBy: $request->input('cancelled_by') ? (int) $request->input('cancelled_by') : auth()->id(),
            isWriteOff: (bool) $request->input('is_write_off', false),
        );
    }

    public static function fromArray(array $data): static
    {
        return new static(
            reason: $data['reason'] ?? $data['cancel_reason'] ?? null,
            cancelledBy: isset($data['cancelled_by']) ? (int) $data['cancelled_by'] : null,
            isWriteOff: (bool) ($data['is_write_off'] ?? false),
        );
    }
}
