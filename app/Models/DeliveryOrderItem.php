<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель позиции заказа на доставку
 */
class DeliveryOrderItem extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id', 'delivery_order_id', 'dish_id', 'product_name',
        'price', 'quantity', 'modifiers', 'comment', 'total',
    ];

    protected $casts = [
        'modifiers' => 'array',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id');
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /**
     * Получить иконку блюда
     */
    public function getIconAttribute(): string
    {
        return $this->dish?->category?->icon ?? '🍽';
    }
}
