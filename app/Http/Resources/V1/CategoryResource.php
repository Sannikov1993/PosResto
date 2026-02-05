<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * V1 Category Resource
 */
class CategoryResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->api_external_id ?? 'C-' . $this->id,

            // Basic info
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,

            // Media
            'image_url' => $this->imageUrl($this->image),

            // Hierarchy
            'parent_id' => $this->parent_id,
            'depth' => $this->depth ?? 0,

            // Availability
            'is_active' => $this->is_active ?? true,

            // Ordering
            'sort_order' => $this->sort_order,

            // Children (if loaded)
            'children' => $this->when(
                $this->relationLoaded('children') && $this->children->isNotEmpty(),
                fn() => static::collection($this->children)
            ),

            // Dishes count
            'dishes_count' => $this->when(
                isset($this->dishes_count),
                $this->dishes_count
            ),

            // Timestamps
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
        ];
    }
}
