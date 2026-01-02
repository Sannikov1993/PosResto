<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LoyaltyLevel extends Model
{
    protected $fillable = [
        'restaurant_id',
        'name',
        'icon',
        'color',
        'min_total',
        'discount_percent',
        'cashback_percent',
        'bonus_multiplier',
        'birthday_bonus',
        'birthday_discount',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_total' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'cashback_percent' => 'decimal:2',
        'bonus_multiplier' => 'decimal:2',
        'birthday_bonus' => 'boolean',
        'birthday_discount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // Methods
    public static function getLevelForTotal($total, $restaurantId = 1)
    {
        return self::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('min_total', '<=', $total)
            ->orderByDesc('min_total')
            ->first();
    }

    public static function getNextLevel($currentLevel, $restaurantId = 1)
    {
        return self::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('min_total', '>', $currentLevel?->min_total ?? 0)
            ->orderBy('min_total')
            ->first();
    }
}