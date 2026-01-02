<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    protected $fillable = [
        'dish_id',
        'output_quantity',
        'instructions',
        'prep_time_minutes',
        'cook_time_minutes',
        'calculated_cost',
    ];

    protected $casts = [
        'output_quantity' => 'decimal:3',
        'calculated_cost' => 'decimal:2',
    ];

    protected $appends = ['total_time', 'cost_per_portion'];

    // Relationships
    public function dish()
    {
        return $this->belongsTo(Dish::class);
    }

    public function items()
    {
        return $this->hasMany(RecipeItem::class);
    }

    // Accessors
    public function getTotalTimeAttribute()
    {
        return ($this->prep_time_minutes ?? 0) + ($this->cook_time_minutes ?? 0);
    }

    public function getCostPerPortionAttribute()
    {
        if ($this->output_quantity <= 0) return 0;
        return round($this->calculated_cost / $this->output_quantity, 2);
    }

    // Methods
    public function calculateCost()
    {
        $totalCost = 0;

        foreach ($this->items as $item) {
            $ingredient = $item->ingredient;
            if ($ingredient) {
                // Учитываем отходы
                $effectiveQty = $item->quantity * (1 + $item->waste_percent / 100);
                $totalCost += $effectiveQty * $ingredient->cost_price;
            }
        }

        $this->update(['calculated_cost' => $totalCost]);
        return $totalCost;
    }

    /**
     * Списать ингредиенты при продаже блюда
     */
    public function deductIngredients($portions = 1, $orderId = null, $userId = null)
    {
        $multiplier = $portions / $this->output_quantity;

        foreach ($this->items as $item) {
            $ingredient = $item->ingredient;
            if ($ingredient && $ingredient->track_stock) {
                $quantity = $item->quantity * $multiplier;
                $ingredient->removeStock($quantity, "Продажа: {$this->dish->name}", $orderId, $userId);
            }
        }
    }

    /**
     * Проверить достаточно ли ингредиентов
     */
    public function checkAvailability($portions = 1)
    {
        $multiplier = $portions / $this->output_quantity;
        $missing = [];

        foreach ($this->items as $item) {
            $ingredient = $item->ingredient;
            if ($ingredient && $ingredient->track_stock) {
                $required = $item->quantity * $multiplier;
                if ($ingredient->quantity < $required) {
                    $missing[] = [
                        'ingredient' => $ingredient->name,
                        'required' => $required,
                        'available' => $ingredient->quantity,
                        'shortage' => $required - $ingredient->quantity,
                        'unit' => $ingredient->unit_name,
                    ];
                }
            }
        }

        return [
            'available' => empty($missing),
            'missing' => $missing,
        ];
    }
}

// ============================================

class RecipeItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'recipe_id',
        'ingredient_id',
        'quantity',
        'waste_percent',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'waste_percent' => 'decimal:2',
    ];

    protected $appends = ['cost', 'effective_quantity'];

    // Relationships
    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    // Accessors
    public function getCostAttribute()
    {
        return round($this->effective_quantity * ($this->ingredient?->cost_price ?? 0), 2);
    }

    public function getEffectiveQuantityAttribute()
    {
        return $this->quantity * (1 + $this->waste_percent / 100);
    }
}
