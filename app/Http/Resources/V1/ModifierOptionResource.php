<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * V1 ModifierOption Resource
 */
class ModifierOptionResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'modifier_id' => $this->modifier_id,

            // Basic info
            'name' => $this->name,

            // Pricing
            'price' => $this->formatMoneyDecimal($this->price),
            'price_cents' => $this->formatMoney($this->price),

            // Status
            'is_default' => $this->is_default ?? false,
            'is_available' => $this->is_available ?? true,

            // Ordering
            'sort_order' => $this->sort_order,
        ];
    }
}
