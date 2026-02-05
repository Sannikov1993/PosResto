<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * V1 Modifier Resource
 */
class ModifierResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->api_external_id ?? 'M-' . $this->id,

            // Basic info
            'name' => $this->name,
            'description' => $this->description,

            // Configuration
            'type' => $this->type, // single, multiple
            'is_required' => $this->is_required ?? false,
            'min_selections' => $this->min_selections ?? 0,
            'max_selections' => $this->max_selections,

            // Status
            'is_active' => $this->is_active ?? true,

            // Ordering
            'sort_order' => $this->sort_order,

            // Options
            'options' => $this->when(
                $this->relationLoaded('options'),
                fn() => ModifierOptionResource::collection($this->options)
            ),

            // Timestamps
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
        ];
    }
}
