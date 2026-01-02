<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class InventoryCheckItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'inventory_check_id',
        'ingredient_id',
        'expected_quantity',
        'actual_quantity',
        'difference',
        'notes',
    ];

    protected $casts = [
        'expected_quantity' => 'decimal:3',
        'actual_quantity' => 'decimal:3',
        'difference' => 'decimal:3',
    ];

    // Relationships
    public function inventoryCheck()
    {
        return $this->belongsTo(InventoryCheck::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    // При установке фактического количества автоматически считаем разницу
    public function setActualQuantityAttribute($value)
    {
        $this->attributes['actual_quantity'] = $value;
        $this->attributes['difference'] = $value - ($this->expected_quantity ?? 0);
    }
}
