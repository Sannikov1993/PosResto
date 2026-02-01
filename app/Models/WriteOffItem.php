<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WriteOffItem extends Model
{
    use HasFactory, BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'write_off_id',
        'item_type',
        'dish_id',
        'ingredient_id',
        'name',
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * Типы позиций
     */
    public const ITEM_TYPES = [
        'dish' => 'Блюдо',
        'ingredient' => 'Ингредиент',
        'manual' => 'Ручной ввод',
    ];

    // ==================== RELATIONSHIPS ====================

    public function writeOff(): BelongsTo
    {
        return $this->belongsTo(WriteOff::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    // ==================== ACCESSORS ====================

    /**
     * Название типа позиции
     */
    public function getItemTypeNameAttribute(): string
    {
        return self::ITEM_TYPES[$this->item_type] ?? 'Позиция';
    }
}
