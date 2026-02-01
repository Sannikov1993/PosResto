<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\BelongsToTenant;
use App\Traits\BelongsToRestaurant;

class Ingredient extends Model
{
    use SoftDeletes, BelongsToTenant, BelongsToRestaurant;

    protected $fillable = [
        'tenant_id',
        'restaurant_id',
        'category_id',
        'unit_id',
        'name',
        'sku',
        'barcode',
        'description',
        'cost_price',
        'min_stock',
        'max_stock',
        'shelf_life_days',
        'storage_conditions',
        'image',
        'is_semi_finished',
        'track_stock',
        'is_active',
        // Новые поля для конвертации и учёта потерь
        'piece_weight',      // Вес 1 штуки в кг (для штучных товаров)
        'density',           // Плотность кг/л (для конвертации объём ↔ вес)
        'cold_loss_percent', // Потери при холодной обработке (очистка)
        'hot_loss_percent',  // Потери при горячей обработке (жарка, варка)
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'min_stock' => 'decimal:3',
        'max_stock' => 'decimal:3',
        'shelf_life_days' => 'integer',
        'is_semi_finished' => 'boolean',
        'track_stock' => 'boolean',
        'is_active' => 'boolean',
        'piece_weight' => 'decimal:4',
        'density' => 'decimal:4',
        'cold_loss_percent' => 'decimal:2',
        'hot_loss_percent' => 'decimal:2',
    ];

