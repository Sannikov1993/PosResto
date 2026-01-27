<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientPackaging;
use App\Models\Unit;

/**
 * Сервис конвертации единиц измерения
 *
 * Обеспечивает:
 * - Конвертацию между единицами одного типа (кг ↔ г, л ↔ мл)
 * - Конвертацию вес ↔ объём через плотность
 * - Конвертацию штуки ↔ вес через piece_weight
 * - Работу с фасовками (упаковка → базовые единицы)
 * - Расчёт брутто/нетто с учётом потерь
 */
class UnitConversionService
{
    /**
     * Стандартные плотности продуктов (кг/л)
     * Используются если плотность не указана в ингредиенте
     */
    const DEFAULT_DENSITIES = [
        'молоко' => 1.03,
        'сливки' => 1.01,
        'масло растительное' => 0.92,
        'масло оливковое' => 0.91,
        'мёд' => 1.42,
        'сметана' => 1.05,
        'кефир' => 1.03,
        'йогурт' => 1.03,
        'вода' => 1.0,
        'сок' => 1.05,
        'вино' => 0.99,
        'пиво' => 1.01,
        'спирт' => 0.79,
    ];

    /**
     * Стандартный вес штучных продуктов (кг)
     */
    const DEFAULT_PIECE_WEIGHTS = [
        'яйцо' => 0.05,      // 50г
        'яйцо куриное' => 0.05,
        'яйцо перепелиное' => 0.012,  // 12г
        'лимон' => 0.12,     // 120г
        'апельсин' => 0.2,   // 200г
        'банан' => 0.15,     // 150г
        'яблоко' => 0.18,    // 180г
        'луковица' => 0.1,   // 100г
        'зубчик чеснока' => 0.005,  // 5г
        'булочка' => 0.06,   // 60г
        'хлеб' => 0.4,       // 400г буханка
    ];

    /**
     * Стандартные потери при холодной обработке (%)
     */
    const DEFAULT_COLD_LOSSES = [
        'картофель' => 20,
        'морковь' => 20,
        'свёкла' => 20,
        'лук репчатый' => 16,
        'чеснок' => 22,
        'капуста' => 20,
        'огурец' => 5,
        'помидор' => 5,
        'перец болгарский' => 25,
        'баклажан' => 10,
        'кабачок' => 5,
        'яблоко' => 10,
        'курица' => 15,
        'говядина' => 10,
        'свинина' => 12,
        'рыба' => 35,
    ];

    /**
     * Стандартные потери при горячей обработке (%)
     */
    const DEFAULT_HOT_LOSSES = [
        'курица жарка' => 35,
        'курица варка' => 25,
        'говядина жарка' => 40,
        'говядина варка' => 38,
        'свинина жарка' => 35,
        'свинина варка' => 30,
        'рыба жарка' => 20,
        'рыба варка' => 18,
        'картофель варка' => 3,
        'картофель жарка' => 30,
        'овощи варка' => 10,
        'овощи жарка' => 25,
        'макароны варка' => -100, // увеличение (впитывание воды)
        'рис варка' => -150,
        'гречка варка' => -150,
    ];

    /**
     * Конвертировать количество из одной единицы в другую
     *
     * @param Ingredient $ingredient Ингредиент
     * @param float $quantity Исходное количество
     * @param Unit $fromUnit Исходная единица
     * @param Unit $toUnit Целевая единица
     * @return float Количество в целевых единицах
     */
    public function convert(Ingredient $ingredient, float $quantity, Unit $fromUnit, Unit $toUnit): float
    {
        // Если единицы одинаковые
        if ($fromUnit->id === $toUnit->id) {
            return $quantity;
        }

        // Сначала конвертируем в базовые единицы ингредиента
        $baseQuantity = $ingredient->convertToBaseUnit($quantity, $fromUnit);

        // Затем из базовых в целевые
        return $ingredient->convertFromBaseUnit($baseQuantity, $toUnit);
    }

    /**
     * Конвертировать фасовку в базовые единицы
     *
     * @param IngredientPackaging $packaging Фасовка
     * @param float $packagingQty Количество фасовок
     * @return float Количество в базовых единицах ингредиента
     */
    public function packagingToBase(IngredientPackaging $packaging, float $packagingQty): float
    {
        return $packaging->toBaseUnits($packagingQty);
    }

