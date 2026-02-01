<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItem extends Model
{
    use HasFactory, BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'price_list_id',
        'dish_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    // ===== RELATIONSHIPS =====

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }
}
