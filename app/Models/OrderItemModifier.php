<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemModifier extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'order_item_id',
        'modifier_option_id',
        'quantity',
        'price',
        'name',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function modifierOption(): BelongsTo
    {
        return $this->belongsTo(ModifierOption::class);
    }

    // Общая стоимость (цена × количество)
    public function getTotalAttribute(): float
    {
        return $this->price * $this->quantity;
    }
}
