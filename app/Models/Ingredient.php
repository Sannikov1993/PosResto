<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $fillable = [
        'restaurant_id',
        'category_id',
        'supplier_id',
        'name',
        'sku',
        'unit_id',
        'quantity',
        'min_quantity',
        'cost_price',
        'expiry_date',
        'notes',
        'is_active',
        'track_stock',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'min_quantity' => 'decimal:3',
        'cost_price' => 'decimal:2',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
        'track_stock' => 'boolean',
    ];

    protected $appends = ['unit_name', 'is_low_stock', 'stock_value', 'status'];

    // Relationships
    public function category()
    {
        return $this->belongsTo(IngredientCategory::class, 'category_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class);
    }

    // Accessors
    public function getUnitNameAttribute()
    {
        return $this->unit?->short_name ?? '';
    }

    public function getIsLowStockAttribute()
    {
        return $this->track_stock && $this->quantity <= $this->min_quantity;
    }

    public function getStockValueAttribute()
    {
        return round($this->quantity * $this->cost_price, 2);
    }

    public function getStatusAttribute()
    {
        if (!$this->track_stock) return 'not_tracked';
        if ($this->quantity <= 0) return 'out_of_stock';
        if ($this->quantity <= $this->min_quantity) return 'low_stock';
        return 'in_stock';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->where('track_stock', true)
            ->whereColumn('quantity', '<=', 'min_quantity');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('track_stock', true)
            ->where('quantity', '<=', 0);
    }

    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // Methods
    public function adjustStock($quantity, $type, $reason = null, $orderId = null, $userId = null)
    {
        $before = $this->quantity;
        $after = $before + $quantity;

        // Создаём запись движения
        StockMovement::create([
            'restaurant_id' => $this->restaurant_id,
            'ingredient_id' => $this->id,
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'cost_price' => $this->cost_price,
            'total_cost' => abs($quantity) * $this->cost_price,
            'order_id' => $orderId,
            'reason' => $reason,
            'user_id' => $userId,
        ]);

        // Обновляем остаток
        $this->update(['quantity' => $after]);

        return $this;
    }

    public function addStock($quantity, $costPrice = null, $supplierId = null, $documentNumber = null, $userId = null)
    {
        $before = $this->quantity;
        $after = $before + $quantity;

        // Обновляем себестоимость если указана новая
        if ($costPrice !== null) {
            $this->update(['cost_price' => $costPrice]);
        }

        StockMovement::create([
            'restaurant_id' => $this->restaurant_id,
            'ingredient_id' => $this->id,
            'type' => 'income',
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'cost_price' => $costPrice ?? $this->cost_price,
            'total_cost' => $quantity * ($costPrice ?? $this->cost_price),
            'supplier_id' => $supplierId,
            'document_number' => $documentNumber,
            'user_id' => $userId,
        ]);

        $this->update(['quantity' => $after]);

        return $this;
    }

    public function removeStock($quantity, $reason = null, $orderId = null, $userId = null)
    {
        return $this->adjustStock(-abs($quantity), 'expense', $reason, $orderId, $userId);
    }

    public function writeOff($quantity, $reason, $userId = null)
    {
        return $this->adjustStock(-abs($quantity), 'write_off', $reason, null, $userId);
    }

    // Статистика
    public static function getStockSummary($restaurantId = 1)
    {
        $ingredients = self::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('track_stock', true)
            ->get();

        return [
            'total_items' => $ingredients->count(),
            'total_value' => $ingredients->sum(fn($i) => $i->quantity * $i->cost_price),
            'low_stock_count' => $ingredients->filter(fn($i) => $i->is_low_stock)->count(),
            'out_of_stock_count' => $ingredients->filter(fn($i) => $i->quantity <= 0)->count(),
        ];
    }
}
