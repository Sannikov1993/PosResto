<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCheckItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'inventory_check_id',
        'ingredient_id',
        'expected_quantity',
        'actual_quantity',
        'difference',
        'cost_price',
        'notes',
    ];

    protected $casts = [
        'expected_quantity' => 'decimal:3',
        'actual_quantity' => 'decimal:3',
        'difference' => 'decimal:3',
        'cost_price' => 'decimal:2',
    ];

    protected $appends = ['difference_cost', 'status'];

    // Relationships
    public function inventoryCheck(): BelongsTo
    {
        return $this->belongsTo(InventoryCheck::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    // Accessors
    public function getDifferenceCostAttribute(): float
    {
        return ($this->difference ?? 0) * ($this->cost_price ?? 0);
    }

    public function getStatusAttribute(): string
    {
        if ($this->actual_quantity === null) {
            return 'pending';
        }
        if ($this->difference == 0) {
            return 'match';
        }
        return $this->difference > 0 ? 'surplus' : 'shortage';
    }

    // При установке фактического количества автоматически считаем разницу
    public function setActualQuantityAttribute($value)
    {
        $this->attributes['actual_quantity'] = $value;
        if ($value !== null) {
            $this->attributes['difference'] = $value - ($this->expected_quantity ?? 0);
        }
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->whereNull('actual_quantity');
    }

    public function scopeWithDiscrepancy($query)
    {
        return $query->whereNotNull('actual_quantity')
            ->whereColumn('actual_quantity', '!=', 'expected_quantity');
    }
}
