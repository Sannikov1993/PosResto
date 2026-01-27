<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Фасовка ингредиента
 *
 * Позволяет работать с ингредиентом в разных единицах измерения:
 * - Приёмка в коробках/упаковках
 * - Учёт на складе в базовых единицах (кг, л, шт)
 * - Списание по рецепту в граммах/миллилитрах
 */
class IngredientPackaging extends Model
{
    protected $fillable = [
        'ingredient_id',
        'unit_id',
        'quantity',      // Кол-во базовых единиц в фасовке
        'barcode',
        'is_default',
        'is_purchase',
        'name',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'is_default' => 'boolean',
        'is_purchase' => 'boolean',
    ];

    protected $appends = ['display_name', 'full_name'];

    // ========================
    // Relationships
    // ========================

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    // ========================
    // Accessors
    // ========================

    /**
     * Название для отображения
     * Пример: "Коробка (30 шт)" или "Канистра (5 л)"
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->name) {
            return $this->name;
        }

        $unitName = $this->unit?->short_name ?? '';
        $baseUnit = $this->ingredient?->unit?->short_name ?? '';

        return "{$unitName} ({$this->quantity} {$baseUnit})";
    }

    /**
     * Полное название с ингредиентом
     * Пример: "Яйцо куриное - Коробка (30 шт)"
     */
    public function getFullNameAttribute(): string
    {
        $ingredientName = $this->ingredient?->name ?? '';
        return "{$ingredientName} - {$this->display_name}";
    }

    // ========================
    // Methods
    // ========================

    /**
     * Конвертировать количество фасовок в базовые единицы
     *
     * @param float $packagingQty Количество фасовок
     * @return float Количество в базовых единицах
     */
    public function toBaseUnits(float $packagingQty): float
    {
        return $packagingQty * $this->quantity;
    }

    /**
     * Конвертировать базовые единицы в количество фасовок
     *
     * @param float $baseQty Количество в базовых единицах
     * @return float Количество фасовок
     */
    public function fromBaseUnits(float $baseQty): float
    {
        if ($this->quantity == 0) {
            return 0;
        }
        return $baseQty / $this->quantity;
    }

    /**
     * Установить как фасовку по умолчанию
     */
    public function setAsDefault(): void
    {
        // Убираем флаг у других фасовок этого ингредиента
        self::where('ingredient_id', $this->ingredient_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    // ========================
    // Scopes
    // ========================

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopePurchase($query)
    {
        return $query->where('is_purchase', true);
    }

    public function scopeByBarcode($query, string $barcode)
    {
        return $query->where('barcode', $barcode);
    }

    // ========================
    // Static Methods
    // ========================

    /**
     * Найти фасовку по штрих-коду
     */
    public static function findByBarcode(string $barcode): ?self
    {
        return self::where('barcode', $barcode)->first();
    }

    /**
     * Создать стандартные фасовки для ингредиента
     */
    public static function createDefaultPackagings(Ingredient $ingredient): void
    {
        $baseUnit = $ingredient->unit;
        if (!$baseUnit) return;

        // Определяем стандартные фасовки по типу единицы
        $packagings = [];

        switch ($baseUnit->type) {
            case 'weight':
                // Для весовых: упаковка, коробка
                $packUnit = Unit::where('short_name', 'уп')->first();
                $boxUnit = Unit::where('short_name', 'кор')->first();

                if ($packUnit) {
                    $packagings[] = [
                        'unit_id' => $packUnit->id,
                        'quantity' => 1, // 1 кг в упаковке по умолчанию
                        'is_default' => true,
                        'is_purchase' => true,
                    ];
                }
                break;

            case 'volume':
                // Для объёмных: бутылка, канистра
                $bottleUnit = Unit::where('short_name', 'бут')->first();

                if ($bottleUnit) {
                    $packagings[] = [
                        'unit_id' => $bottleUnit->id,
                        'quantity' => 1, // 1 л в бутылке по умолчанию
                        'is_default' => true,
                        'is_purchase' => true,
                    ];
                }
                break;

            case 'piece':
                // Для штучных: упаковка, коробка
                $packUnit = Unit::where('short_name', 'уп')->first();
                $boxUnit = Unit::where('short_name', 'кор')->first();

                if ($packUnit) {
                    $packagings[] = [
                        'unit_id' => $packUnit->id,
                        'quantity' => 10, // 10 шт в упаковке по умолчанию
                        'is_default' => true,
                        'is_purchase' => true,
                    ];
                }

                if ($boxUnit) {
                    $packagings[] = [
                        'unit_id' => $boxUnit->id,
                        'quantity' => 30, // 30 шт в коробке
                        'is_default' => false,
                        'is_purchase' => true,
                    ];
                }
                break;
        }

        foreach ($packagings as $data) {
            $ingredient->packagings()->create($data);
        }
    }
}
