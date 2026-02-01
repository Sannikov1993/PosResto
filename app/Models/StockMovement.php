<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToRestaurant;

class StockMovement extends Model
{
    use BelongsToRestaurant;
    protected $fillable = [
        'restaurant_id',
        'warehouse_id',
        'ingredient_id',
        'user_id',
        'type',
        'document_type',
        'document_id',
        'quantity',
        'quantity_before',
        'quantity_after',
        'document_number',
        'cost_price',
        'total_cost',
        'reason',
        'notes',
        'movement_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'cost_price' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'movement_date' => 'datetime',
    ];

    protected $appends = ['type_label', 'type_icon'];

    const TYPE_INCOME = 'income';
    const TYPE_EXPENSE = 'expense';
    const TYPE_WRITE_OFF = 'write_off';
    const TYPE_PRODUCTION = 'production';
    const TYPE_SALE = 'sale';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_TRANSFER_IN = 'transfer_in';
    const TYPE_ADJUSTMENT_PLUS = 'adjustment_plus';
    const TYPE_ADJUSTMENT_MINUS = 'adjustment_minus';

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Accessors
    public function getTypeLabelAttribute(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    public function getTypeIconAttribute(): string
    {
        return [
            'income' => '📥',
            'expense' => '📤',
            'write_off' => '🗑️',
            'production' => '🍳',
            'sale' => '💰',
            'transfer' => '🔄',
            'transfer_in' => '📦',
            'adjustment_plus' => '➕',
            'adjustment_minus' => '➖',
        ][$this->type] ?? '📦';
    }

    public static function getTypes(): array
    {
        return [
            'income' => 'Приход',
            'expense' => 'Расход',
            'write_off' => 'Списание',
            'production' => 'Производство',
            'sale' => 'Продажа',
            'transfer' => 'Перемещение (исход)',
            'transfer_in' => 'Перемещение (приход)',
            'adjustment_plus' => 'Корректировка (+)',
            'adjustment_minus' => 'Корректировка (-)',
        ];
    }

    // Scopes
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForPeriod($query, $from, $to)
    {
        return $query->whereBetween('movement_date', [$from, $to]);
    }

    public function scopeForIngredient($query, int $ingredientId)
    {
        return $query->where('ingredient_id', $ingredientId);
    }

    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeIncome($query)
    {
        return $query->whereIn('type', ['income', 'transfer_in', 'adjustment_plus']);
    }

    public function scopeOutcome($query)
    {
        return $query->whereIn('type', ['expense', 'write_off', 'sale', 'production', 'transfer', 'adjustment_minus']);
    }
}