    /**
     * Конвертировать базовые единицы в фасовку
     *
     * @param IngredientPackaging $packaging Фасовка
     * @param float $baseQty Количество в базовых единицах
     * @return array ['full' => целые фасовки, 'remainder' => остаток в базовых]
     */
    public function baseToPackaging(IngredientPackaging $packaging, float $baseQty): array
    {
        $packagingQty = $packaging->fromBaseUnits($baseQty);
        $fullPackages = floor($packagingQty);
        $remainder = $baseQty - $packaging->toBaseUnits($fullPackages);

        return [
            'full' => $fullPackages,
            'fractional' => $packagingQty,
            'remainder' => round($remainder, 4),
        ];
    }

    /**
     * Рассчитать нетто из брутто
     *
     * @param Ingredient $ingredient Ингредиент
     * @param float $grossWeight Вес брутто
     * @param string $processingType Тип обработки
     * @return array ['net' => нетто, 'loss' => потери, 'loss_percent' => % потерь]
     */
    public function calculateNet(Ingredient $ingredient, float $grossWeight, string $processingType = 'both'): array
    {
        $netWeight = $ingredient->calculateNetWeight($grossWeight, $processingType);
        $loss = $grossWeight - $netWeight;
        $lossPercent = $grossWeight > 0 ? ($loss / $grossWeight) * 100 : 0;

        return [
            'gross' => $grossWeight,
            'net' => round($netWeight, 4),
            'loss' => round($loss, 4),
            'loss_percent' => round($lossPercent, 2),
        ];
    }

    /**
     * Рассчитать брутто из нетто
     *
     * @param Ingredient $ingredient Ингредиент
     * @param float $netWeight Вес нетто
     * @param string $processingType Тип обработки
     * @return array ['gross' => брутто, 'loss' => потери, 'loss_percent' => % потерь]
     */
    public function calculateGross(Ingredient $ingredient, float $netWeight, string $processingType = 'both'): array
    {
        $grossWeight = $ingredient->calculateGrossWeight($netWeight, $processingType);
        $loss = $grossWeight - $netWeight;
        $lossPercent = $grossWeight > 0 ? ($loss / $grossWeight) * 100 : 0;

        return [
            'gross' => round($grossWeight, 4),
            'net' => $netWeight,
            'loss' => round($loss, 4),
            'loss_percent' => round($lossPercent, 2),
        ];
    }

    /**
     * Получить рекомендуемую плотность по названию ингредиента
     *
     * @param string $ingredientName Название ингредиента
     * @return float|null Плотность или null
     */
    public function suggestDensity(string $ingredientName): ?float
    {
        $name = mb_strtolower($ingredientName);

        foreach (self::DEFAULT_DENSITIES as $key => $density) {
            if (str_contains($name, $key)) {
                return $density;
            }
        }

        return null;
    }

    /**
     * Получить рекомендуемый вес штуки по названию ингредиента
     *
     * @param string $ingredientName Название ингредиента
     * @return float|null Вес в кг или null
     */
    public function suggestPieceWeight(string $ingredientName): ?float
    {
        $name = mb_strtolower($ingredientName);

        foreach (self::DEFAULT_PIECE_WEIGHTS as $key => $weight) {
            if (str_contains($name, $key)) {
                return $weight;
            }
        }

        return null;
    }

    /**
     * Получить рекомендуемые потери при холодной обработке
     *
     * @param string $ingredientName Название ингредиента
     * @return float|null Процент потерь или null
     */
    public function suggestColdLoss(string $ingredientName): ?float
    {
        $name = mb_strtolower($ingredientName);

        foreach (self::DEFAULT_COLD_LOSSES as $key => $loss) {
            if (str_contains($name, $key)) {
                return $loss;
            }
        }

        return null;
    }

    /**
     * Получить рекомендуемые потери при горячей обработке
     *
     * @param string $ingredientName Название ингредиента
     * @param string $cookingMethod Метод приготовления (жарка, варка)
     * @return float|null Процент потерь или null
     */
    public function suggestHotLoss(string $ingredientName, string $cookingMethod = 'жарка'): ?float
    {
        $name = mb_strtolower($ingredientName);
        $method = mb_strtolower($cookingMethod);

        foreach (self::DEFAULT_HOT_LOSSES as $key => $loss) {
            if (str_contains($key, $method)) {
                $productName = str_replace([$method, ' '], '', $key);
                if (str_contains($name, $productName)) {
                    return $loss;
                }
            }
        }

        return null;
    }

