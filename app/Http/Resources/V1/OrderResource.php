<?php

namespace App\Http\Resources\V1;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\OrderType;
use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * V1 Order Resource
 *
 * Transforms Order model into public API format
 */
class OrderResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'daily_number' => $this->daily_number,
            'external_id' => $this->external_id,

            // Type and status
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,

            // Source
            'source' => $this->source,

            // Financial
            'amounts' => [
                'subtotal' => $this->formatMoneyDecimal($this->subtotal),
                'subtotal_cents' => $this->formatMoney($this->subtotal),
                'discount_amount' => $this->formatMoneyDecimal($this->discount_amount),
                'discount_amount_cents' => $this->formatMoney($this->discount_amount),
                'discount_percent' => $this->discount_percent ? (float) $this->discount_percent : null,
                'loyalty_discount' => $this->formatMoneyDecimal($this->loyalty_discount_amount),
                'delivery_fee' => $this->formatMoneyDecimal($this->delivery_fee),
                'delivery_fee_cents' => $this->formatMoney($this->delivery_fee),
                'tips' => $this->formatMoneyDecimal($this->tips),
                'tips_cents' => $this->formatMoney($this->tips),
                'total' => $this->formatMoneyDecimal($this->total),
                'total_cents' => $this->formatMoney($this->total),
                'paid_amount' => $this->formatMoneyDecimal($this->paid_amount),
                'paid_amount_cents' => $this->formatMoney($this->paid_amount),
                'change_amount' => $this->formatMoneyDecimal($this->change_amount),
                'bonus_used' => $this->formatMoneyDecimal($this->bonus_used),
            ],

            // Applied discounts
            'discounts' => $this->when(
                $this->applied_discounts,
                fn() => $this->formatAppliedDiscounts()
            ),

            // Customer info
            'customer' => $this->when(
                $this->customer_id,
                fn() => [
                    'id' => $this->customer_id,
                    'name' => $this->relationLoaded('customer') ? $this->customer?->name : null,
                    'phone' => $this->phone ?? ($this->relationLoaded('customer') ? $this->customer?->phone : null),
                ]
            ),

            // Table info (for dine-in)
            'table' => $this->when(
                $this->table_id && $this->type === OrderType::DINE_IN->value,
                fn() => [
                    'id' => $this->table_id,
                    'number' => $this->relationLoaded('table') ? $this->table?->number : null,
                    'name' => $this->relationLoaded('table') ? $this->table?->name : null,
                    'order_number' => $this->table_order_number,
                ]
            ),

            // Delivery info
            'delivery' => $this->when(
                $this->type === OrderType::DELIVERY->value,
                fn() => [
                    'address' => $this->delivery_address,
                    'notes' => $this->delivery_notes,
                    'latitude' => $this->delivery_latitude ? (float) $this->delivery_latitude : null,
                    'longitude' => $this->delivery_longitude ? (float) $this->delivery_longitude : null,
                    'zone_id' => $this->delivery_zone_id,
                    'estimated_minutes' => $this->estimated_delivery_minutes,
                    'status' => $this->delivery_status,
                    'courier_id' => $this->courier_id,
                ]
            ),

            // Schedule
            'scheduling' => [
                'is_asap' => $this->is_asap ?? true,
                'scheduled_at' => $this->formatDateTime($this->scheduled_at),
            ],

            // Guests
            'persons' => $this->persons,
            'comment' => $this->comment,
            'notes' => $this->notes,

            // Order items
            'items' => $this->when(
                $this->relationLoaded('items'),
                fn() => OrderItemResource::collection($this->items)
            ),

            // Promo
            'promo_code' => $this->promo_code,

            // Status timestamps
            'timestamps' => [
                'created_at' => $this->formatDateTime($this->created_at),
                'updated_at' => $this->formatDateTime($this->updated_at),
                'confirmed_at' => $this->formatDateTime($this->confirmed_at),
                'cooking_started_at' => $this->formatDateTime($this->cooking_started_at),
                'cooking_finished_at' => $this->formatDateTime($this->cooking_finished_at),
                'ready_at' => $this->formatDateTime($this->ready_at),
                'picked_up_at' => $this->formatDateTime($this->picked_up_at),
                'delivered_at' => $this->formatDateTime($this->delivered_at),
                'completed_at' => $this->formatDateTime($this->completed_at),
                'cancelled_at' => $this->formatDateTime($this->cancelled_at),
                'paid_at' => $this->formatDateTime($this->paid_at),
            ],

            // Cancellation info
            'cancellation' => $this->when(
                $this->status === OrderStatus::CANCELLED->value,
                fn() => [
                    'reason' => $this->cancel_reason,
                    'cancelled_by_id' => $this->cancelled_by,
                ]
            ),
        ];
    }

    /**
     * Format applied discounts for API response
     */
    protected function formatAppliedDiscounts(): array
    {
        $discounts = $this->applied_discounts ?? [];

        return collect($discounts)->map(function ($discount) {
            return [
                'name' => $discount['name'] ?? null,
                'type' => $discount['type'] ?? null,
                'amount' => $this->formatMoneyDecimal($discount['amount'] ?? 0),
                'amount_cents' => $this->formatMoney($discount['amount'] ?? 0),
                'percent' => $discount['percent'] ?? null,
                'source_type' => $discount['sourceType'] ?? null,
                'source_id' => $discount['sourceId'] ?? null,
                'is_automatic' => $discount['auto'] ?? false,
            ];
        })->values()->all();
    }
}
