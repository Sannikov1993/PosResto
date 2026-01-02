<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'restaurant_id',
        'ingredient_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'cost_price',
        'total_cost',
        'supplier_id',
        'order_id',
        'document_number',
        'reason',
        'user_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'quantity_before' => 'decimal:3',
        'quantity_after' => 'decimal:3',
        'cost_price' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    protected $appends = ['type_label', 'type_icon'];

    const TYPE_INCOME = 'income';
    const TYPE_EXPENSE = 'expense';
    const TYPE_WRITE_OFF = 'write_off';
    const TYPE_INVENTORY = 'inventory';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_RETURN = 'return';

    // Relationships
    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Accessors
    public function getTypeLabelAttribute()
    {
        return [
            'income' => 'Приход',
            'expense' => 'Расход',
            'write_off' => 'Списание',
            'inventory' => 'Инвентаризация',
            'transfer' => 'Перемещение',
            'return' => 'Возврат',
        ][$this->type] ?? $this->type;
    }

    public function getTypeIconAttribute()
    {
        return [
            'income' => '📥',
            'expense' => '📤',
            'write_off' => '🗑️',
            'inventory' => '📋',
            'transfer' => '🔄',
            'return' => '↩️',
        ][$this->type] ?? '📦';
    }

    // Scopes
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForPeriod($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    public function scopeForIngredient($query, $ingredientId)
    {
        return $query->where('ingredient_id', $ingredientId);
    }
}