    /**
     * Автозаполнение параметров ингредиента на основе названия
     *
     * @param Ingredient $ingredient Ингредиент для заполнения
     * @return array Предложенные значения
     */
    public function suggestParameters(Ingredient $ingredient): array
    {
        $name = $ingredient->name;
        $suggestions = [];

        // Плотность
        $density = $this->suggestDensity($name);
        if ($density !== null && empty($ingredient->density)) {
            $suggestions['density'] = $density;
        }

        // Вес штуки
        $pieceWeight = $this->suggestPieceWeight($name);
        if ($pieceWeight !== null && empty($ingredient->piece_weight)) {
            $suggestions['piece_weight'] = $pieceWeight;
        }

        // Потери при х/о
        $coldLoss = $this->suggestColdLoss($name);
        if ($coldLoss !== null && empty($ingredient->cold_loss_percent)) {
            $suggestions['cold_loss_percent'] = $coldLoss;
        }

        // Потери при г/о
        $hotLoss = $this->suggestHotLoss($name);
        if ($hotLoss !== null && empty($ingredient->hot_loss_percent)) {
            $suggestions['hot_loss_percent'] = $hotLoss;
        }

        return $suggestions;
    }

    /**
     * Валидировать возможность конвертации между единицами
     *
     * @param Ingredient $ingredient Ингредиент
     * @param Unit $fromUnit Исходная единица
     * @param Unit $toUnit Целевая единица
     * @return array ['valid' => bool, 'reason' => string|null]
     */
    public function canConvert(Ingredient $ingredient, Unit $fromUnit, Unit $toUnit): array
    {
        // Одинаковые единицы
        if ($fromUnit->id === $toUnit->id) {
            return ['valid' => true, 'reason' => null];
        }

        // Одинаковый тип - всегда можно
        if ($fromUnit->type === $toUnit->type) {
            return ['valid' => true, 'reason' => null];
        }

        // Вес ↔ Объём - нужна плотность
        if (($fromUnit->type === 'weight' && $toUnit->type === 'volume') ||
            ($fromUnit->type === 'volume' && $toUnit->type === 'weight')) {
            if (empty($ingredient->density)) {
                return [
                    'valid' => false,
                    'reason' => 'Для конвертации вес↔объём укажите плотность продукта'
                ];
            }
            return ['valid' => true, 'reason' => null];
        }

        // Штуки ↔ Вес - нужен вес штуки
        if (($fromUnit->type === 'piece' && $toUnit->type === 'weight') ||
            ($fromUnit->type === 'weight' && $toUnit->type === 'piece')) {
            if (empty($ingredient->piece_weight)) {
                return [
                    'valid' => false,
                    'reason' => 'Для конвертации шт↔вес укажите вес одной штуки'
                ];
            }
            return ['valid' => true, 'reason' => null];
        }

        return [
            'valid' => false,
            'reason' => 'Конвертация между этими типами единиц невозможна'
        ];
    }

    /**
     * Получить все возможные единицы измерения для ингредиента
     * с учётом настроенных параметров конвертации
     *
     * @param Ingredient $ingredient Ингредиент
     * @return array Список доступных единиц с коэффициентами
     */
    public function getAvailableUnits(Ingredient $ingredient): array
    {
        $baseUnit = $ingredient->unit;
        if (!$baseUnit) {
            return [];
        }

        $units = [];

        // Все единицы того же типа
        $sameTypeUnits = Unit::where('type', $baseUnit->type)->get();
        foreach ($sameTypeUnits as $unit) {
            $units[] = [
                'id' => $unit->id,
                'name' => $unit->name,
                'short_name' => $unit->short_name,
                'type' => $unit->type,
                'ratio' => $unit->base_ratio / $baseUnit->base_ratio,
                'is_base' => $unit->id === $baseUnit->id,
            ];
        }

        // Если есть плотность - добавляем единицы объёма/веса
        if ($ingredient->density > 0) {
            $convertType = $baseUnit->type === 'weight' ? 'volume' : 'weight';
            $convertUnits = Unit::where('type', $convertType)->get();

            foreach ($convertUnits as $unit) {
                $testQty = $ingredient->convertFromBaseUnit(1, $unit);
                $units[] = [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'short_name' => $unit->short_name,
                    'type' => $unit->type,
                    'ratio' => $testQty,
                    'is_base' => false,
                    'requires_density' => true,
                ];
            }
        }

        // Если есть вес штуки - добавляем штучные единицы
        if ($ingredient->piece_weight > 0 && $baseUnit->type === 'weight') {
            $pieceUnits = Unit::where('type', 'piece')->get();

            foreach ($pieceUnits as $unit) {
                $testQty = $ingredient->convertFromBaseUnit(1, $unit);
                $units[] = [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'short_name' => $unit->short_name,
                    'type' => $unit->type,
                    'ratio' => $testQty,
                    'is_base' => false,
                    'requires_piece_weight' => true,
                ];
            }
        }

        return $units;
    }
}
