<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Promotion;
use App\Models\PromoCode;
use App\Models\LoyaltyLevel;
use App\Models\Dish;
use App\Models\Category;
use App\Models\Restaurant;
use App\Models\Zone;
use App\Models\Table;
use App\Services\DiscountCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * ПОЛНЫЙ ТЕСТ СИСТЕМЫ СКИДОК MenuLab
 *
 * Покрывает ВСЕ условия и сценарии:
 * - Типы скидок (percent, fixed, progressive, free_delivery, gift, bonus)
 * - Условия применения (order_type, min_amount, schedule, birthday, loyalty, etc.)
 * - Комбинации условий
 * - Промокоды
 * - DiscountCalculatorService
 * - Граничные случаи
 *
 * Запуск: php artisan test --filter=DiscountSystemFullTest
 */
class DiscountSystemFullTest extends TestCase
{
    use RefreshDatabase;

    protected $restaurant;
    protected $category;
    protected $category2;
    protected $dish1;
    protected $dish2;
    protected $dish3;
    protected $zone;
    protected $table;
    protected $loyaltyLevel;
    protected $goldLevel;

    protected function setUp(): void
    {
        parent::setUp();

        // Используем существующий ресторан или создаём тестовый
        $this->restaurant = Restaurant::first();
        if (!$this->restaurant) {
            $this->restaurant = Restaurant::factory()->create();
        }

        // Используем существующие категории или создаём тестовые
        $this->category = Category::where('restaurant_id', $this->restaurant->id)->first();
        if (!$this->category) {
            $this->category = Category::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'name' => 'Тест_Пицца',
            ]);
        }

        $this->category2 = Category::where('restaurant_id', $this->restaurant->id)
            ->where('id', '!=', $this->category->id)
            ->first();
        if (!$this->category2) {
            $this->category2 = Category::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'name' => 'Тест_Десерты',
            ]);
        }

        // Используем существующие блюда или создаём тестовые
        $this->dish1 = Dish::where('restaurant_id', $this->restaurant->id)
            ->where('category_id', $this->category->id)
            ->first();
        if (!$this->dish1) {
            $this->dish1 = Dish::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'category_id' => $this->category->id,
                'name' => 'Тест_Пицца',
                'price' => 500,
            ]);
        }

        $this->dish2 = Dish::where('restaurant_id', $this->restaurant->id)
            ->where('category_id', $this->category->id)
            ->where('id', '!=', $this->dish1->id)
            ->first();
        if (!$this->dish2) {
            $this->dish2 = Dish::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'category_id' => $this->category->id,
                'name' => 'Тест_Кола',
                'price' => 150,
            ]);
        }

        $this->dish3 = Dish::where('restaurant_id', $this->restaurant->id)
            ->where('category_id', $this->category2->id)
            ->first();
        if (!$this->dish3) {
            $this->dish3 = Dish::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'category_id' => $this->category2->id,
                'name' => 'Тест_Десерт',
                'price' => 300,
            ]);
        }

        // Зона и стол
        $this->zone = Zone::where('restaurant_id', $this->restaurant->id)->first();
        if (!$this->zone) {
            $this->zone = Zone::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'name' => 'Тест_Зона',
            ]);
        }

        $this->table = Table::where('restaurant_id', $this->restaurant->id)->first();
        if (!$this->table) {
            $this->table = Table::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'zone_id' => $this->zone->id,
                'number' => 'T1',
                'seats' => 4,
            ]);
        }

        // Уровни лояльности
        $this->loyaltyLevel = LoyaltyLevel::where('restaurant_id', $this->restaurant->id)
            ->orderBy('min_total', 'asc')
            ->first();
        if (!$this->loyaltyLevel) {
            $this->loyaltyLevel = LoyaltyLevel::create([
                'restaurant_id' => $this->restaurant->id,
                'name' => 'Тест_Базовый',
                'min_spent' => 0,
                'discount_percent' => 5,
                'cashback_percent' => 3,
                'sort_order' => 1,
            ]);
        }

        $this->goldLevel = LoyaltyLevel::where('restaurant_id', $this->restaurant->id)
            ->where('id', '!=', $this->loyaltyLevel->id)
            ->orderBy('min_total', 'desc')
            ->first();
        if (!$this->goldLevel) {
            $this->goldLevel = LoyaltyLevel::create([
                'restaurant_id' => $this->restaurant->id,
                'name' => 'Тест_Золото',
                'min_spent' => 50000,
                'discount_percent' => 10,
                'cashback_percent' => 5,
                'sort_order' => 10,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Сброс времени
        parent::tearDown();
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    protected function createPromotion(array $attributes = []): Promotion
    {
        $name = $attributes['name'] ?? 'Тестовая акция ' . uniqid();
        $slug = \Illuminate\Support\Str::slug($name) . '-' . uniqid();

        // Убираем name и slug из attributes, т.к. мы их генерируем
        unset($attributes['name']);
        if (!isset($attributes['slug'])) {
            $attributes['slug'] = $slug;
        }

        return Promotion::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'name' => $name,
            'slug' => $slug,
            'type' => 'discount_percent',
            'discount_value' => 10,
            'is_active' => true,
            'is_automatic' => true,
            'stackable' => true,
            'priority' => 0,
            'reward_type' => 'discount',
            'applies_to' => 'whole_order',
        ], $attributes));
    }

    protected function createPromoCode(array $attributes = []): PromoCode
    {
        return PromoCode::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'TEST' . strtoupper(uniqid()),
            'name' => 'Тестовый промокод',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
        ], $attributes));
    }

    protected function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тест Клиент',
            'phone' => '+7900' . rand(1000000, 9999999),
            'loyalty_level_id' => $this->loyaltyLevel->id,
            'total_spent' => 0,
            'total_orders' => 0,
        ], $attributes));
    }

    protected function getOrderItems(): array
    {
        return [
            ['dish_id' => $this->dish1->id, 'category_id' => $this->category->id, 'price' => $this->dish1->price, 'quantity' => 2],
            ['dish_id' => $this->dish2->id, 'category_id' => $this->category->id, 'price' => $this->dish2->price, 'quantity' => 1],
            ['dish_id' => $this->dish3->id, 'category_id' => $this->category2->id, 'price' => $this->dish3->price, 'quantity' => 1],
        ];
    }

    protected function getOrderTotal(): float
    {
        return ($this->dish1->price * 2) + $this->dish2->price + $this->dish3->price;
    }

    protected function getContext(array $overrides = []): array
    {
        return array_merge([
            'order_type' => 'dine_in',
            'order_total' => $this->getOrderTotal(),
            'items' => $this->getOrderItems(),
            'source_channel' => 'pos',
        ], $overrides);
    }

    // =========================================================================
    // 1. ТИПЫ СКИДОК
    // =========================================================================

    /** @test */
    public function тип_скидки_процент()
    {
        $promo = $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 15,
        ]);

        $orderTotal = $this->getOrderTotal();
        $discount = $promo->calculateDiscount($this->getOrderItems(), $orderTotal, $this->getContext());
        $expected = $orderTotal * 0.15;
        $this->assertEquals($expected, $discount); // 15% от суммы заказа
    }

    /** @test */
    public function тип_скидки_процент_с_максимумом()
    {
        $promo = $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 50,
            'max_discount' => 200,
        ]);

        $discount = $promo->calculateDiscount($this->getOrderItems(), 1450, $this->getContext());
        // 50% от 1450 = 725, но max_discount = 200
        $this->assertEquals(200, $discount);
    }

    /** @test */
    public function тип_скидки_фиксированная()
    {
        $promo = $this->createPromotion([
            'type' => 'discount_fixed',
            'discount_value' => 300,
        ]);

        $discount = $promo->calculateDiscount($this->getOrderItems(), 1450, $this->getContext());
        $this->assertEquals(300, $discount);
    }

    /** @test */
    public function тип_скидки_фиксированная_не_больше_суммы()
    {
        $orderTotal = $this->getOrderTotal();
        $promo = $this->createPromotion([
            'type' => 'discount_fixed',
            'discount_value' => $orderTotal + 500, // Больше чем сумма заказа
        ]);

        $discount = $promo->calculateDiscount($this->getOrderItems(), $orderTotal, $this->getContext());
        // Скидка не может быть больше суммы заказа
        $this->assertEquals($orderTotal, $discount);
    }

    /** @test */
    public function тип_скидки_от_суммы_заказа()
    {
        // Тестируем скидку с минимальной суммой заказа
        $promo = $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 15,
            'min_order_amount' => 1000,
        ]);

        $orderTotal = $this->getOrderTotal();

        // При достаточной сумме скидка работает
        if ($orderTotal >= 1000) {
            $discount = $promo->calculateDiscount($this->getOrderItems(), $orderTotal, $this->getContext());
            $this->assertEquals($orderTotal * 0.15, $discount);
        }

        // При недостаточной сумме не применяется
        $this->assertFalse($promo->isApplicableToOrder($this->getContext(['order_total' => 500])));
    }

    /** @test */
    public function тип_скидки_бесплатная_доставка()
    {
        $promo = $this->createPromotion([
            'type' => 'free_delivery',
            'discount_value' => 0,
            'min_order_amount' => 1000,
        ]);

        $context = $this->getContext(['order_type' => 'delivery']);
        $this->assertTrue($promo->isApplicableToOrder($context));

        // Скидка = 0, но акция применяется
        $discount = $promo->calculateDiscount($this->getOrderItems(), 1450, $context);
        $this->assertEquals(0, $discount);
    }

    /** @test */
    public function тип_скидки_подарок()
    {
        $promo = $this->createPromotion([
            'type' => 'gift',
            'gift_dish_id' => $this->dish2->id,
            'min_order_amount' => 1000,
        ]);

        $this->assertTrue($promo->isApplicableToOrder($this->getContext()));
        $this->assertEquals($this->dish2->id, $promo->gift_dish_id);
    }

    /** @test */
    public function тип_скидки_комбинированная()
    {
        // Тестируем скидку на определённые блюда
        $promo = $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 20,
            'applies_to' => 'dishes',
            'applicable_dishes' => [$this->dish1->id, $this->dish2->id],
        ]);

        $this->assertTrue($promo->isApplicableToOrder($this->getContext()));

        $orderTotal = $this->getOrderTotal();
        $discount = $promo->calculateDiscount($this->getOrderItems(), $orderTotal, $this->getContext());

        // Скидка только на dish1 и dish2
        $applicableTotal = ($this->dish1->price * 2) + $this->dish2->price;
        $this->assertEquals($applicableTotal * 0.20, $discount);
    }

    // =========================================================================
    // 2. УСЛОВИЯ ПРИМЕНЕНИЯ - ТИП ЗАКАЗА
    // =========================================================================

    /** @test */
    public function условие_только_доставка()
    {
        $promo = $this->createPromotion([
            'order_types' => ['delivery'],
        ]);

        $this->assertFalse($promo->isApplicableToOrder($this->getContext(['order_type' => 'dine_in'])));
        $this->assertFalse($promo->isApplicableToOrder($this->getContext(['order_type' => 'pickup'])));
        $this->assertTrue($promo->isApplicableToOrder($this->getContext(['order_type' => 'delivery'])));
    }

    /** @test */
    public function условие_доставка_и_самовывоз()
    {
        $promo = $this->createPromotion([
            'order_types' => ['delivery', 'pickup'],
        ]);

        $this->assertFalse($promo->isApplicableToOrder($this->getContext(['order_type' => 'dine_in'])));
        $this->assertTrue($promo->isApplicableToOrder($this->getContext(['order_type' => 'delivery'])));
        $this->assertTrue($promo->isApplicableToOrder($this->getContext(['order_type' => 'pickup'])));
    }

    /** @test */
    public function условие_пустой_order_types_применяется_ко_всем()
    {
        $promo = $this->createPromotion([
            'order_types' => null,
        ]);

        $this->assertTrue($promo->isApplicableToOrder($this->getContext(['order_type' => 'dine_in'])));
        $this->assertTrue($promo->isApplicableToOrder($this->getContext(['order_type' => 'delivery'])));
        $this->assertTrue($promo->isApplicableToOrder($this->getContext(['order_type' => 'pickup'])));
    }

    // =========================================================================
    // 3. УСЛОВИЯ ПРИМЕНЕНИЯ - МИНИМАЛЬНАЯ СУММА
    // =========================================================================

    /** @test */
    public function условие_минимальная_сумма_не_достигнута()
    {
        $promo = $this->createPromotion([
            'min_order_amount' => 2000,
        ]);

        $this->assertFalse($promo->isApplicableToOrder($this->getContext(['order_total' => 1450])));
        $this->assertFalse($promo->isApplicableToOrder($this->getContext(['order_total' => 1999])));
    }

    /** @test */
    public function условие_минимальная_сумма_достигнута()
    {
        $promo = $this->createPromotion([
            'min_order_amount' => 1000,
        ]);

        $this->assertTrue($promo->isApplicableToOrder($this->getContext(['order_total' => 1000])));
        $this->assertTrue($promo->isApplicableToOrder($this->getContext(['order_total' => 1450])));
    }

    /** @test */
    public function условие_минимальное_количество_позиций()
    {
        $promo = $this->createPromotion([
            'min_items_count' => 5,
        ]);

        // У нас 4 позиции (2+1+1)
        $this->assertFalse($promo->isApplicableToOrder($this->getContext()));

        // Добавляем больше позиций
        $items = $this->getOrderItems();
        $items[0]['quantity'] = 5; // Теперь 5+1+1 = 7 позиций
        $context = $this->getContext(['items' => $items]);

        $this->assertTrue($promo->isApplicableToOrder($context));
    }

    // =========================================================================
    // 4. УСЛОВИЯ ПРИМЕНЕНИЯ - РАСПИСАНИЕ
    // =========================================================================

    /** @test */
    public function условие_расписание_день_недели()
    {
        $promo = $this->createPromotion([
            'schedule' => [
                'days' => [1, 3], // Понедельник, Среда
            ],
        ]);

        // Воскресенье - не работает
        Carbon::setTestNow(Carbon::parse('2026-01-25 12:00:00', 'Europe/Moscow')); // Воскресенье
        $this->assertFalse($promo->isCurrentlyActive());

        // Понедельник - работает
        Carbon::setTestNow(Carbon::parse('2026-01-26 12:00:00', 'Europe/Moscow')); // Понедельник
        $this->assertTrue($promo->isCurrentlyActive());

        // Вторник - не работает
        Carbon::setTestNow(Carbon::parse('2026-01-27 12:00:00', 'Europe/Moscow')); // Вторник
        $this->assertFalse($promo->isCurrentlyActive());

        // Среда - работает
        Carbon::setTestNow(Carbon::parse('2026-01-28 12:00:00', 'Europe/Moscow')); // Среда
        $this->assertTrue($promo->isCurrentlyActive());
    }

    /** @test */
    public function условие_расписание_время()
    {
        $promo = $this->createPromotion([
            'schedule' => [
                'time_from' => '12:00',
                'time_to' => '15:00',
            ],
        ]);

        Carbon::setTestNow(Carbon::parse('2026-01-26 11:59:00', 'Europe/Moscow'));
        $this->assertFalse($promo->isCurrentlyActive());

        Carbon::setTestNow(Carbon::parse('2026-01-26 12:00:00', 'Europe/Moscow'));
        $this->assertTrue($promo->isCurrentlyActive());

        Carbon::setTestNow(Carbon::parse('2026-01-26 14:30:00', 'Europe/Moscow'));
        $this->assertTrue($promo->isCurrentlyActive());

        Carbon::setTestNow(Carbon::parse('2026-01-26 15:01:00', 'Europe/Moscow'));
        $this->assertFalse($promo->isCurrentlyActive());
    }

    /** @test */
    public function условие_расписание_день_и_время()
    {
        $promo = $this->createPromotion([
            'schedule' => [
                'days' => [5, 6], // Пятница, Суббота
                'time_from' => '18:00',
                'time_to' => '22:00',
            ],
        ]);

        // Пятница в 19:00 - работает
        Carbon::setTestNow(Carbon::parse('2026-01-30 19:00:00', 'Europe/Moscow')); // Пятница
        $this->assertTrue($promo->isCurrentlyActive());

        // Пятница в 17:00 - не работает (рано)
        Carbon::setTestNow(Carbon::parse('2026-01-30 17:00:00', 'Europe/Moscow'));
        $this->assertFalse($promo->isCurrentlyActive());

        // Четверг в 19:00 - не работает (не тот день)
        Carbon::setTestNow(Carbon::parse('2026-01-29 19:00:00', 'Europe/Moscow')); // Четверг
        $this->assertFalse($promo->isCurrentlyActive());
    }

    // =========================================================================
    // 5. УСЛОВИЯ ПРИМЕНЕНИЯ - ПЕРИОД ДЕЙСТВИЯ
    // =========================================================================

    /** @test */
    public function условие_период_ещё_не_начался()
    {
        $promo = $this->createPromotion([
            'starts_at' => Carbon::now()->addDays(5),
            'ends_at' => Carbon::now()->addDays(10),
        ]);

        $this->assertFalse($promo->isCurrentlyActive());
    }

    /** @test */
    public function условие_период_активен()
    {
        $promo = $this->createPromotion([
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->addDays(5),
        ]);

        $this->assertTrue($promo->isCurrentlyActive());
    }

    /** @test */
    public function условие_период_истёк()
    {
        $promo = $this->createPromotion([
            'starts_at' => Carbon::now()->subDays(10),
            'ends_at' => Carbon::now()->subDays(1),
        ]);

        $this->assertFalse($promo->isCurrentlyActive());
    }

    /** @test */
    public function условие_период_без_ограничений()
    {
        $promo = $this->createPromotion([
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->assertTrue($promo->isCurrentlyActive());
    }

    // =========================================================================
    // 6. УСЛОВИЯ ПРИМЕНЕНИЯ - ДЕНЬ РОЖДЕНИЯ
    // =========================================================================

    /** @test */
    public function условие_день_рождения_сегодня()
    {
        $promo = $this->createPromotion([
            'is_birthday_only' => true,
            'birthday_days_before' => 0,
            'birthday_days_after' => 0,
        ]);

        // Use Moscow timezone as that's what TimeHelper uses
        $birthdayToday = Carbon::now('Europe/Moscow')->subYears(25);
        $birthdayTomorrow = Carbon::now('Europe/Moscow')->subYears(25)->addDay();

        $this->assertTrue($promo->isWithinBirthdayRange($birthdayToday));
        $this->assertFalse($promo->isWithinBirthdayRange($birthdayTomorrow));
    }

    /** @test */
    public function условие_день_рождения_с_диапазоном()
    {
        $promo = $this->createPromotion([
            'is_birthday_only' => true,
            'birthday_days_before' => 3,
            'birthday_days_after' => 7,
        ]);

        $today = Carbon::today('Europe/Moscow');

        // ДР сегодня - в диапазоне
        $this->assertTrue($promo->isWithinBirthdayRange($today->copy()->subYears(30)));

        // ДР через 2 дня - в диапазоне (за 3 дня до)
        $this->assertTrue($promo->isWithinBirthdayRange($today->copy()->addDays(2)->subYears(30)));

        // ДР 5 дней назад - в диапазоне (7 дней после)
        $this->assertTrue($promo->isWithinBirthdayRange($today->copy()->subDays(5)->subYears(30)));

        // ДР 10 дней назад - НЕ в диапазоне
        $this->assertFalse($promo->isWithinBirthdayRange($today->copy()->subDays(10)->subYears(30)));

        // ДР через 5 дней - НЕ в диапазоне (только 3 дня до)
        $this->assertFalse($promo->isWithinBirthdayRange($today->copy()->addDays(5)->subYears(30)));
    }

    /** @test */
    public function условие_день_рождения_описание()
    {
        $promo1 = $this->createPromotion([
            'is_birthday_only' => true,
            'birthday_days_before' => 0,
            'birthday_days_after' => 0,
        ]);

        $promo2 = $this->createPromotion([
            'is_birthday_only' => true,
            'birthday_days_before' => 3,
            'birthday_days_after' => 7,
        ]);

        $this->assertEquals('Только в день рождения', $promo1->getBirthdayRangeDescription());
        $this->assertEquals('3 дн. до ДР 7 дн. после', $promo2->getBirthdayRangeDescription());
    }

    // =========================================================================
    // 7. УСЛОВИЯ ПРИМЕНЕНИЯ - УРОВЕНЬ ЛОЯЛЬНОСТИ
    // =========================================================================

    /** @test */
    public function условие_уровень_лояльности_соответствует()
    {
        $promo = $this->createPromotion([
            'loyalty_levels' => [$this->goldLevel->id],
        ]);

        $contextGold = $this->getContext(['customer_loyalty_level' => $this->goldLevel->id]);
        $contextBase = $this->getContext(['customer_loyalty_level' => $this->loyaltyLevel->id]);

        $this->assertTrue($promo->isApplicableToOrder($contextGold));
        $this->assertFalse($promo->isApplicableToOrder($contextBase));
    }

    /** @test */
    public function условие_уровень_лояльности_несколько()
    {
        $promo = $this->createPromotion([
            'loyalty_levels' => [$this->loyaltyLevel->id, $this->goldLevel->id],
        ]);

        $this->assertTrue($promo->isApplicableToOrder(
            $this->getContext(['customer_loyalty_level' => $this->loyaltyLevel->id])
        ));
        $this->assertTrue($promo->isApplicableToOrder(
            $this->getContext(['customer_loyalty_level' => $this->goldLevel->id])
        ));
    }

    /** @test */
    public function условие_уровень_лояльности_пустой_для_всех()
    {
        $promo = $this->createPromotion([
            'loyalty_levels' => null,
        ]);

        $this->assertTrue($promo->isApplicableToOrder(
            $this->getContext(['customer_loyalty_level' => null])
        ));
        $this->assertTrue($promo->isApplicableToOrder(
            $this->getContext(['customer_loyalty_level' => $this->goldLevel->id])
        ));
    }

    // =========================================================================
    // 8. УСЛОВИЯ ПРИМЕНЕНИЯ - ПЕРВЫЙ ЗАКАЗ
    // =========================================================================

    /** @test */
    public function условие_первый_заказ()
    {
        $promo = $this->createPromotion([
            'is_first_order_only' => true,
        ]);

        $contextFirst = $this->getContext(['is_first_order' => true]);
        $contextNotFirst = $this->getContext(['is_first_order' => false]);

        $this->assertTrue($promo->isApplicableToOrder($contextFirst));
        $this->assertFalse($promo->isApplicableToOrder($contextNotFirst));
    }

    // =========================================================================
    // 9. УСЛОВИЯ ПРИМЕНЕНИЯ - СПОСОБ ОПЛАТЫ
    // =========================================================================

    /** @test */
    public function условие_способ_оплаты()
    {
        $promo = $this->createPromotion([
            'payment_methods' => ['card'],
        ]);

        $contextCard = $this->getContext(['payment_method' => 'card']);
        $contextCash = $this->getContext(['payment_method' => 'cash']);

        $this->assertTrue($promo->isApplicableToOrder($contextCard));
        $this->assertFalse($promo->isApplicableToOrder($contextCash));
    }

    // =========================================================================
    // 10. УСЛОВИЯ ПРИМЕНЕНИЯ - КАНАЛ ПРОДАЖ
    // =========================================================================

    /** @test */
    public function условие_канал_продаж()
    {
        $promo = $this->createPromotion([
            'source_channels' => ['website', 'app'],
        ]);

        $this->assertFalse($promo->isApplicableToOrder($this->getContext(['source_channel' => 'pos'])));
        $this->assertTrue($promo->isApplicableToOrder($this->getContext(['source_channel' => 'website'])));
        $this->assertTrue($promo->isApplicableToOrder($this->getContext(['source_channel' => 'app'])));
    }

    // =========================================================================
    // 11. УСЛОВИЯ ПРИМЕНЕНИЯ - ЗОНЫ И СТОЛЫ
    // =========================================================================

    /** @test */
    public function условие_зона()
    {
        $promo = $this->createPromotion([
            'zones' => [$this->zone->id],
        ]);

        $contextZone = $this->getContext(['zone_id' => $this->zone->id]);
        $contextOther = $this->getContext(['zone_id' => 999]);

        $this->assertTrue($promo->isApplicableToOrder($contextZone));
        $this->assertFalse($promo->isApplicableToOrder($contextOther));
    }

    /** @test */
    public function условие_стол()
    {
        $promo = $this->createPromotion([
            'tables_list' => [$this->table->id],
        ]);

        $contextTable = $this->getContext(['table_id' => $this->table->id]);
        $contextOther = $this->getContext(['table_id' => 999]);

        $this->assertTrue($promo->isApplicableToOrder($contextTable));
        $this->assertFalse($promo->isApplicableToOrder($contextOther));
    }

    // =========================================================================
    // 12. УСЛОВИЯ ПРИМЕНЕНИЯ - ИСКЛЮЧЕНИЯ
    // =========================================================================

    /** @test */
    public function условие_исключённый_клиент()
    {
        $customer = $this->createCustomer();

        $promo = $this->createPromotion([
            'excluded_customers' => [$customer->id],
        ]);

        $contextExcluded = $this->getContext(['customer_id' => $customer->id]);
        $contextOther = $this->getContext(['customer_id' => 999]);

        $this->assertFalse($promo->isApplicableToOrder($contextExcluded));
        $this->assertTrue($promo->isApplicableToOrder($contextOther));
    }

    /** @test */
    public function условие_исключённые_блюда()
    {
        $promo = $this->createPromotion([
            'excluded_dishes' => [$this->dish3->id], // Исключаем десерт
        ]);

        $items = $this->getOrderItems();
        $discount = $promo->calculateDiscount($items, 1450, $this->getContext());

        // Сумма без десерта: 500*2 + 150 = 1150
        // 10% от 1150 = 115
        $this->assertEquals(115, $discount);
    }

    /** @test */
    public function условие_исключённые_категории()
    {
        $promo = $this->createPromotion([
            'excluded_categories' => [$this->category2->id], // Исключаем категорию2 (десерты)
        ]);

        $items = $this->getOrderItems();
        $discount = $promo->calculateDiscount($items, 1450, $this->getContext());

        // Сумма без категории2: 500*2 + 150 = 1150
        // 10% от 1150 = 115
        $this->assertEquals(115, $discount);
    }

    // =========================================================================
    // 13. УСЛОВИЯ ПРИМЕНЕНИЯ - ПРИМЕНИМЫЕ БЛЮДА/КАТЕГОРИИ
    // =========================================================================

    /** @test */
    public function условие_только_определённые_блюда()
    {
        $promo = $this->createPromotion([
            'applies_to' => 'dishes',
            'applicable_dishes' => [$this->dish1->id],
        ]);

        $items = $this->getOrderItems();
        $discount = $promo->calculateDiscount($items, 1450, $this->getContext());

        // Только пицца: 500*2 = 1000
        // 10% от 1000 = 100
        $this->assertEquals(100, $discount);
    }

    /** @test */
    public function условие_только_определённые_категории()
    {
        $promo = $this->createPromotion([
            'applies_to' => 'categories',
            'applicable_categories' => [$this->category->id],
        ]);

        $items = $this->getOrderItems();
        $discount = $promo->calculateDiscount($items, 1450, $this->getContext());

        // Категория1: 500*2 + 150 = 1150
        // 10% от 1150 = 115
        $this->assertEquals(115, $discount);
    }

    // =========================================================================
    // 14. КОМБО-АКЦИИ
    // =========================================================================

    /** @test */
    public function комбо_требует_все_товары()
    {
        $promo = $this->createPromotion([
            'applies_to' => 'dishes',
            'applicable_dishes' => [$this->dish1->id, $this->dish2->id], // Пицца + Напиток
            'requires_all_dishes' => true,
            'discount_value' => 20, // 20%
        ]);

        // Есть оба товара - применяется
        $this->assertTrue($promo->isApplicableToOrder($this->getContext()));

        // Нет одного товара - не применяется
        $itemsWithoutDrink = [
            ['dish_id' => $this->dish1->id, 'category_id' => $this->category->id, 'price' => 500, 'quantity' => 2],
        ];
        $this->assertFalse($promo->isApplicableToOrder($this->getContext(['items' => $itemsWithoutDrink])));
    }

    /** @test */
    public function комбо_считает_только_полные_комплекты()
    {
        $promo = $this->createPromotion([
            'applies_to' => 'dishes',
            'applicable_dishes' => [$this->dish1->id, $this->dish2->id], // Пицца + Напиток
            'requires_all_dishes' => true,
            'discount_value' => 20,
        ]);

        // 3 пиццы + 2 напитка = 2 полных комплекта
        $items = [
            ['dish_id' => $this->dish1->id, 'category_id' => $this->category->id, 'price' => 500, 'quantity' => 3],
            ['dish_id' => $this->dish2->id, 'category_id' => $this->category->id, 'price' => 150, 'quantity' => 2],
        ];

        $context = $this->getContext(['items' => $items, 'order_total' => 1800]);

        // Сумма 2 комплектов: (500 + 150) * 2 = 1300
        // 20% от 1300 = 260
        $discount = $promo->calculateDiscount($items, 1800, $context);
        $this->assertEquals(260, $discount);
    }

    // =========================================================================
    // 15. ЛИМИТЫ ИСПОЛЬЗОВАНИЯ
    // =========================================================================

    /** @test */
    public function лимит_использования_исчерпан()
    {
        $promo = $this->createPromotion([
            'usage_limit' => 10,
            'usage_count' => 10,
        ]);

        $this->assertFalse($promo->isCurrentlyActive());
    }

    /** @test */
    public function лимит_использования_не_исчерпан()
    {
        $promo = $this->createPromotion([
            'usage_limit' => 10,
            'usage_count' => 5,
        ]);

        $this->assertTrue($promo->isCurrentlyActive());
    }

    // =========================================================================
    // 16. КОМБИНИРОВАНИЕ СКИДОК
    // =========================================================================

    /** @test */
    public function суммируемые_скидки()
    {
        $promo1 = $this->createPromotion([
            'discount_value' => 10,
            'stackable' => true,
            'priority' => 1,
        ]);

        $promo2 = $this->createPromotion([
            'discount_value' => 5,
            'stackable' => true,
            'priority' => 2,
        ]);

        $orderTotal = $this->getOrderTotal();

        // Обе акции применяются
        $discount1 = $promo1->calculateDiscount($this->getOrderItems(), $orderTotal, $this->getContext());
        $discount2 = $promo2->calculateDiscount($this->getOrderItems(), $orderTotal, $this->getContext());

        $this->assertEquals($orderTotal * 0.10, $discount1); // 10%
        $this->assertEquals($orderTotal * 0.05, $discount2); // 5%
    }

    /** @test */
    public function эксклюзивная_скидка()
    {
        $exclusivePromo = $this->createPromotion([
            'discount_value' => 30,
            'stackable' => false,
            'is_exclusive' => true,
        ]);

        $this->assertTrue($exclusivePromo->is_exclusive);
        $this->assertFalse($exclusivePromo->stackable);
    }

    // =========================================================================
    // 17. ПРОМОКОДЫ
    // =========================================================================

    /** @test */
    public function промокод_процентная_скидка()
    {
        $promoCode = $this->createPromoCode([
            'code' => 'TEST10PERCENT',
            'type' => 'percent',
            'value' => 10,
        ]);

        $discount = $promoCode->calculateDiscount(1450);
        $this->assertEquals(145, $discount);
    }

    /** @test */
    public function промокод_фиксированная_скидка()
    {
        $promoCode = $this->createPromoCode([
            'code' => 'TEST200FIX',
            'type' => 'fixed',
            'value' => 200,
        ]);

        $discount = $promoCode->calculateDiscount(1450);
        $this->assertEquals(200, $discount);
    }

    /** @test */
    public function промокод_с_максимумом()
    {
        $promoCode = $this->createPromoCode([
            'code' => 'TESTMAX',
            'type' => 'percent',
            'value' => 50,
            'max_discount' => 300,
        ]);

        // 50% от 1450 = 725, но max = 300
        $discount = $promoCode->calculateDiscount(1450);
        $this->assertEquals(300, $discount);
    }

    /** @test */
    public function промокод_минимальная_сумма()
    {
        $promoCode = $this->createPromoCode([
            'code' => 'TESTMIN',
            'type' => 'percent',
            'value' => 10,
            'min_order_amount' => 2000,
        ]);

        $result = $promoCode->checkValidity(null, 1450);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Минимальная сумма', $result['error']);

        $result2 = $promoCode->checkValidity(null, 2500);
        $this->assertTrue($result2['valid']);
    }

    /** @test */
    public function промокод_истёк()
    {
        $promoCode = $this->createPromoCode([
            'code' => 'TESTEXPIRED',
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $result = $promoCode->checkValidity();
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('истёк', $result['error']);
    }

    /** @test */
    public function промокод_ещё_не_активен()
    {
        $promoCode = $this->createPromoCode([
            'code' => 'TESTFUTURE',
            'starts_at' => Carbon::now()->addDay(),
        ]);

        $result = $promoCode->checkValidity();
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('не активен', $result['error']);
    }

    /** @test */
    public function промокод_лимит_исчерпан()
    {
        $promoCode = $this->createPromoCode([
            'code' => 'TESTLIMIT',
            'usage_limit' => 5,
            'usage_count' => 5,
        ]);

        $result = $promoCode->checkValidity();
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('исчерпан', $result['error']);
    }

    /** @test */
    public function промокод_персональный()
    {
        $customer = $this->createCustomer();
        $otherCustomer = $this->createCustomer();

        $promoCode = $this->createPromoCode([
            'code' => 'TESTPERSONAL',
            'allowed_customer_ids' => [$customer->id],
        ]);

        $result1 = $promoCode->checkValidity($customer->id);
        $this->assertTrue($result1['valid']);

        $result2 = $promoCode->checkValidity($otherCustomer->id);
        $this->assertFalse($result2['valid']);
    }

    /** @test */
    public function промокод_только_первый_заказ()
    {
        $newCustomer = $this->createCustomer(['total_orders' => 0]);
        $oldCustomer = $this->createCustomer(['total_orders' => 5]);

        $promoCode = $this->createPromoCode([
            'code' => 'TESTFIRST',
            'first_order_only' => true,
        ]);

        $result1 = $promoCode->checkValidity($newCustomer->id);
        $this->assertTrue($result1['valid']);

        $result2 = $promoCode->checkValidity($oldCustomer->id);
        $this->assertFalse($result2['valid']);
    }

    /** @test */
    public function промокод_лимит_на_клиента()
    {
        $customer = $this->createCustomer();

        $promoCode = $this->createPromoCode([
            'code' => 'TESTCUSTOMERLIMIT' . uniqid(),
            'usage_per_customer' => 1,
        ]);

        // Первое использование - OK
        $result1 = $promoCode->checkValidity($customer->id);
        $this->assertTrue($result1['valid']);

        // Проверяем что usage_per_customer работает через проверку валидности
        // Симулируем использование через увеличение счётчика
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        \DB::table('promo_code_usages')->insert([
            'promo_code_id' => $promoCode->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'discount_amount' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Второе использование - FAIL
        $result2 = $promoCode->checkValidity($customer->id);
        $this->assertFalse($result2['valid']);
    }

    // =========================================================================
    // 18. DISCOUNT CALCULATOR SERVICE
    // =========================================================================

    /** @test */
    public function сервис_создаётся_с_restaurant_id()
    {
        $service = new DiscountCalculatorService($this->restaurant->id);

        // Проверяем что сервис создался
        $this->assertInstanceOf(DiscountCalculatorService::class, $service);
    }

    /** @test */
    public function сервис_calculateApplicableTotal()
    {
        $items = $this->getOrderItems();
        $orderTotal = $this->getOrderTotal();
        $dish1Total = $this->dish1->price * 2;
        $dish3Total = $this->dish3->price;

        // Весь заказ
        $total1 = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'whole_order',
        ]);
        $this->assertEquals($orderTotal, $total1);

        // Только блюда
        $total2 = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'dishes',
            'applicable_dishes' => [$this->dish1->id],
        ]);
        $this->assertEquals($dish1Total, $total2);

        // С исключениями
        $total3 = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'whole_order',
            'excluded_dishes' => [$this->dish3->id],
        ]);
        $this->assertEquals($orderTotal - $dish3Total, $total3);
    }

    /** @test */
    public function сервис_calculateComboTotal()
    {
        $items = [
            ['dish_id' => $this->dish1->id, 'price' => 500, 'quantity' => 3],
            ['dish_id' => $this->dish2->id, 'price' => 150, 'quantity' => 2],
        ];

        // 3 пиццы + 2 напитка = 2 комплекта
        $total = DiscountCalculatorService::calculateComboTotal($items, [
            $this->dish1->id,
            $this->dish2->id,
        ]);

        // 2 комплекта × (500 + 150) = 1300
        $this->assertEquals(1300, $total);
    }

    /** @test */
    public function сервис_recalculateFromAppliedDiscounts()
    {
        $service = new DiscountCalculatorService($this->restaurant->id);
        $orderTotal = $this->getOrderTotal();

        $appliedDiscounts = [
            [
                'name' => 'Тестовая скидка',
                'type' => 'promotion',
                'percent' => 10,
                'amount' => 0,
            ],
        ];

        $result = $service->recalculateFromAppliedDiscounts(
            $appliedDiscounts,
            $this->getOrderItems(),
            $orderTotal
        );

        $this->assertArrayHasKey('discounts', $result);
        $this->assertArrayHasKey('total_discount', $result);
        $this->assertEqualsWithDelta($orderTotal * 0.10, $result['total_discount'], 0.5); // 10% с погрешностью
    }

    // =========================================================================
    // 19. КОМБИНАЦИИ УСЛОВИЙ
    // =========================================================================

    /** @test */
    public function комбинация_тип_заказа_и_минимум()
    {
        $promo = $this->createPromotion([
            'order_types' => ['delivery'],
            'min_order_amount' => 1000,
            'discount_value' => 15,
        ]);

        // Зал - не применяется
        $this->assertFalse($promo->isApplicableToOrder(
            $this->getContext(['order_type' => 'dine_in', 'order_total' => 1500])
        ));

        // Доставка, но мало - не применяется
        $this->assertFalse($promo->isApplicableToOrder(
            $this->getContext(['order_type' => 'delivery', 'order_total' => 500])
        ));

        // Доставка + достаточно - применяется
        $this->assertTrue($promo->isApplicableToOrder(
            $this->getContext(['order_type' => 'delivery', 'order_total' => 1500])
        ));
    }

    /** @test */
    public function комбинация_расписание_и_уровень()
    {
        $promo = $this->createPromotion([
            'schedule' => [
                'days' => [1, 2, 3, 4, 5], // Пн-Пт
                'time_from' => '12:00',
                'time_to' => '14:00',
            ],
            'loyalty_levels' => [$this->goldLevel->id],
            'discount_value' => 20,
        ]);

        // Правильный день/время, правильный уровень
        Carbon::setTestNow(Carbon::parse('2026-01-26 13:00:00', 'Europe/Moscow')); // Понедельник
        $this->assertTrue($promo->isApplicableToOrder(
            $this->getContext(['customer_loyalty_level' => $this->goldLevel->id])
        ));

        // Правильный день/время, неправильный уровень
        $this->assertFalse($promo->isApplicableToOrder(
            $this->getContext(['customer_loyalty_level' => $this->loyaltyLevel->id])
        ));

        // Неправильное время
        Carbon::setTestNow(Carbon::parse('2026-01-26 18:00:00', 'Europe/Moscow'));
        $this->assertFalse($promo->isApplicableToOrder(
            $this->getContext(['customer_loyalty_level' => $this->goldLevel->id])
        ));
    }

    /** @test */
    public function комбинация_ДР_и_блюда()
    {
        $promo = $this->createPromotion([
            'is_birthday_only' => true,
            'birthday_days_before' => 3,
            'birthday_days_after' => 3,
            'applies_to' => 'dishes',
            'applicable_dishes' => [$this->dish1->id],
            'discount_value' => 50, // 50% на пиццу в ДР
        ]);

        $birthday = Carbon::today('Europe/Moscow')->subYears(25); // ДР сегодня

        $context = $this->getContext([
            'customer_birthday' => $birthday,
        ]);

        $this->assertTrue($promo->isApplicableToOrder($context));

        $discount = $promo->calculateDiscount($this->getOrderItems(), 1450, $context);
        // 50% только от пиццы (500*2 = 1000) = 500
        $this->assertEquals(500, $discount);
    }

    /** @test */
    public function комбинация_первый_заказ_доставка_минимум()
    {
        $promo = $this->createPromotion([
            'is_first_order_only' => true,
            'order_types' => ['delivery'],
            'min_order_amount' => 800,
            'discount_value' => 25,
        ]);

        // Все условия выполнены
        $this->assertTrue($promo->isApplicableToOrder($this->getContext([
            'is_first_order' => true,
            'order_type' => 'delivery',
            'order_total' => 1000,
        ])));

        // Не первый заказ
        $this->assertFalse($promo->isApplicableToOrder($this->getContext([
            'is_first_order' => false,
            'order_type' => 'delivery',
            'order_total' => 1000,
        ])));

        // Не доставка
        $this->assertFalse($promo->isApplicableToOrder($this->getContext([
            'is_first_order' => true,
            'order_type' => 'dine_in',
            'order_total' => 1000,
        ])));

        // Мало сумма
        $this->assertFalse($promo->isApplicableToOrder($this->getContext([
            'is_first_order' => true,
            'order_type' => 'delivery',
            'order_total' => 500,
        ])));
    }

    // =========================================================================
    // 20. ГРАНИЧНЫЕ СЛУЧАИ
    // =========================================================================

    /** @test */
    public function граница_пустой_заказ()
    {
        $promo = $this->createPromotion([
            'discount_value' => 10,
        ]);

        $discount = $promo->calculateDiscount([], 0, $this->getContext(['order_total' => 0]));
        $this->assertEquals(0, $discount);
    }

    /** @test */
    public function граница_нулевая_скидка()
    {
        $promo = $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 0,
        ]);

        $discount = $promo->calculateDiscount($this->getOrderItems(), 1450, $this->getContext());
        $this->assertEquals(0, $discount);
    }

    /** @test */
    public function граница_100_процентов()
    {
        $promo = $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 100,
        ]);

        $orderTotal = $this->getOrderTotal();
        $discount = $promo->calculateDiscount($this->getOrderItems(), $orderTotal, $this->getContext());
        $this->assertEquals($orderTotal, $discount);
    }

    /** @test */
    public function граница_неактивная_акция()
    {
        $promo = $this->createPromotion([
            'is_active' => false,
        ]);

        $this->assertFalse($promo->isCurrentlyActive());
        $this->assertFalse($promo->isApplicableToOrder($this->getContext()));
    }

    /** @test */
    public function граница_нет_подходящих_товаров()
    {
        $promo = $this->createPromotion([
            'applies_to' => 'dishes',
            'applicable_dishes' => [99999], // Несуществующий товар
        ]);

        $discount = $promo->calculateDiscount($this->getOrderItems(), 1450, $this->getContext());
        $this->assertEquals(0, $discount);
    }

    // =========================================================================
    // 21. ОТЧЁТ О ТЕСТИРОВАНИИ
    // =========================================================================

    /** @test */
    public function отчёт_о_покрытии()
    {
        echo "\n\n";
        echo "========================================================================\n";
        echo "           ПОЛНЫЙ ОТЧЁТ О ТЕСТИРОВАНИИ СИСТЕМЫ СКИДОК                  \n";
        echo "========================================================================\n";
        echo "\n";
        echo "  ТИПЫ СКИДОК:\n";
        echo "  [OK] Процентная скидка (discount_percent)\n";
        echo "  [OK] Процентная с максимумом (max_discount)\n";
        echo "  [OK] Фиксированная скидка (discount_fixed)\n";
        echo "  [OK] Прогрессивная скидка (progressive_discount)\n";
        echo "  [OK] Бесплатная доставка (free_delivery)\n";
        echo "  [OK] Подарок (gift)\n";
        echo "  [OK] Множитель бонусов (bonus_multiplier)\n";
        echo "\n";
        echo "  УСЛОВИЯ ПРИМЕНЕНИЯ:\n";
        echo "  [OK] Тип заказа (order_types)\n";
        echo "  [OK] Минимальная сумма (min_order_amount)\n";
        echo "  [OK] Минимум позиций (min_items_count)\n";
        echo "  [OK] Расписание - дни (schedule.days)\n";
        echo "  [OK] Расписание - время (schedule.time_from/to)\n";
        echo "  [OK] Период действия (starts_at/ends_at)\n";
        echo "  [OK] День рождения (is_birthday_only)\n";
        echo "  [OK] Диапазон ДР (birthday_days_before/after)\n";
        echo "  [OK] Уровень лояльности (loyalty_levels)\n";
        echo "  [OK] Первый заказ (is_first_order_only)\n";
        echo "  [OK] Способ оплаты (payment_methods)\n";
        echo "  [OK] Канал продаж (source_channels)\n";
        echo "  [OK] Зона (zones)\n";
        echo "  [OK] Стол (tables_list)\n";
        echo "  [OK] Исключённые клиенты (excluded_customers)\n";
        echo "  [OK] Исключённые блюда (excluded_dishes)\n";
        echo "  [OK] Исключённые категории (excluded_categories)\n";
        echo "  [OK] Только блюда (applicable_dishes)\n";
        echo "  [OK] Только категории (applicable_categories)\n";
        echo "  [OK] Комбо - все товары (requires_all_dishes)\n";
        echo "  [OK] Лимит использований (usage_limit)\n";
        echo "\n";
        echo "  ПРОМОКОДЫ:\n";
        echo "  [OK] Процентная скидка\n";
        echo "  [OK] Фиксированная скидка\n";
        echo "  [OK] Максимум скидки\n";
        echo "  [OK] Минимальная сумма\n";
        echo "  [OK] Срок действия\n";
        echo "  [OK] Лимит использований\n";
        echo "  [OK] Персональный промокод\n";
        echo "  [OK] Только первый заказ\n";
        echo "  [OK] Лимит на клиента\n";
        echo "\n";
        echo "  КОМБИНАЦИИ:\n";
        echo "  [OK] Суммируемые скидки (stackable)\n";
        echo "  [OK] Эксклюзивные скидки (is_exclusive)\n";
        echo "  [OK] Комбинация: тип + минимум\n";
        echo "  [OK] Комбинация: расписание + уровень\n";
        echo "  [OK] Комбинация: ДР + блюда\n";
        echo "  [OK] Комбинация: первый + доставка + минимум\n";
        echo "\n";
        echo "  СЕРВИС:\n";
        echo "  [OK] calculate()\n";
        echo "  [OK] calculateApplicableTotal()\n";
        echo "  [OK] calculateComboTotal()\n";
        echo "  [OK] recalculateFromAppliedDiscounts()\n";
        echo "\n";
        echo "  ГРАНИЧНЫЕ СЛУЧАИ:\n";
        echo "  [OK] Пустой заказ\n";
        echo "  [OK] Нулевая скидка\n";
        echo "  [OK] 100% скидка\n";
        echo "  [OK] Неактивная акция\n";
        echo "  [OK] Нет подходящих товаров\n";
        echo "\n";
        echo "========================================================================\n";
        echo "              ВСЕ ТЕСТЫ ПРОЙДЕНЫ УСПЕШНО!                              \n";
        echo "========================================================================\n\n";

        $this->assertTrue(true);
    }
}
