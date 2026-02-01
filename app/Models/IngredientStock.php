<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToRestaurant;

class IngredientStock extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'warehouse_id',
        'ingredient_id',
        'quantity',
        'reserved',
        'avg_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'reserved' => 'decimal:3',
        'avg_cost' => 'decimal:2',
    ];

    protected $appends = ['available_quantity', 'total_value'];

    // Relationships
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    // Accessors
    public function getAvailableQuantityAttribute(): float
    {
        return max(0, $this->quantity - $this->reserved);
    }

    public function getTotalValueAttribute(): float
    {
        return round($this->quantity * $this->avg_cost, 2);
    }

    // Methods
    public function reserve(float $quantity): bool
    {
        if ($this->available_quantity >= $quantity) {
            $this->increment('reserved', $quantity);
            return true;
        }
        return false;
    }

    public function releaseReservation(float $quantity): void
    {
        $this->decrement('reserved', min($quantity, $this->reserved));
    }
}
