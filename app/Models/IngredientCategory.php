<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenant;

class IngredientCategory extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'restaurant_id',
        'name',
        'icon',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $appends = ['ingredients_count'];

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class, 'category_id');
    }

    // Accessors
    public function getIngredientsCountAttribute(): int
    {
        return $this->ingredients()->count();
    }

    // Scopes
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Default categories
    public static function getDefaultCategories(): array
    {
        return [
            ['name' => 'Мясо и птица', 'icon' => '🥩', 'color' => '#dc2626'],
            ['name' => 'Рыба и морепродукты', 'icon' => '🐟', 'color' => '#0891b2'],
            ['name' => 'Овощи', 'icon' => '🥬', 'color' => '#16a34a'],
            ['name' => 'Фрукты', 'icon' => '🍎', 'color' => '#ea580c'],
            ['name' => 'Молочные продукты', 'icon' => '🧀', 'color' => '#eab308'],
            ['name' => 'Бакалея', 'icon' => '🌾', 'color' => '#a16207'],
            ['name' => 'Специи и приправы', 'icon' => '🌶️', 'color' => '#b91c1c'],
            ['name' => 'Напитки', 'icon' => '🍷', 'color' => '#7c3aed'],
            ['name' => 'Замороженные продукты', 'icon' => '❄️', 'color' => '#0ea5e9'],
            ['name' => 'Полуфабрикаты', 'icon' => '🍱', 'color' => '#6366f1'],
            ['name' => 'Хозтовары', 'icon' => '🧹', 'color' => '#6b7280'],
        ];
    }
}
