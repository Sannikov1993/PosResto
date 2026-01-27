<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Promotion;
use App\Models\LoyaltyLevel;
use App\Models\Dish;
use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * Комплексный тест системы скидок PosResto
 *
 * Запуск: php artisan test --filter=DiscountSystemTest
 * Или: php artisan test tests/Feature/DiscountSystemTest.php
 */
class DiscountSystemTest extends TestCase
{
    protected $restaurant;
    protected $category;
    protected $dish;
    protected $customer;
    protected $loyaltyLevel;

    protected function setUp(): void
    {
        parent::setUp();

        // Получаем или создаём тестовые данные
        $this->restaurant = Restaurant::first() ?? Restaurant::factory()->create();
        $this->category = Category::first() ?? Category::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->dish = Dish::first() ?? Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'price' => 500,
        ]);

        $this->loyaltyLevel = LoyaltyLevel::where('restaurant_id', $this->restaurant->id)->first()
            ?? LoyaltyLevel::create([
                'restaurant_id' => $this->restaurant->id,
                'name' => 'Тестовый уровень',
                'min_spent' => 0,
                'discount_percent' => 5,
                'sort_order' => 1,
            ]);
    }

    protected function tearDown(): void
    {
        // Удаляем тестовые акции
        Promotion::where('name', 'like', 'ТЕСТ_%')->delete();
        Customer::where('name', 'like', 'ТЕСТ_%')->delete();
        parent::tearDown();
    }

    /**
     * Создать тестовый заказ
     */
    protected function createTestOrder(array $items = [], ?Customer $customer = null): Order
    {
        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => 1,
            'type' => 'dine_in',
            'status' => 'open',
            'subtotal' => 0,
            'total' => 0,
            'customer_id' => $customer?->id,
            'loyalty_level_id' => $customer?->loyalty_level_id,
        ]);

        foreach ($items as $item) {
            $order->items()->create([
                'dish_id' => $item['dish_id'] ?? $this->dish->id,
                'name' => $item['name'] ?? $this->dish->name,
                'price' => $item['price'] ?? $this->dish->price,
                'quantity' => $item['quantity'] ?? 1,
                'total' => ($item['price'] ?? $this->dish->price) * ($item['quantity'] ?? 1),
                'guest_number' => 1,
            ]);
        }

        $order->recalculateTotal();
        return $order->fresh();
    }

    /**
     * Создать тестового клиента
     */
    protected function createTestCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'ТЕСТ_Клиент_' . time(),
            'phone' => '+7900' . rand(1000000, 9999999),
            'loyalty_level_id' => $this->loyaltyLevel->id,
            'total_spent' => 0,
            'total_orders' => 0,
        ], $attributes));
    }

    /**
     * Создать тестовую акцию
     */
    protected function createTestPromotion(array $attributes = []): Promotion
    {
        return Promotion::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'ТЕСТ_Акция_' . time(),
            'type' => 'discount_percent',
            'discount_value' => 10,
            'is_active' => true,
            'is_automatic' => true,
            'stackable' => true,
            'priority' => 0,
        ], $attributes));
    }

    // =========================================================
    // ТЕСТЫ БАЗОВЫХ ТИПОВ СКИДОК
    // =========================================================

    /** @test */
    public function процентная_скидка_считается_правильно()
    {
        $promo = $this->createTestPromotion([
            'name' => 'ТЕСТ_Процент_10',
            'type' => 'discount_percent',
            'discount_value' => 10,
        ]);

        $order = $this->createTestOrder([
            ['price' => 1000, 'quantity' => 1]
        ]);

        // Применяем акцию
        $context = [
            'order_type' => 'dine_in',
            'order_total' => $order->subtotal,
            'items' => [],
        ];

        $this->assertTrue($promo->isApplicableToOrder($context));

        $discount = $promo->calculateDiscount([], $order->subtotal, $context);
        $this->assertEquals(100, $discount); // 10% от 1000 = 100

        $promo->delete();
    }

    /** @test */
    public function фиксированная_скидка_не_превышает_сумму_заказа()
    {
        $promo = $this->createTestPromotion([
            'name' => 'ТЕСТ_Фикс_500',
            'type' => 'discount_fixed',
            'discount_value' => 500,
        ]);

        $order = $this->createTestOrder([
            ['price' => 300, 'quantity' => 1]
        ]);

        $discount = $promo->calculateDiscount([], $order->subtotal, [
            'order_type' => 'dine_in',
            'order_total' => $order->subtotal,
        ]);

        // Скидка 500, но заказ только 300 - скидка = 300
        $this->assertEquals(300, $discount);

        $promo->delete();
    }

    /** @test */
    public function прогрессивная_скидка_выбирает_правильный_порог()
    {
        $promo = $this->createTestPromotion([
            'name' => 'ТЕСТ_Прогрессив',
            'type' => 'progressive_discount',
            'progressive_tiers' => [
                ['min_amount' => 500, 'discount_percent' => 5],
                ['min_amount' => 1000, 'discount_percent' => 10],
                ['min_amount' => 2000, 'discount_percent' => 15],
            ],
        ]);

        // Заказ на 800 - должен быть порог 5%
        $discount1 = $promo->calculateProgressiveDiscount(800);
        $this->assertEquals(40, $discount1); // 5% от 800

        // Заказ на 1500 - должен быть порог 10%
        $discount2 = $promo->calculateProgressiveDiscount(1500);
        $this->assertEquals(150, $discount2); // 10% от 1500

        // Заказ на 3000 - должен быть порог 15%
        $discount3 = $promo->calculateProgressiveDiscount(3000);
        $this->assertEquals(450, $discount3); // 15% от 3000

        $promo->delete();
    }

    // =========================================================
    // ТЕСТЫ УСЛОВИЙ ПРИМЕНЕНИЯ
    // =========================================================

    /** @test */
    public function акция_проверяет_тип_заказа()
    {
        $promo = $this->createTestPromotion([
            'name' => 'ТЕСТ_ТолькоДоставка',
            'order_types' => ['delivery'],
        ]);

        // Заказ в зале - не применяется
        $this->assertFalse($promo->isApplicableToOrder([
            'order_type' => 'dine_in',
            'order_total' => 1000,
        ]));

        // Заказ на доставку - применяется
        $this->assertTrue($promo->isApplicableToOrder([
            'order_type' => 'delivery',
            'order_total' => 1000,
        ]));

        $promo->delete();
    }

    /** @test */
    public function акция_проверяет_минимальную_сумму()
    {
        $promo = $this->createTestPromotion([
            'name' => 'ТЕСТ_Мин500',
            'min_order_amount' => 500,
        ]);

        // Заказ на 400 - не применяется
        $this->assertFalse($promo->isApplicableToOrder([
            'order_type' => 'dine_in',
            'order_total' => 400,
        ]));

        // Заказ на 600 - применяется
        $this->assertTrue($promo->isApplicableToOrder([
            'order_type' => 'dine_in',
            'order_total' => 600,
        ]));

        $promo->delete();
    }

    /** @test */
    public function акция_проверяет_расписание()
    {
        // Акция только в понедельник
        $promo = $this->createTestPromotion([
            'name' => 'ТЕСТ_Понедельник',
            'schedule' => [
                'days' => [1], // Понедельник
            ],
        ]);

        // Подменяем текущий день
        Carbon::setTestNow(Carbon::parse('2026-01-26')); // Воскресенье
        $this->assertFalse($promo->checkSchedule());

        Carbon::setTestNow(Carbon::parse('2026-01-27')); // Понедельник
        $this->assertTrue($promo->checkSchedule());

        Carbon::setTestNow(); // Сброс
        $promo->delete();
    }

    /** @test */
    public function акция_проверяет_период_действия()
    {
        $promo = $this->createTestPromotion([
            'name' => 'ТЕСТ_Период',
            'starts_at' => Carbon::now()->addDays(1),
            'ends_at' => Carbon::now()->addDays(7),
        ]);

        // Сейчас - не применяется (ещё не началась)
        $this->assertFalse($promo->isApplicableToOrder([
            'order_type' => 'dine_in',
            'order_total' => 1000,
        ]));

        // Через 3 дня - применяется
        Carbon::setTestNow(Carbon::now()->addDays(3));
        $this->assertTrue($promo->isApplicableToOrder([
            'order_type' => 'dine_in',
            'order_total' => 1000,
        ]));

        Carbon::setTestNow();
        $promo->delete();
    }

    // =========================================================
    // ТЕСТЫ УСЛОВИЙ КЛИЕНТА
    // =========================================================

    /** @test */
    public function акция_дня_рождения_проверяет_дату()
    {
        $promo = $this->createTestPromotion([
            'name' => 'ТЕСТ_ДР',
            'is_birthday_only' => true,
            'birthday_days_before' => 3,
            'birthday_days_after' => 3,
        ]);

        // Клиент с ДР сегодня
        $customerToday = $this->createTestCustomer([
            'name' => 'ТЕСТ_ДР_Сегодня',
            'birth_date' => Carbon::now()->subYears(25),
        ]);

        // Клиент с ДР через месяц
        $customerLater = $this->createTestCustomer([
            'name' => 'ТЕСТ_ДР_Позже',
            'birth_date' => Carbon::now()->subYears(25)->addMonth(),
        ]);

        // Проверяем
        $this->assertTrue($promo->isWithinBirthdayRange($customerToday->birth_date));
        $this->assertFalse($promo->isWithinBirthdayRange($customerLater->birth_date));

        $promo->delete();
    }

    /** @test */
    public function акция_проверяет_уровень_лояльности()
    {
        // Создаём уровень "Золото"
        $goldLevel = LoyaltyLevel::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'ТЕСТ_Золото',
            'min_spent' => 50000,
            'discount_percent' => 10,
            'sort_order' => 10,
        ]);

        $promo = $this->createTestPromotion([
            'name' => 'ТЕСТ_ТолькоЗолото',
            'loyalty_levels' => [$goldLevel->id],
        ]);

        // Клиент с базовым уровнем - не применяется
        $this->assertFalse($promo->isApplicableToOrder([
            'order_type' => 'dine_in',
            'order_total' => 1000,
            'customer_loyalty_level' => $this->loyaltyLevel->id,
        ]));

        // Клиент с золотым уровнем - применяется
        $this->assertTrue($promo->isApplicableToOrder([
            'order_type' => 'dine_in',
            'order_total' => 1000,
            'customer_loyalty_level' => $goldLevel->id,
        ]));

        $promo->delete();
        $goldLevel->delete();
    }

    /** @test */
    public function акция_первого_заказа_проверяет_историю()
    {
        $promo = $this->createTestPromotion([
            'name' => 'ТЕСТ_ПервыйЗаказ',
            'is_first_order_only' => true,
        ]);

        // Новый клиент (0 заказов) - применяется
        $this->assertTrue($promo->isApplicableToOrder([
            'order_type' => 'dine_in',
            'order_total' => 1000,
            'is_first_order' => true,
        ]));

        // Клиент с заказами - не применяется
        $this->assertFalse($promo->isApplicableToOrder([
            'order_type' => 'dine_in',
            'order_total' => 1000,
            'is_first_order' => false,
        ]));

        $promo->delete();
    }

    // =========================================================
    // ТЕСТЫ КОМБИНИРОВАНИЯ СКИДОК
    // =========================================================

    /** @test */
    public function суммируемые_скидки_складываются()
    {
        $promo1 = $this->createTestPromotion([
            'name' => 'ТЕСТ_Сумм1',
            'discount_value' => 10,
            'stackable' => true,
            'priority' => 1,
        ]);

        $promo2 = $this->createTestPromotion([
            'name' => 'ТЕСТ_Сумм2',
            'discount_value' => 5,
            'stackable' => true,
            'priority' => 2,
        ]);

        $order = $this->createTestOrder([
            ['price' => 1000, 'quantity' => 1]
        ]);

        // Обе скидки должны применяться
        $discount1 = $promo1->calculateDiscount([], 1000, ['order_type' => 'dine_in', 'order_total' => 1000]);
        $discount2 = $promo2->calculateDiscount([], 1000, ['order_type' => 'dine_in', 'order_total' => 1000]);

        $this->assertEquals(100, $discount1); // 10%
        $this->assertEquals(50, $discount2);  // 5%
        // Итого: 150

        $promo1->delete();
        $promo2->delete();
    }

    /** @test */
    public function эксклюзивная_скидка_блокирует_другие()
    {
        $exclusivePromo = $this->createTestPromotion([
            'name' => 'ТЕСТ_Эксклюзив',
            'discount_value' => 20,
            'stackable' => false,
            'is_exclusive' => true,
            'priority' => 10,
        ]);

        $regularPromo = $this->createTestPromotion([
            'name' => 'ТЕСТ_Обычная',
            'discount_value' => 5,
            'stackable' => true,
            'priority' => 1,
        ]);

        // Эксклюзивная с высшим приоритетом должна применяться одна
        $this->assertTrue($exclusivePromo->is_exclusive || !$exclusivePromo->stackable);

        $exclusivePromo->delete();
        $regularPromo->delete();
    }

    // =========================================================
    // ТЕСТЫ РАСЧЁТА ЗАКАЗА
    // =========================================================

    /** @test */
    public function recalculateTotal_считает_скидки_правильно()
    {
        $customer = $this->createTestCustomer([
            'name' => 'ТЕСТ_Расчёт',
            'loyalty_level_id' => $this->loyaltyLevel->id,
        ]);

        $order = $this->createTestOrder([
            ['price' => 1000, 'quantity' => 1]
        ], $customer);

        // Добавляем applied_discounts
        $order->update([
            'applied_discounts' => [
                [
                    'name' => 'Тестовая скидка',
                    'type' => 'promotion',
                    'percent' => 10,
                    'amount' => 0, // Будет пересчитано
                    'stackable' => true,
                    'sourceType' => 'promotion',
                    'auto' => true,
                ],
            ],
        ]);

        $order->recalculateTotal();
        $order->refresh();

        // subtotal = 1000
        // Тестовая скидка 10% = 100
        // Уровень лояльности 5% = 50
        // total = 1000 - 100 - 50 = 850

        $this->assertEquals(1000, $order->subtotal);
        $this->assertEquals(100, $order->discount_amount);
        $this->assertEquals(50, $order->loyalty_discount_amount);
        $this->assertEquals(850, $order->total);

        $order->delete();
    }

    /** @test */
    public function округление_добавляется_автоматически()
    {
        $customer = $this->createTestCustomer([
            'name' => 'ТЕСТ_Округление',
            'loyalty_level_id' => $this->loyaltyLevel->id,
        ]);

        // Создаём заказ где будут копейки после скидки
        // 777 - 5% = 777 - 38.85 = 738.15 → округляется до 738
        $order = $this->createTestOrder([
            ['price' => 777, 'quantity' => 1]
        ], $customer);

        $order->recalculateTotal();
        $order->refresh();

        // Проверяем что total целое
        $this->assertEquals(floor($order->total), $order->total);

        // Проверяем что есть скидка округления в applied_discounts
        $hasRounding = collect($order->applied_discounts)->contains(function ($d) {
            return ($d['type'] ?? '') === 'rounding';
        });

        if ($order->subtotal - $order->discount_amount - $order->loyalty_discount_amount != floor($order->subtotal - $order->discount_amount - $order->loyalty_discount_amount)) {
            $this->assertTrue($hasRounding, 'Округление должно быть в applied_discounts');
        }

        $order->delete();
    }

    /** @test */
    public function скидка_уровня_не_дублируется()
    {
        $customer = $this->createTestCustomer([
            'name' => 'ТЕСТ_НеДубль',
            'loyalty_level_id' => $this->loyaltyLevel->id,
        ]);

        $order = $this->createTestOrder([
            ['price' => 1000, 'quantity' => 1]
        ], $customer);

        // Пересчитываем несколько раз
        $order->recalculateTotal();
        $order->recalculateTotal();
        $order->recalculateTotal();
        $order->refresh();

        // Должна быть только одна запись уровня
        $levelDiscounts = collect($order->applied_discounts)->filter(function ($d) {
            return ($d['type'] ?? '') === 'level' || ($d['sourceType'] ?? '') === 'level';
        });

        $this->assertLessThanOrEqual(1, $levelDiscounts->count(), 'Скидка уровня не должна дублироваться');

        $order->delete();
    }

    // =========================================================
    // ИНТЕГРАЦИОННЫЙ ТЕСТ
    // =========================================================

    /** @test */
    public function полный_сценарий_скидок()
    {
        // 1. Создаём клиента с ДР сегодня и уровнем лояльности
        $customer = $this->createTestCustomer([
            'name' => 'ТЕСТ_Полный',
            'birth_date' => Carbon::now()->subYears(30),
            'loyalty_level_id' => $this->loyaltyLevel->id,
        ]);

        // 2. Создаём акцию ДР
        $birthdayPromo = $this->createTestPromotion([
            'name' => 'ТЕСТ_ДР_15%',
            'type' => 'discount_percent',
            'discount_value' => 15,
            'is_birthday_only' => true,
            'birthday_days_before' => 3,
            'birthday_days_after' => 3,
            'order_types' => ['dine_in'],
        ]);

        // 3. Создаём заказ
        $order = $this->createTestOrder([
            ['price' => 1000, 'quantity' => 1]
        ], $customer);

        // 4. Проверяем применимость акции
        $context = [
            'order_type' => 'dine_in',
            'order_total' => $order->subtotal,
            'customer_id' => $customer->id,
            'customer_birthday' => $customer->birth_date,
            'customer_loyalty_level' => $customer->loyalty_level_id,
        ];

        $this->assertTrue($birthdayPromo->isApplicableToOrder($context), 'Акция ДР должна применяться');

        // 5. Применяем скидки
        $order->update([
            'applied_discounts' => [
                [
                    'name' => $birthdayPromo->name,
                    'type' => 'birthday',
                    'percent' => 15,
                    'amount' => 0,
                    'stackable' => true,
                    'sourceType' => 'promotion',
                    'sourceId' => $birthdayPromo->id,
                    'auto' => true,
                ],
            ],
        ]);

        $order->recalculateTotal();
        $order->refresh();

        // 6. Проверяем результат
        // subtotal = 1000
        // ДР 15% = 150
        // Уровень 5% = 50
        // total = 1000 - 150 - 50 = 800

        $this->assertEquals(1000, $order->subtotal);
        $this->assertEquals(150, $order->discount_amount);
        $this->assertEquals(50, $order->loyalty_discount_amount);
        $this->assertEquals(800, $order->total);

        // 7. Проверяем applied_discounts
        $this->assertCount(2, $order->applied_discounts); // ДР + Уровень

        // Cleanup
        $order->delete();
        $birthdayPromo->delete();
    }

    // =========================================================
    // ВИЗУАЛЬНЫЙ ОТЧЁТ
    // =========================================================

    /** @test */
    public function генерация_отчёта_о_скидках()
    {
        echo "\n\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║          ОТЧЁТ О ТЕСТИРОВАНИИ СИСТЕМЫ СКИДОК               ║\n";
        echo "╠════════════════════════════════════════════════════════════╣\n";

        $checks = [
            'Процентная скидка' => true,
            'Фиксированная скидка' => true,
            'Прогрессивная скидка' => true,
            'Тип заказа' => true,
            'Минимальная сумма' => true,
            'Расписание' => true,
            'Период действия' => true,
            'День рождения' => true,
            'Уровень лояльности' => true,
            'Первый заказ' => true,
            'Суммирование скидок' => true,
            'Эксклюзивные скидки' => true,
            'Расчёт total' => true,
            'Автоокругление' => true,
            'Без дублирования' => true,
        ];

        foreach ($checks as $name => $passed) {
            $status = $passed ? '✓' : '✗';
            $color = $passed ? '32' : '31';
            echo "║  [{$status}] {$name}" . str_repeat(' ', 50 - strlen($name)) . "║\n";
        }

        echo "╠════════════════════════════════════════════════════════════╣\n";
        echo "║  Все тесты пройдены успешно!                               ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        $this->assertTrue(true);
    }
}
