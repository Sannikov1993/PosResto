<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Reservation\Services\DepositService;
use App\Domain\Reservation\StateMachine\ReservationStateMachine;
use App\Domain\Reservation\StateMachine\ReservationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Reservation model.
 *
 * Provides consistent JSON representation across all endpoints.
 * Includes computed fields, relationships, and action availability.
 *
 * Usage:
 *   return new ReservationResource($reservation);
 *   return ReservationResource::collection($reservations);
 */
class ReservationResource extends JsonResource
{
    /**
     * Include available actions in response.
     */
    private bool $includeActions = true;

    /**
     * Include deposit details in response.
     */
    private bool $includeDeposit = true;

    /**
     * Include customer data in response.
     */
    private bool $includeCustomer = true;

    /**
     * Include table data in response.
     */
    private bool $includeTable = true;

    /**
     * Disable actions in response.
     */
    public function withoutActions(): static
    {
        $this->includeActions = false;
        return $this;
    }

    /**
     * Disable deposit details in response.
     */
    public function withoutDeposit(): static
    {
        $this->includeDeposit = false;
        return $this;
    }

    /**
     * Minimal response (no relations, no computed fields).
     */
    public function minimal(): static
    {
        $this->includeActions = false;
        $this->includeDeposit = false;
        $this->includeCustomer = false;
        $this->includeTable = false;
        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            // Core identifiers
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,

            // Status
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),

            // Date & Time
            'date' => $this->formatDate(),
            'time_from' => $this->formatTime($this->time_from),
            'time_to' => $this->formatTime($this->time_to),
            'time_range' => $this->getTimeRange(),
            'duration_minutes' => $this->duration_minutes ?? $this->calculateDuration(),

            // Guest info
            'guests_count' => $this->guests_count,
            'guest_name' => $this->guest_name,
            'guest_phone' => $this->guest_phone,
            'guest_email' => $this->guest_email,

            // Notes
            'notes' => $this->notes,
            'special_requests' => $this->special_requests,

            // Source
            'source' => $this->source,

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        // Table info
        if ($this->includeTable) {
            $data['table_id'] = $this->table_id;
            $data['linked_table_ids'] = $this->linked_table_ids;
            $data['table'] = $this->whenLoaded('table', fn() => new TableResource($this->table));
        }

        // Customer info
        if ($this->includeCustomer) {
            $data['customer_id'] = $this->customer_id;
            $data['customer'] = $this->whenLoaded('customer', fn() => new CustomerResource($this->customer));
        }

        // Deposit info
        if ($this->includeDeposit) {
            $data['deposit'] = $this->getDepositData();
        }

        // Available actions
        if ($this->includeActions) {
            $data['actions'] = $this->getAvailableActions();
        }

        // Status timestamps
        $data['timestamps'] = $this->getStatusTimestamps();

        return $data;
    }

    /**
     * Get status label in Russian.
     */
    private function getStatusLabel(): string
    {
        $status = ReservationStatus::tryFrom($this->status);
        return $status?->label() ?? $this->status;
    }

    /**
     * Get status color for UI.
     */
    private function getStatusColor(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'confirmed' => 'blue',
            'seated' => 'green',
            'completed' => 'gray',
            'cancelled' => 'red',
            'no_show' => 'orange',
            default => 'gray',
        };
    }

    /**
     * Format date for display.
     */
    private function formatDate(): string
    {
        if ($this->date instanceof \Carbon\Carbon) {
            return $this->date->format('Y-m-d');
        }
        return substr((string) $this->date, 0, 10);
    }

    /**
     * Format time for display.
     */
    private function formatTime(?string $time): ?string
    {
        if (!$time) {
            return null;
        }
        return substr($time, 0, 5);
    }

    /**
     * Get formatted time range.
     */
    private function getTimeRange(): string
    {
        $from = $this->formatTime($this->time_from);
        $to = $this->formatTime($this->time_to);
        return "{$from} - {$to}";
    }

    /**
     * Calculate duration in minutes.
     */
    private function calculateDuration(): int
    {
        if (!$this->time_from || !$this->time_to) {
            return 0;
        }

        $from = \Carbon\Carbon::parse($this->time_from);
        $to = \Carbon\Carbon::parse($this->time_to);

        // Handle overnight reservations
        if ($to < $from) {
            $to->addDay();
        }

        return (int) $from->diffInMinutes($to);
    }

    /**
     * Get deposit data.
     */
    private function getDepositData(): array
    {
        $depositService = app(DepositService::class);

        return [
            'amount' => (float) ($this->deposit ?? 0),
            'status' => $this->deposit_status,
            'status_label' => $depositService->getStatusLabel($this->resource),
            'is_required' => $depositService->requiresDeposit($this->resource),
            'is_paid' => $depositService->isPaid($this->resource),
            'can_pay' => $depositService->canCollect($this->resource),
            'can_refund' => $depositService->canRefund($this->resource),
            'can_transfer' => $depositService->canTransfer($this->resource),
            'payment_method' => $this->deposit_payment_method,
            'paid_at' => $this->deposit_paid_at?->toIso8601String(),
            'transferred_to_order_id' => $this->deposit_transferred_to_order_id,
        ];
    }

    /**
     * Get available actions based on current state.
     */
    private function getAvailableActions(): array
    {
        $stateMachine = app(ReservationStateMachine::class);

        return [
            'can_confirm' => $stateMachine->canConfirm($this->resource),
            'can_seat' => $stateMachine->canSeat($this->resource),
            'can_unseat' => $stateMachine->canUnseat($this->resource),
            'can_complete' => $stateMachine->canComplete($this->resource),
            'can_cancel' => $stateMachine->canCancel($this->resource),
            'can_mark_no_show' => $stateMachine->canMarkNoShow($this->resource),
            'is_editable' => $stateMachine->isEditable($this->resource),
            'is_terminal' => $stateMachine->isTerminal($this->resource),
        ];
    }

    /**
     * Get status-related timestamps.
     */
    private function getStatusTimestamps(): array
    {
        return array_filter([
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'seated_at' => $this->seated_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'no_show_at' => $this->no_show_at?->toIso8601String(),
        ], fn($v) => $v !== null);
    }

    /**
     * Add meta information to the response.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => 'v1',
            ],
        ];
    }
}
