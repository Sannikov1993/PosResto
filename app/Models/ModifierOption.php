<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ModifierOption extends Model
{
    protected $fillable = [
        'modifier_id',
        'name',
        'price',
        'is_default',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'modifier_option_ingredients')
            ->withPivot(['quantity', 'action'])
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Получить себестоимость ингредиентов для этой опции
    public function getIngredientsCost(): float
    {
        $cost = 0;
        foreach ($this->ingredients as $ingredient) {
            if ($ingredient->pivot->action !== 'remove') {
                $cost += $ingredient->pivot->quantity * ($ingredient->cost_price ?? 0);
            }
        }
        return $cost;
    }
}
