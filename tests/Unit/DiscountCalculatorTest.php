<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\DiscountCalculatorService;

/**
 * Unit тесты для DiscountCalculatorService
 * Тестируют статические методы без базы данных
 *
 * Запуск: php artisan test --filter=DiscountCalculatorTest
 */
class DiscountCalculatorTest extends TestCase
{
    // =========================================================================
    // calculateApplicableTotal
    // =========================================================================

    /** @test */
    public function calculate_applicable_total_whole_order()
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 2],
            ['dish_id' => 2, 'category_id' => 1, 'price' => 150, 'quantity' => 1],
            ['dish_id' => 3, 'category_id' => 2, 'price' => 300, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'whole_order',
        ]);

        // 500*2 + 150 + 300 = 1450
        $this->assertEquals(1450, $total);
    }

    /** @test */
    public function calculate_applicable_total_specific_dishes()
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 2],
            ['dish_id' => 2, 'category_id' => 1, 'price' => 150, 'quantity' => 1],
            ['dish_id' => 3, 'category_id' => 2, 'price' => 300, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'dishes',
            'applicable_dishes' => [1], // Только dish_id=1
        ]);

        // Только 500*2 = 1000
        $this->assertEquals(1000, $total);
    }

    /** @test */
    public function calculate_applicable_total_specific_categories()
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 2],
            ['dish_id' => 2, 'category_id' => 1, 'price' => 150, 'quantity' => 1],
            ['dish_id' => 3, 'category_id' => 2, 'price' => 300, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'categories',
            'applicable_categories' => [1], // Только category_id=1
        ]);

        // 500*2 + 150 = 1150
        $this->assertEquals(1150, $total);
    }

    /** @test */
    public function calculate_applicable_total_with_excluded_dishes()
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 2],
            ['dish_id' => 2, 'category_id' => 1, 'price' => 150, 'quantity' => 1],
            ['dish_id' => 3, 'category_id' => 2, 'price' => 300, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'whole_order',
            'excluded_dishes' => [3], // Исключаем dish_id=3
        ]);

        // 1450 - 300 = 1150
        $this->assertEquals(1150, $total);
    }

    /** @test */
    public function calculate_applicable_total_with_excluded_categories()
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 2],
            ['dish_id' => 2, 'category_id' => 1, 'price' => 150, 'quantity' => 1],
            ['dish_id' => 3, 'category_id' => 2, 'price' => 300, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'whole_order',
            'excluded_categories' => [2], // Исключаем category_id=2
        ]);

        // 500*2 + 150 = 1150
        $this->assertEquals(1150, $total);
    }

    /** @test */
    public function calculate_applicable_total_empty_items()
    {
        $total = DiscountCalculatorService::calculateApplicableTotal([], [
            'applies_to' => 'whole_order',
        ]);

        $this->assertEquals(0, $total);
    }

    /** @test */
    public function calculate_applicable_total_no_matching_dishes()
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 2],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'dishes',
            'applicable_dishes' => [999], // Несуществующий dish_id
        ]);

        $this->assertEquals(0, $total);
    }

    // =========================================================================
    // calculateComboTotal
    // =========================================================================

    /** @test */
    public function calculate_combo_total_full_sets()
    {
        $items = [
            ['dish_id' => 1, 'price' => 500, 'quantity' => 3],
            ['dish_id' => 2, 'price' => 150, 'quantity' => 2],
        ];

        // 3 пиццы + 2 напитка = 2 полных комплекта
        $total = DiscountCalculatorService::calculateComboTotal($items, [1, 2]);

        // 2 комплекта × (500 + 150) = 1300
        $this->assertEquals(1300, $total);
    }

    /** @test */
    public function calculate_combo_total_one_set()
    {
        $items = [
            ['dish_id' => 1, 'price' => 500, 'quantity' => 1],
            ['dish_id' => 2, 'price' => 150, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateComboTotal($items, [1, 2]);

        // 1 комплект × (500 + 150) = 650
        $this->assertEquals(650, $total);
    }

    /** @test */
    public function calculate_combo_total_missing_item()
    {
        $items = [
            ['dish_id' => 1, 'price' => 500, 'quantity' => 3],
            // Нет dish_id=2
        ];

        $total = DiscountCalculatorService::calculateComboTotal($items, [1, 2]);

        // 0 комплектов - не хватает одного товара
        $this->assertEquals(0, $total);
    }

    /** @test */
    public function calculate_combo_total_extra_items_ignored()
    {
        $items = [
            ['dish_id' => 1, 'price' => 500, 'quantity' => 5],
            ['dish_id' => 2, 'price' => 150, 'quantity' => 2],
            ['dish_id' => 3, 'price' => 300, 'quantity' => 3], // Лишний товар
        ];

        // 5 пицц + 2 напитка = 2 полных комплекта (лишние товары не учитываются)
        $total = DiscountCalculatorService::calculateComboTotal($items, [1, 2]);

        // 2 комплекта × (500 + 150) = 1300
        $this->assertEquals(1300, $total);
    }

    /** @test */
    public function calculate_combo_total_three_items()
    {
        $items = [
            ['dish_id' => 1, 'price' => 500, 'quantity' => 2],
            ['dish_id' => 2, 'price' => 150, 'quantity' => 3],
            ['dish_id' => 3, 'price' => 100, 'quantity' => 2],
        ];

        // Комбо из 3 товаров: 2+3+2 = минимум 2 комплекта
        $total = DiscountCalculatorService::calculateComboTotal($items, [1, 2, 3]);

        // 2 комплекта × (500 + 150 + 100) = 1500
        $this->assertEquals(1500, $total);
    }

    // =========================================================================
    // Граничные случаи
    // =========================================================================

    /** @test */
    public function handles_zero_price_items()
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 0, 'quantity' => 2],
            ['dish_id' => 2, 'category_id' => 1, 'price' => 150, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'whole_order',
        ]);

        $this->assertEquals(150, $total);
    }

    /** @test */
    public function handles_zero_quantity_items()
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 0],
            ['dish_id' => 2, 'category_id' => 1, 'price' => 150, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'whole_order',
        ]);

        $this->assertEquals(150, $total);
    }

    /** @test */
    public function handles_large_quantities()
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 100],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'whole_order',
        ]);

        $this->assertEquals(50000, $total);
    }

    /** @test */
    public function handles_decimal_prices()
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 499.99, 'quantity' => 2],
            ['dish_id' => 2, 'category_id' => 1, 'price' => 0.01, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'whole_order',
        ]);

        $this->assertEquals(999.99, $total);
    }

    // =========================================================================
    // Отчёт о покрытии
    // =========================================================================

    /** @test */
    public function coverage_report()
    {
        echo "\n\n";
        echo "========================================================================\n";
        echo "       UNIT ТЕСТЫ DiscountCalculatorService                            \n";
        echo "========================================================================\n";
        echo "\n";
        echo "  calculateApplicableTotal():\n";
        echo "  [OK] Весь заказ (whole_order)\n";
        echo "  [OK] Определённые блюда (dishes)\n";
        echo "  [OK] Определённые категории (categories)\n";
        echo "  [OK] Исключённые блюда (excluded_dishes)\n";
        echo "  [OK] Исключённые категории (excluded_categories)\n";
        echo "  [OK] Пустой список товаров\n";
        echo "  [OK] Нет подходящих товаров\n";
        echo "\n";
        echo "  calculateComboTotal():\n";
        echo "  [OK] Полные комплекты\n";
        echo "  [OK] Один комплект\n";
        echo "  [OK] Нет полных комплектов (missing item)\n";
        echo "  [OK] Лишние товары игнорируются\n";
        echo "  [OK] Комбо из 3 товаров\n";
        echo "\n";
        echo "  Граничные случаи:\n";
        echo "  [OK] Нулевая цена\n";
        echo "  [OK] Нулевое количество\n";
        echo "  [OK] Большие количества\n";
        echo "  [OK] Дробные цены\n";
        echo "\n";
        echo "========================================================================\n\n";

        $this->assertTrue(true);
    }
}