    protected $appends = ['unit_name', 'total_stock', 'is_low_stock', 'stock_value', 'status'];

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(IngredientStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function packagings(): HasMany
    {
        return $this->hasMany(IngredientPackaging::class);
    }

    public function defaultPackaging()
    {
        return $this->hasOne(IngredientPackaging::class)->where('is_default', true);
    }

    public function dishes(): BelongsToMany
    {
        return $this->belongsToMany(Dish::class, 'recipes')
            ->withPivot('quantity', 'gross_quantity', 'waste_percent', 'is_optional', 'notes')
            ->withTimestamps();
    }

    // Accessors
    public function getUnitNameAttribute(): string
    {
        return $this->unit?->short_name ?? '';
    }

    public function getTotalStockAttribute(): float
    {
        return $this->stocks()->sum('quantity');
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->track_stock && $this->total_stock <= $this->min_stock;
    }

    public function getStockValueAttribute(): float
    {
        return round($this->total_stock * $this->cost_price, 2);
    }

    public function getStatusAttribute(): string
    {
        if (!$this->track_stock) return 'not_tracked';
        $stock = $this->total_stock;
        if ($stock <= 0) return 'out_of_stock';
        if ($stock <= $this->min_stock) return 'low_stock';
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
            ->whereHas('stocks', function ($q) {
                $q->havingRaw('SUM(quantity) <= ingredients.min_stock');
            });
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('track_stock', true)
            ->whereDoesntHave('stocks', function ($q) {
                $q->where('quantity', '>', 0);
            });
    }

    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeSemiFinished($query)
    {
        return $query->where('is_semi_finished', true);
    }

    // Methods
    public function getStockInWarehouse(int $warehouseId): float
    {
        return $this->stocks()->where('warehouse_id', $warehouseId)->value('quantity') ?? 0;
    }

    public function adjustStock(int $warehouseId, float $quantity, string $type, ?int $userId = null, ?string $reason = null, ?int $documentId = null, ?string $documentType = null): StockMovement
    {
        $stock = IngredientStock::firstOrCreate(
            ['warehouse_id' => $warehouseId, 'ingredient_id' => $this->id],
            ['quantity' => 0, 'avg_cost' => $this->cost_price]
        );

        $before = $stock->quantity;
        $after = $before + $quantity;

        // Обновляем остаток
        $stock->update(['quantity' => max(0, $after)]);

        // Создаём движение
        return StockMovement::create([
            'restaurant_id' => $this->restaurant_id,
            'warehouse_id' => $warehouseId,
            'ingredient_id' => $this->id,
            'user_id' => $userId ?? auth()->id(),
            'type' => $type,
            'document_type' => $documentType,
            'document_id' => $documentId,
            'quantity' => $quantity,
            'cost_price' => $this->cost_price,
            'total_cost' => abs($quantity) * $this->cost_price,
            'reason' => $reason,
            'movement_date' => now(),
        ]);
    }

    public function addStock(int $warehouseId, float $quantity, ?float $costPrice = null, ?int $userId = null): StockMovement
    {
        if ($costPrice !== null) {
            $this->update(['cost_price' => $costPrice]);
        }
        return $this->adjustStock($warehouseId, abs($quantity), 'income', $userId);
    }

    public function removeStock(int $warehouseId, float $quantity, ?int $userId = null, ?string $reason = null): StockMovement
    {
        return $this->adjustStock($warehouseId, -abs($quantity), 'expense', $userId, $reason);
    }

    public function writeOff(int $warehouseId, float $quantity, string $reason, ?int $userId = null): StockMovement
    {
        return $this->adjustStock($warehouseId, -abs($quantity), 'write_off', $userId, $reason);
    }

    // ========================
    // Конвертация единиц измерения
    // ========================

    /**
     * Конвертировать количество в базовые единицы ингредиента
     *
     * @param float $quantity Исходное количество
     * @param Unit $fromUnit Исходная единица измерения
     * @return float Количество в базовых единицах
     */
    public function convertToBaseUnit(float $quantity, Unit $fromUnit): float
    {
        $baseUnit = $this->unit;
        if (!$baseUnit) {
            return $quantity;
        }

        // Если единицы одинаковые - возвращаем как есть
        if ($fromUnit->id === $baseUnit->id) {
            return $quantity;
        }

        // Если типы единиц одинаковые - используем base_ratio
        if ($fromUnit->type === $baseUnit->type) {
            return $quantity * $fromUnit->base_ratio / $baseUnit->base_ratio;
        }

        // Конвертация между типами (вес ↔ объём через плотность)
        if ($this->density && $this->density > 0) {
            // volume → weight: л * плотность = кг
            if ($fromUnit->type === 'volume' && $baseUnit->type === 'weight') {
                $liters = $quantity * $fromUnit->base_ratio; // в литры
                $kg = $liters * $this->density;
                return $kg / $baseUnit->base_ratio;
            }
            // weight → volume: кг / плотность = л
            if ($fromUnit->type === 'weight' && $baseUnit->type === 'volume') {
                $kg = $quantity * $fromUnit->base_ratio; // в кг
                $liters = $kg / $this->density;
                return $liters / $baseUnit->base_ratio;
            }
        }

        // Конвертация штук в вес через piece_weight
        if ($this->piece_weight && $this->piece_weight > 0) {
            // piece → weight: шт * вес_штуки = кг
            if ($fromUnit->type === 'piece' && $baseUnit->type === 'weight') {
                $pieces = $quantity * $fromUnit->base_ratio;
                $kg = $pieces * $this->piece_weight;
                return $kg / $baseUnit->base_ratio;
            }
            // weight → piece: кг / вес_штуки = шт
            if ($fromUnit->type === 'weight' && $baseUnit->type === 'piece') {
                $kg = $quantity * $fromUnit->base_ratio;
                $pieces = $kg / $this->piece_weight;
                return $pieces / $baseUnit->base_ratio;
            }
        }

        // Не удалось конвертировать - возвращаем как есть
        return $quantity;
    }

    /**
     * Конвертировать из базовых единиц в указанную
     *
     * @param float $quantity Количество в базовых единицах
     * @param Unit $toUnit Целевая единица измерения
     * @return float Количество в целевых единицах
     */
    public function convertFromBaseUnit(float $quantity, Unit $toUnit): float
    {
        $baseUnit = $this->unit;
        if (!$baseUnit) {
            return $quantity;
        }

        if ($toUnit->id === $baseUnit->id) {
            return $quantity;
        }

        // Если типы единиц одинаковые
        if ($toUnit->type === $baseUnit->type) {
            return $quantity * $baseUnit->base_ratio / $toUnit->base_ratio;
        }

        // Конвертация между типами (вес ↔ объём)
        if ($this->density && $this->density > 0) {
            if ($baseUnit->type === 'weight' && $toUnit->type === 'volume') {
                $kg = $quantity * $baseUnit->base_ratio;
                $liters = $kg / $this->density;
                return $liters / $toUnit->base_ratio;
            }
            if ($baseUnit->type === 'volume' && $toUnit->type === 'weight') {
                $liters = $quantity * $baseUnit->base_ratio;
                $kg = $liters * $this->density;
                return $kg / $toUnit->base_ratio;
            }
        }

        // Конвертация вес ↔ штуки
        if ($this->piece_weight && $this->piece_weight > 0) {
            if ($baseUnit->type === 'weight' && $toUnit->type === 'piece') {
                $kg = $quantity * $baseUnit->base_ratio;
                $pieces = $kg / $this->piece_weight;
                return $pieces / $toUnit->base_ratio;
            }
            if ($baseUnit->type === 'piece' && $toUnit->type === 'weight') {
                $pieces = $quantity * $baseUnit->base_ratio;
                $kg = $pieces * $this->piece_weight;
                return $kg / $toUnit->base_ratio;
            }
        }

        return $quantity;
    }

    /**
     * Рассчитать нетто из брутто с учётом потерь при обработке
     *
     * @param float $grossWeight Вес брутто
     * @param string $processingType Тип обработки: none, cold, hot, both
     * @return float Вес нетто
     */
    public function calculateNetWeight(float $grossWeight, string $processingType = 'none'): float
    {
        $netWeight = $grossWeight;

        // Потери при холодной обработке (очистка, разделка)
        if (in_array($processingType, ['cold', 'both']) && $this->cold_loss_percent > 0) {
            $netWeight = $netWeight * (1 - $this->cold_loss_percent / 100);
        }

        // Потери при горячей обработке (жарка, варка)
        if (in_array($processingType, ['hot', 'both']) && $this->hot_loss_percent > 0) {
            $netWeight = $netWeight * (1 - $this->hot_loss_percent / 100);
        }

        return round($netWeight, 4);
    }

    /**
     * Рассчитать брутто из нетто (обратная операция)
     *
     * @param float $netWeight Вес нетто
     * @param string $processingType Тип обработки
     * @return float Вес брутто
     */
    public function calculateGrossWeight(float $netWeight, string $processingType = 'none'): float
    {
        $grossWeight = $netWeight;

        // Обратный расчёт: если после обработки осталось X%, значит было X% / (1 - потери)
        if (in_array($processingType, ['hot', 'both']) && $this->hot_loss_percent > 0) {
            $grossWeight = $grossWeight / (1 - $this->hot_loss_percent / 100);
        }

        if (in_array($processingType, ['cold', 'both']) && $this->cold_loss_percent > 0) {
            $grossWeight = $grossWeight / (1 - $this->cold_loss_percent / 100);
        }

        return round($grossWeight, 4);
    }

    /**
     * Общий процент потерь при обработке
     */
    public function getTotalLossPercentAttribute(): float
    {
        $remaining = 1;

        if ($this->cold_loss_percent > 0) {
            $remaining *= (1 - $this->cold_loss_percent / 100);
        }
        if ($this->hot_loss_percent > 0) {
            $remaining *= (1 - $this->hot_loss_percent / 100);
        }

        return round((1 - $remaining) * 100, 2);
    }

    // Статистика
    public static function getStockSummary(int $restaurantId, ?int $warehouseId = null): array
    {
        $query = self::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('track_stock', true)
            ->with('stocks');

        $ingredients = $query->get();

        $totalValue = 0;
        $lowStockCount = 0;
        $outOfStockCount = 0;

        foreach ($ingredients as $ingredient) {
            $stock = $warehouseId
                ? $ingredient->stocks->where('warehouse_id', $warehouseId)->sum('quantity')
                : $ingredient->stocks->sum('quantity');

            $totalValue += $stock * $ingredient->cost_price;

            if ($stock <= 0) {
                $outOfStockCount++;
            } elseif ($stock <= $ingredient->min_stock) {
                $lowStockCount++;
            }
        }

        return [
            'total_items' => $ingredients->count(),
            'total_value' => round($totalValue, 2),
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
        ];
    }
}
