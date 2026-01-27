<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'ingredient_id',
        'quantity',
        'cost_price',
        'total',
        'expiry_date',
        'batch_number',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'cost_price' => 'decimal:2',
        'total' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->total = $item->quantity * $item->cost_price;
        });

        static::saved(function ($item) {
            $item->invoice->recalculateTotal();
        });

        static::deleted(function ($item) {
            $item->invoice->recalculateTotal();
        });
    }
}
