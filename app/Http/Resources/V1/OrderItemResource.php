<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * V1 OrderItem Resource
 */
class OrderItemResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Dish info
            'dish_id' => $this->dish_id,
            'dish' => $this->when(
                $this->relationLoaded('dish') && $this->dish,
                fn() => [
                    'id' => $this->dish->id,
                    'external_id' => $this->dish->api_external_id,
                    'name' => $this->dish->name,
                    'sku' => $this->dish->sku,
                ]
            ),

            // Item details
            'name' => $this->name,
            'quantity' => $this->quantity,

            // Pricing
            'price' => $this->formatMoneyDecimal($this->price),
            'price_cents' => $this->formatMoney($this->price),
            'modifiers_price' => $this->formatMoneyDecimal($this->modifiers_price),
            'modifiers_price_cents' => $this->formatMoney($this->modifiers_price),
            'total' => $this->formatMoneyDecimal($this->total),
            'total_cents' => $this->formatMoney($this->total),

            // Modifiers
            'modifiers' => $this->when(
                $this->modifiers,
                fn() => $this->formatModifiers()
            ),

            // Status
            'status' => $this->status,
            'comment' => $this->comment,

            // Kitchen info
            'kitchen_station_id' => $this->kitchen_station_id,
            'cooking_time_minutes' => $this->cooking_time,

            // Timestamps
            'created_at' => $this->formatDateTime($this->created_at),
            'sent_to_kitchen_at' => $this->formatDateTime($this->sent_to_kitchen_at),
            'started_at' => $this->formatDateTime($this->started_at),
            'completed_at' => $this->formatDateTime($this->completed_at),
        ];
    }

    /**
     * Format modifiers for API response
     */
    protected function formatModifiers(): array
    {
        $modifiers = $this->modifiers ?? [];

        if (is_string($modifiers)) {
            $modifiers = json_decode($modifiers, true) ?? [];
        }

        return collect($modifiers)->map(function ($modifier) {
            return [
                'id' => $modifier['id'] ?? null,
                'modifier_id' => $modifier['modifier_id'] ?? null,
                'name' => $modifier['name'] ?? null,
                'option' => $modifier['option'] ?? null,
                'price' => $this->formatMoneyDecimal($modifier['price'] ?? 0),
                'price_cents' => $this->formatMoney($modifier['price'] ?? 0),
                'quantity' => $modifier['quantity'] ?? 1,
            ];
        })->values()->all();
    }
}
