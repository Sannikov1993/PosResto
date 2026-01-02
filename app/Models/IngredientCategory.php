<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class IngredientCategory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'restaurant_id',
        'name',
        'icon',
        'sort_order',
    ];

    // Relationships
    public function ingredients()
    {
        return $this->hasMany(Ingredient::class, 'category_id');
    }

    // Scopes
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}