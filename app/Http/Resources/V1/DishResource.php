<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * V1 Dish Resource
 *
 * Transforms Dish model into public API format
 */
class DishResource extends ApiResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->api_external_id,
            'sku' => $this->sku,

            // Type info
            'product_type' => $this->product_type,
            'parent_id' => $this->when($this->isVariant(), $this->parent_id),

            // Basic info
            'name' => $this->name,
            'variant_name' => $this->when($this->variant_name, $this->variant_name),
            'full_name' => $this->getFullName(),
            'slug' => $this->slug,
            'description' => $this->description,

            // Pricing
            'price' => $this->formatMoneyDecimal($this->price),
            'price_cents' => $this->formatMoney($this->price),
            'old_price' => $this->when($this->old_price, fn() => $this->formatMoneyDecimal($this->old_price)),
            'old_price_cents' => $this->when($this->old_price, fn() => $this->formatMoney($this->old_price)),
            'is_on_sale' => $this->isOnSale(),
            'discount_percent' => $this->when($this->isOnSale(), fn() => $this->getDiscountPercent()),

            // Media
            'image_url' => $this->imageUrl($this->image),

            // Nutrition info
            'nutrition' => [
                'weight' => $this->weight,
                'weight_unit' => 'g',
                'calories' => $this->calories,
                'proteins' => $this->proteins ? (float) $this->proteins : null,
                'fats' => $this->fats ? (float) $this->fats : null,
                'carbs' => $this->carbs ? (float) $this->carbs : null,
            ],

            // Cooking
            'cooking_time_minutes' => $this->cooking_time,

            // Flags
            'flags' => [
                'is_available' => $this->is_available,
                'is_popular' => $this->is_popular,
                'is_new' => $this->is_new,
                'is_spicy' => $this->is_spicy,
                'is_vegetarian' => $this->is_vegetarian,
                'is_vegan' => $this->is_vegan,
                'is_in_stop_list' => $this->isInStopList(),
            ],

            // Category
            'category' => $this->includeRelation('category', CategoryResource::class),

            // Variants (for parent products)
            'variants' => $this->when(
                $this->isParent() && $this->relationLoaded('variants'),
                fn() => static::collection($this->variants)
            ),

            // Modifiers
            'modifiers' => $this->when(
                $this->relationLoaded('modifiers'),
                fn() => ModifierResource::collection($this->modifiers)
            ),

            // Timestamps
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
        ];
    }
}
