<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recipe extends Model
{
    protected $fillable = [
        'dish_id',
        'ingredient_id',
        'unit_id',           // Единица измерения в рецепте (может отличаться от базовой)
        'quantity',          // Количество нетто (в unit_id или базовой единице)
        'gross_quantity',    // Количество брутто
        'waste_percent',     // Ручной % потерь (если отличается от ингредиента)
        'processing_type',   // Тип обработки: none, cold, hot, both
        'is_optional',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'gross_quantity' => 'decimal:3',
        'waste_percent' => 'decimal:2',
        'is_optional' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['ingredient_cost', 'unit_name', 'effective_quantity', 'base_quantity'];

    // Relationships
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /**
     * Единица измерения в рецепте (может отличаться от базовой единицы ингредиента)
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    // Accessors
    /**
     * Себестоимость ингредиента в рецепте (на основе base_quantity)
     */
    public function getIngredientCostAttribute(): float
    {
        return round($this->base_quantity * ($this->ingredient->cost_price ?? 0), 2);
    }

    /**
     * Название единицы измерения в рецепте
     */
    public function getUnitNameAttribute(): string
    {
        // Если указана своя единица - используем её
        if ($this->unit_id && $this->unit) {
            return $this->unit->short_name;
        }
        // Иначе - единицу ингредиента
        return $this->ingredient->unit?->short_name ?? '';
    }

    /**
     * Эффективное количество (нетто с учётом потерь)
     */
    public function getEffectiveQuantityAttribute(): float
    {
        if ($this->gross_quantity && $this->gross_quantity > 0) {
            // Если указан % потерь вручную
            if ($this->waste_percent > 0) {
                return $this->gross_quantity * (1 - $this->waste_percent / 100);
            }
            // Если указан тип обработки - используем потери ингредиента
            if ($this->processing_type && $this->processing_type !== 'none' && $this->ingredient) {
                return $this->ingredient->calculateNetWeight($this->gross_quantity, $this->processing_type);
            }
            return $this->gross_quantity;
        }
        return $this->quantity;
    }

    /**
     * Количество в базовых единицах ингредиента (для списания со склада)
     * Конвертирует quantity из unit_id в базовую единицу ингредиента
     */
    public function getBaseQuantityAttribute(): float
    {
        $quantity = $this->quantity;
        $ingredient = $this->ingredient;

        if (!$ingredient) {
            return $quantity;
        }

        // Если указана своя единица измерения - конвертируем
        if ($this->unit_id && $this->unit && $this->unit_id !== $ingredient->unit_id) {
            $quantity = $ingredient->convertToBaseUnit($quantity, $this->unit);
        }

        return round($quantity, 4);
    }

    /**
     * Количество брутто в базовых единицах (для отображения в отчётах)
     */
    public function getBaseGrossQuantityAttribute(): float
    {
        $grossQty = $this->gross_quantity ?? $this->quantity;
        $ingredient = $this->ingredient;

        if (!$ingredient) {
            return $grossQty;
        }

        // Если указана своя единица измерения - конвертируем
        if ($this->unit_id && $this->unit && $this->unit_id !== $ingredient->unit_id) {
            $grossQty = $ingredient->convertToBaseUnit($grossQty, $this->unit);
        }

        return round($grossQty, 4);
    }

    // Рассчитать себестоимость блюда
    public static function calculateDishCost(int $dishId): float
    {
        return self::where('dish_id', $dishId)
            ->with(['ingredient', 'unit'])
            ->get()
            ->sum(fn($r) => $r->base_quantity * ($r->ingredient->cost_price ?? 0));
    }

    // Обновить себестоимость блюда
    public static function updateDishFoodCost(int $dishId): void
    {
        $cost = self::calculateDishCost($dishId);
        Dish::where('id', $dishId)->update(['food_cost' => $cost]);
    }

    /**
     * Списать ингредиенты при продаже блюда
     * Использует base_quantity для корректного списания в базовых единицах
     */
    public static function deductIngredientsForDish(int $dishId, int $warehouseId, int $portions = 1, ?int $orderId = null, ?int $userId = null): void
    {
        $recipes = self::where('dish_id', $dishId)
            ->with(['ingredient', 'unit'])
            ->get();

        foreach ($recipes as $recipe) {
            $ingredient = $recipe->ingredient;
            if ($ingredient && $ingredient->track_stock && !$recipe->is_optional) {
                // Используем base_quantity - уже сконвертировано в базовые единицы
                $quantity = $recipe->base_quantity * $portions;
                $ingredient->removeStock($warehouseId, $quantity, $userId, "Продажа: Заказ #{$orderId}");
            }
        }
    }

    /**
     * Проверить достаточно ли ингредиентов
     * Использует base_quantity для корректной проверки в базовых единицах
     */
    public static function checkAvailability(int $dishId, int $warehouseId, int $portions = 1): array
    {
        $recipes = self::where('dish_id', $dishId)
            ->with(['ingredient.stocks', 'unit'])
            ->get();

        $missing = [];

        foreach ($recipes as $recipe) {
            $ingredient = $recipe->ingredient;
            if ($ingredient && $ingredient->track_stock && !$recipe->is_optional) {
                // Используем base_quantity - уже сконвертировано в базовые единицы
                $required = $recipe->base_quantity * $portions;
                $available = $ingredient->getStockInWarehouse($warehouseId);

                if ($available < $required) {
                    $missing[] = [
                        'ingredient_id' => $ingredient->id,
                        'ingredient' => $ingredient->name,
                        'required' => $required,
                        'available' => $available,
                        'shortage' => $required - $available,
                        'unit' => $ingredient->unit_name, // Базовая единица
                        'recipe_unit' => $recipe->unit_name, // Единица в рецепте
                        'recipe_quantity' => $recipe->quantity * $portions, // В единицах рецепта
                    ];
                }
            }
        }

        return [
            'available' => empty($missing),
            'missing' => $missing,
        ];
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($recipe) {
            self::updateDishFoodCost($recipe->dish_id);
        });

        static::deleted(function ($recipe) {
            self::updateDishFoodCost($recipe->dish_id);
        });
    }
}
