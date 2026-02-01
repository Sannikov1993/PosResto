<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\LoyaltyLevel;
use App\Models\LoyaltySetting;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\BonusSetting;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Services\DiscountCalculatorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Comprehensive unit tests for DiscountCalculatorService.
 *
 * Tests cover:
 *  - Loyalty level discount calculation (various levels)
 *  - Birthday bonus application
 *  - Promo code validation and application
 *  - Promotion matching (percentage, fixed, combo, free delivery, gift, bonus multiplier)
 *  - Bonus earning calculation
 *  - Edge cases: expired promotions, invalid promo codes, zero-amount orders, no customer
 *  - Multiple discounts interaction (stackable, exclusive, priority)
 *  - recalculateFromAppliedDiscounts
 *  - calculateApplicableTotal and calculateComboTotal (static helpers)
 *
 * Run: php artisan test --filter=DiscountCalculatorServiceTest
 */
class DiscountCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected DiscountCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        // Create default BonusSetting so BonusService does not fail
        BonusSetting::create([
            'restaurant_id' => $this->restaurant->id,
            'is_enabled' => true,
            'currency_name' => 'bonuses',
            'currency_symbol' => 'B',
            'earn_rate' => 5,
            'min_order_for_earn' => 0,
            'earn_rounding' => 1,
            'spend_rate' => 50,
            'min_spend_amount' => 100,
            'bonus_to_ruble' => 1,
            'expiry_days' => 0,
            'notify_before_expiry' => false,
            'notify_days_before' => 0,
            'registration_bonus' => 0,
            'birthday_bonus' => 0,
            'referral_bonus' => 0,
            'referral_friend_bonus' => 0,
        ]);

        $this->service = new DiscountCalculatorService($this->restaurant->id);
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * Create a loyalty level and attach it to a customer.
     */
    protected function createCustomerWithLevel(
        float $discountPercent = 5,
        ?float $cashbackPercent = 5,
        bool $birthdayBonus = false,
        float $birthdayDiscount = 0,
        ?string $birthDate = null,
        int $ordersCount = 1
    ): Customer {
        $level = LoyaltyLevel::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => "Level {$discountPercent}%",
            'icon' => null,
            'color' => '#000000',
            'min_total' => 0,
            'discount_percent' => $discountPercent,
            'cashback_percent' => $cashbackPercent ?? 0,
            'bonus_multiplier' => 1,
            'birthday_bonus' => $birthdayBonus,
            'birthday_discount' => $birthdayDiscount,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'restaurant_id' => $this->restaurant->id,
            'loyalty_level_id' => $level->id,
            'name' => 'Test Customer',
            'phone' => '79991234567',
            'birth_date' => $birthDate,
            'bonus_balance' => 0,
            'total_orders' => $ordersCount,
            'total_spent' => 0,
        ]);

        // Eager load the relation
        $customer->load('loyaltyLevel');

        return $customer;
    }

    /**
     * Create a basic automatic promotion.
     */
    protected function createPromotion(array $overrides = []): Promotion
    {
        return Promotion::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Promo',
            'type' => 'discount_percent',
            'applies_to' => 'whole_order',
            'discount_value' => 10,
            'is_active' => true,
            'is_automatic' => true,
            'requires_promo_code' => false,
            'stackable' => true,
            'is_exclusive' => false,
            'priority' => 1,
            'sort_order' => 1,
        ], $overrides));
    }

    /**
     * Standard order items for testing.
     */
    protected function sampleItems(): array
    {
        // Сумма: 400*2 + 200*1 = 1000
        return [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 400, 'quantity' => 2],
            ['dish_id' => 2, 'category_id' => 2, 'price' => 200, 'quantity' => 1],
        ];
    }

    // =========================================================================
    // 1. Loyalty level discount calculation
    // =========================================================================

    public function test_no_discount_when_no_customer(): void
    {
        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => null,
        ]);

        $this->assertEquals(0, $result['total_discount']);
        $this->assertEquals(1000, $result['final_total']);
        $this->assertEmpty($result['discounts']);
        $this->assertNull($result['customer']);
    }

    public function test_loyalty_level_discount_5_percent(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 5);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1300,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        // 1300 * 5% = 65
        $levelDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'level');
        $this->assertCount(1, $levelDiscounts);

        $levelDiscount = reset($levelDiscounts);
        $this->assertEquals(65, $levelDiscount['amount']);
        $this->assertEquals(5, $levelDiscount['percent']);
    }

    public function test_loyalty_level_discount_10_percent(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 10);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1300,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        // 1300 * 10% = 130
        $levelDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'level');
        $levelDiscount = reset($levelDiscounts);
        $this->assertEquals(130, $levelDiscount['amount']);
        $this->assertEquals(1300 - 130, $result['final_total']);
    }

    public function test_loyalty_level_discount_zero_percent(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 0);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1300,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $levelDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'level');
        $this->assertCount(0, $levelDiscounts);
        $this->assertEquals(1300, $result['order_total']);
    }

    public function test_loyalty_discount_name_includes_level_name(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 7);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $levelDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'level');
        $levelDiscount = reset($levelDiscounts);
        $this->assertStringContainsString('Level 7%', $levelDiscount['name']);
    }

    // =========================================================================
    // 2. Birthday bonus application
    // =========================================================================

    public function test_birthday_discount_applied_on_birthday(): void
    {
        $today = Carbon::today();

        // Create loyalty settings for birthday days window
        LoyaltySetting::set('birthday_days_before', 7, $this->restaurant->id);
        LoyaltySetting::set('birthday_days_after', 7, $this->restaurant->id);

        $customer = $this->createCustomerWithLevel(
            discountPercent: 5,
            birthdayBonus: true,
            birthdayDiscount: 15,
            birthDate: $today->format('Y-m-d'),
        );

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $birthdayDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'birthday');
        $this->assertCount(1, $birthdayDiscounts);

        $bd = reset($birthdayDiscounts);
        // 1000 * 15% = 150
        $this->assertEquals(150, $bd['amount']);
        $this->assertEquals(15, $bd['percent']);
    }

    public function test_birthday_discount_applied_within_days_before(): void
    {
        $today = Carbon::today();
        $birthdayInFuture = $today->copy()->addDays(3);

        LoyaltySetting::set('birthday_days_before', 7, $this->restaurant->id);
        LoyaltySetting::set('birthday_days_after', 7, $this->restaurant->id);

        $customer = $this->createCustomerWithLevel(
            discountPercent: 5,
            birthdayBonus: true,
            birthdayDiscount: 10,
            birthDate: $birthdayInFuture->format('Y-m-d'),
        );

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $birthdayDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'birthday');
        $this->assertCount(1, $birthdayDiscounts);
    }

    public function test_birthday_discount_applied_within_days_after(): void
    {
        $today = Carbon::today();
        $birthdayInPast = $today->copy()->subDays(3);

        LoyaltySetting::set('birthday_days_before', 7, $this->restaurant->id);
        LoyaltySetting::set('birthday_days_after', 7, $this->restaurant->id);

        $customer = $this->createCustomerWithLevel(
            discountPercent: 5,
            birthdayBonus: true,
            birthdayDiscount: 10,
            birthDate: $birthdayInPast->format('Y-m-d'),
        );

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $birthdayDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'birthday');
        $this->assertCount(1, $birthdayDiscounts);
    }

    public function test_birthday_discount_not_applied_outside_window(): void
    {
        $today = Carbon::today();
        $birthdayFarAway = $today->copy()->addDays(30);

        LoyaltySetting::set('birthday_days_before', 7, $this->restaurant->id);
        LoyaltySetting::set('birthday_days_after', 7, $this->restaurant->id);

        $customer = $this->createCustomerWithLevel(
            discountPercent: 5,
            birthdayBonus: true,
            birthdayDiscount: 10,
            birthDate: $birthdayFarAway->format('Y-m-d'),
        );

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $birthdayDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'birthday');
        $this->assertCount(0, $birthdayDiscounts);
    }

    public function test_birthday_discount_not_applied_without_birthday_bonus_flag(): void
    {
        $today = Carbon::today();

        LoyaltySetting::set('birthday_days_before', 7, $this->restaurant->id);
        LoyaltySetting::set('birthday_days_after', 7, $this->restaurant->id);

        // birthday_bonus = false on loyalty level
        $customer = $this->createCustomerWithLevel(
            discountPercent: 5,
            birthdayBonus: false,
            birthdayDiscount: 10,
            birthDate: $today->format('Y-m-d'),
        );

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $birthdayDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'birthday');
        $this->assertCount(0, $birthdayDiscounts);
    }

    public function test_birthday_discount_not_applied_when_no_birth_date(): void
    {
        LoyaltySetting::set('birthday_days_before', 7, $this->restaurant->id);
        LoyaltySetting::set('birthday_days_after', 7, $this->restaurant->id);

        $customer = $this->createCustomerWithLevel(
            discountPercent: 5,
            birthdayBonus: true,
            birthdayDiscount: 10,
            birthDate: null,
        );

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $birthdayDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'birthday');
        $this->assertCount(0, $birthdayDiscounts);
    }

    public function test_birthday_and_loyalty_discounts_stack(): void
    {
        $today = Carbon::today();

        LoyaltySetting::set('birthday_days_before', 7, $this->restaurant->id);
        LoyaltySetting::set('birthday_days_after', 7, $this->restaurant->id);

        $customer = $this->createCustomerWithLevel(
            discountPercent: 5,
            birthdayBonus: true,
            birthdayDiscount: 10,
            birthDate: $today->format('Y-m-d'),
        );

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        // Level: 1000 * 5% = 50
        // Birthday: 1000 * 10% = 100
        // Total discount = 150
        $this->assertEquals(150, $result['total_discount']);
        $this->assertEquals(850, $result['final_total']);
    }

    // =========================================================================
    // 3. Automatic promotion matching: discount_percent
    // =========================================================================

    public function test_automatic_percent_promotion_applied(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(1, $promoDiscounts);

        $promo = reset($promoDiscounts);
        // 1000 * 10% = 100
        $this->assertEquals(100, $promo['amount']);
        $this->assertEquals(1000 - 100, $result['final_total']);
    }

    public function test_percent_promotion_respects_max_discount(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 50,
            'max_discount' => 200,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        // 1000 * 50% = 500, but max_discount = 200
        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $promo = reset($promoDiscounts);
        $this->assertEquals(200, $promo['amount']);
    }

    // =========================================================================
    // 4. Automatic promotion matching: discount_fixed
    // =========================================================================

    public function test_fixed_discount_promotion_applied(): void
    {
        $this->createPromotion([
            'type' => 'discount_fixed',
            'discount_value' => 150,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $promo = reset($promoDiscounts);
        $this->assertEquals(150, $promo['amount']);
        $this->assertEquals(850, $result['final_total']);
    }

    public function test_fixed_discount_capped_at_remaining_total(): void
    {
        $this->createPromotion([
            'type' => 'discount_fixed',
            'discount_value' => 5000,
        ]);

        $result = $this->service->calculate([
            'items' => [['dish_id' => 1, 'category_id' => 1, 'price' => 200, 'quantity' => 1]],
            'subtotal' => 200,
            'order_type' => 'dine_in',
        ]);

        // Fixed discount 5000 > remaining total 200, capped at 200
        $this->assertLessThanOrEqual(200, $result['total_discount']);
        $this->assertGreaterThanOrEqual(0, $result['final_total']);
    }

    // =========================================================================
    // 5. Automatic promotion: free_delivery
    // =========================================================================

    public function test_free_delivery_promotion(): void
    {
        $this->createPromotion([
            'type' => 'free_delivery',
            'discount_value' => 0,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $this->assertTrue($result['free_delivery']);
        // free_delivery does not reduce order total
        $this->assertEquals(1000, $result['final_total']);
    }

    // =========================================================================
    // 6. Automatic promotion: gift
    // =========================================================================

    public function test_gift_promotion_adds_gift_item(): void
    {
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Free Dessert',
            'price' => 300,
        ]);

        $this->createPromotion([
            'type' => 'gift',
            'discount_value' => 0,
            'gift_dish_id' => $dish->id,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $this->assertNotEmpty($result['gift_items']);
        $this->assertEquals($dish->id, $result['gift_items'][0]['dish_id']);
        $this->assertEquals('Free Dessert', $result['gift_items'][0]['name']);
        // Gift does not change monetary discount
        $this->assertEquals(1000, $result['final_total']);
    }

    // =========================================================================
    // 7. Automatic promotion: bonus_multiplier
    // =========================================================================

    public function test_bonus_multiplier_promotion_affects_bonus_earned(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 0, cashbackPercent: 5);

        $this->createPromotion([
            'type' => 'bonus_multiplier',
            'discount_value' => 3, // x3 multiplier
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        // bonus_multiplier promo does NOT reduce total_discount
        $this->assertEquals(0, $result['total_discount']);
        // Bonus earned should be > 0 due to multiplier
        $this->assertGreaterThan(0, $result['bonus_earned']);
    }

    // =========================================================================
    // 8. Promotion conditions: min_order_amount
    // =========================================================================

    public function test_promotion_not_applied_below_min_order_amount(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'min_order_amount' => 2000,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        // subtotal 1000 < min_order_amount 2000, promo should not apply
        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(0, $promoDiscounts);
    }

    public function test_promotion_applied_at_min_order_amount(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'min_order_amount' => 1000,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(1, $promoDiscounts);
    }

    // =========================================================================
    // 9. Expired promotions
    // =========================================================================

    public function test_expired_promotion_not_applied(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'starts_at' => Carbon::now()->subDays(30),
            'ends_at' => Carbon::now()->subDays(1), // ended yesterday
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(0, $promoDiscounts);
    }

    public function test_future_promotion_not_applied(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'starts_at' => Carbon::now()->addDays(5),
            'ends_at' => Carbon::now()->addDays(30),
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(0, $promoDiscounts);
    }

    public function test_inactive_promotion_not_applied(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'is_active' => false,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(0, $promoDiscounts);
    }

    // =========================================================================
    // 10. Promo code validation and application
    // =========================================================================

    public function test_valid_promo_code_applied(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 20,
            'code' => 'SAVE20',
            'is_automatic' => false,
            'requires_promo_code' => true,
            'is_active' => true,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'promo_code' => 'save20', // lowercase to test normalization
        ]);

        $promoCodeDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promo_code');
        $this->assertCount(1, $promoCodeDiscounts);

        $pc = reset($promoCodeDiscounts);
        // 1000 * 20% = 200
        $this->assertEquals(200, $pc['amount']);
    }

    public function test_invalid_promo_code_ignored(): void
    {
        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'promo_code' => 'NONEXISTENT',
        ]);

        $promoCodeDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promo_code');
        $this->assertCount(0, $promoCodeDiscounts);
        $this->assertEquals(1000, $result['final_total']);
    }

    public function test_promo_code_inactive_not_applied(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 20,
            'code' => 'INACTIVE',
            'is_automatic' => false,
            'requires_promo_code' => true,
            'is_active' => false,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'promo_code' => 'INACTIVE',
        ]);

        $promoCodeDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promo_code');
        $this->assertCount(0, $promoCodeDiscounts);
    }

    public function test_promo_code_expired_not_applied(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 20,
            'code' => 'EXPIRED',
            'is_automatic' => false,
            'requires_promo_code' => true,
            'is_active' => true,
            'starts_at' => Carbon::now()->subDays(30),
            'ends_at' => Carbon::now()->subDay(),
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'promo_code' => 'EXPIRED',
        ]);

        $promoCodeDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promo_code');
        $this->assertCount(0, $promoCodeDiscounts);
    }

    public function test_promo_code_usage_limit_exhausted(): void
    {
        $promo = $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 20,
            'code' => 'LIMITED',
            'is_automatic' => false,
            'requires_promo_code' => true,
            'is_active' => true,
            'usage_limit' => 2,
            'usage_count' => 2, // already used up
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'promo_code' => 'LIMITED',
        ]);

        $promoCodeDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promo_code');
        $this->assertCount(0, $promoCodeDiscounts);
    }

    public function test_promo_code_below_min_order_not_applied(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 20,
            'code' => 'MINORDER',
            'is_automatic' => false,
            'requires_promo_code' => true,
            'is_active' => true,
            'min_order_amount' => 5000,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'promo_code' => 'MINORDER',
        ]);

        $promoCodeDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promo_code');
        $this->assertCount(0, $promoCodeDiscounts);
    }

    public function test_promo_code_fixed_discount(): void
    {
        $this->createPromotion([
            'type' => 'discount_fixed',
            'discount_value' => 300,
            'code' => 'FIX300',
            'is_automatic' => false,
            'requires_promo_code' => true,
            'is_active' => true,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'promo_code' => 'FIX300',
        ]);

        $promoCodeDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promo_code');
        $this->assertCount(1, $promoCodeDiscounts);
        $pc = reset($promoCodeDiscounts);
        $this->assertEquals(300, $pc['amount']);
    }

    public function test_promo_code_gift_type(): void
    {
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Gift Item',
            'price' => 250,
        ]);

        $this->createPromotion([
            'type' => 'gift',
            'discount_value' => 0,
            'code' => 'GIFT',
            'is_automatic' => false,
            'requires_promo_code' => true,
            'is_active' => true,
            'gift_dish_id' => $dish->id,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'promo_code' => 'GIFT',
        ]);

        // Gift promo code should not reduce total
        $this->assertNotEmpty($result['gift_items']);
        $this->assertEquals($dish->id, $result['gift_items'][0]['dish_id']);
    }

    // =========================================================================
    // 11. Multiple discounts interaction (stackable, exclusive, priority)
    // =========================================================================

    public function test_stackable_promotions_combine(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 5,
            'name' => 'Promo A',
            'stackable' => true,
            'is_exclusive' => false,
            'priority' => 2,
            'sort_order' => 1,
        ]);

        $this->createPromotion([
            'type' => 'discount_fixed',
            'discount_value' => 100,
            'name' => 'Promo B',
            'stackable' => true,
            'is_exclusive' => false,
            'priority' => 1,
            'sort_order' => 2,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(2, $promoDiscounts);

        // Total discount should include both
        $this->assertGreaterThan(0, $result['total_discount']);
    }

    public function test_non_stackable_promotion_stops_chain(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 5,
            'name' => 'First Non-Stackable',
            'stackable' => false,
            'is_exclusive' => false,
            'priority' => 10,
            'sort_order' => 1,
        ]);

        $this->createPromotion([
            'type' => 'discount_fixed',
            'discount_value' => 100,
            'name' => 'Second Promo',
            'stackable' => true,
            'is_exclusive' => false,
            'priority' => 1,
            'sort_order' => 2,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        // Only the first non-stackable should apply (break; after it)
        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(1, $promoDiscounts);

        $promo = reset($promoDiscounts);
        $this->assertEquals('First Non-Stackable', $promo['name']);
    }

    public function test_exclusive_promotion_blocks_non_stackable(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'name' => 'Exclusive Promo',
            'stackable' => true,
            'is_exclusive' => true,
            'priority' => 10,
            'sort_order' => 1,
        ]);

        $this->createPromotion([
            'type' => 'discount_fixed',
            'discount_value' => 100,
            'name' => 'Non-Stackable After Exclusive',
            'stackable' => false,
            'is_exclusive' => false,
            'priority' => 1,
            'sort_order' => 2,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        // The exclusive promo applies first; then non-stackable should be skipped
        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(1, $promoDiscounts);

        $promo = reset($promoDiscounts);
        $this->assertEquals('Exclusive Promo', $promo['name']);
    }

    public function test_loyalty_discount_stacks_with_promotion(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 5);

        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        // Loyalty: 1000 * 5% = 50
        // Promotion: 1000 * 10% = 100 (скидки рассчитываются от полной суммы)
        $this->assertCount(2, $result['discounts']);
        $this->assertEquals(1000 - 50 - 100, $result['final_total']);
    }

    public function test_promo_code_added_after_automatic_promotions(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 5);

        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'name' => 'Auto Promo',
        ]);

        $this->createPromotion([
            'type' => 'discount_fixed',
            'discount_value' => 50,
            'code' => 'EXTRA50',
            'is_automatic' => false,
            'requires_promo_code' => true,
            'is_active' => true,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
            'promo_code' => 'EXTRA50',
        ]);

        // 3 discounts: level + auto promo + promo code
        $this->assertCount(3, $result['discounts']);
        $this->assertGreaterThan(0, $result['total_discount']);
    }

    // =========================================================================
    // 12. Promotion order type restriction
    // =========================================================================

    public function test_promotion_restricted_to_delivery_not_applied_for_dine_in(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'order_types' => ['delivery'],
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(0, $promoDiscounts);
    }

    public function test_promotion_restricted_to_delivery_applied_for_delivery(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'order_types' => ['delivery'],
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'delivery',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(1, $promoDiscounts);
    }

    // =========================================================================
    // 13. Edge cases: zero-amount orders
    // =========================================================================

    public function test_zero_subtotal_order(): void
    {
        $result = $this->service->calculate([
            'items' => [],
            'subtotal' => 0,
            'order_type' => 'dine_in',
        ]);

        $this->assertEquals(0, $result['order_total']);
        $this->assertEquals(0, $result['final_total']);
        $this->assertEquals(0, $result['total_discount']);
    }

    public function test_empty_items_with_subtotal(): void
    {
        $result = $this->service->calculate([
            'items' => [],
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $this->assertEquals(1000, $result['order_total']);
    }

    public function test_subtotal_calculated_from_items_when_not_provided(): void
    {
        $result = $this->service->calculate([
            'items' => [
                ['dish_id' => 1, 'price' => 500, 'quantity' => 2],
                ['dish_id' => 2, 'price' => 100, 'quantity' => 3],
            ],
            'order_type' => 'dine_in',
        ]);

        // 500*2 + 100*3 = 1300
        $this->assertEquals(1300, $result['order_total']);
    }

    // =========================================================================
    // 14. Bonus earning calculation
    // =========================================================================

    public function test_bonus_earned_for_customer(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 0, cashbackPercent: 5);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        // earn_rate from BonusSetting is 5%, but loyalty level cashback is 5% too
        // BonusService::calculateEarning uses the effective rate from BonusSetting
        // With final_total 1000 (no discount) and 5% rate => 50 bonuses
        $this->assertEquals(50, $result['bonus_earned']);
    }

    public function test_no_bonus_earned_without_customer(): void
    {
        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $this->assertEquals(0, $result['bonus_earned']);
    }

    public function test_bonus_earned_based_on_final_total_after_discounts(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 10, cashbackPercent: 5);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        // Loyalty discount: 1000 * 10% = 100
        // final_total = 900
        // Bonus: 900 * 5% = 45
        $this->assertEquals(900, $result['final_total']);
        $this->assertEquals(45, $result['bonus_earned']);
    }

    // =========================================================================
    // 15. Result structure validation
    // =========================================================================

    public function test_result_contains_all_expected_keys(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 5);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $this->assertArrayHasKey('order_total', $result);
        $this->assertArrayHasKey('discounts', $result);
        $this->assertArrayHasKey('applied_discounts', $result);
        $this->assertArrayHasKey('total_discount', $result);
        $this->assertArrayHasKey('final_total', $result);
        $this->assertArrayHasKey('bonus_earned', $result);
        $this->assertArrayHasKey('free_delivery', $result);
        $this->assertArrayHasKey('gift_items', $result);
        $this->assertArrayHasKey('applied_promotions', $result);
        $this->assertArrayHasKey('customer', $result);
    }

    public function test_applied_discounts_contain_source_type_and_id(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 5);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $this->assertNotEmpty($result['applied_discounts']);
        $levelApplied = $result['applied_discounts'][0];
        $this->assertEquals('level', $levelApplied['type']);
        $this->assertEquals('level', $levelApplied['sourceType']);
        $this->assertArrayHasKey('sourceId', $levelApplied);
        $this->assertTrue($levelApplied['auto']);
        $this->assertTrue($levelApplied['stackable']);
    }

    public function test_customer_info_returned_when_customer_exists(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 5);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $this->assertNotNull($result['customer']);
        $this->assertEquals($customer->id, $result['customer']['id']);
        $this->assertArrayHasKey('name', $result['customer']);
        $this->assertArrayHasKey('level', $result['customer']);
        $this->assertArrayHasKey('bonus_balance', $result['customer']);
    }

    public function test_applied_promotions_lists_promotion_ids(): void
    {
        $promo = $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $this->assertContains($promo->id, $result['applied_promotions']);
    }

    // =========================================================================
    // 16. recalculateFromAppliedDiscounts
    // =========================================================================

    public function test_recalculate_percent_discount(): void
    {
        $appliedDiscounts = [
            [
                'name' => 'Birthday discount',
                'type' => 'birthday',
                'sourceType' => 'birthday',
                'percent' => 10,
                'amount' => 100,
            ],
        ];

        $orderItems = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 600, 'quantity' => 2],
        ];

        $result = $this->service->recalculateFromAppliedDiscounts($appliedDiscounts, $orderItems, 1200);

        // Items total: 600*2 = 1200, 10% = 120
        $this->assertEquals(120, $result['total_discount']);
        $this->assertEquals(120, $result['discounts'][0]['amount']);
    }

    public function test_recalculate_fixed_amount_discount(): void
    {
        $appliedDiscounts = [
            [
                'name' => 'Fixed promo',
                'type' => 'promo_code',
                'sourceType' => 'promotion',
                'fixedAmount' => 200,
                'amount' => 200,
            ],
        ];

        $orderItems = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 1],
        ];

        $result = $this->service->recalculateFromAppliedDiscounts($appliedDiscounts, $orderItems, 500);

        $this->assertEquals(200, $result['total_discount']);
    }

    public function test_recalculate_fixed_amount_capped_at_applicable_total(): void
    {
        $appliedDiscounts = [
            [
                'name' => 'Fixed promo',
                'type' => 'promo_code',
                'sourceType' => 'promotion',
                'fixedAmount' => 5000,
                'amount' => 5000,
            ],
        ];

        $orderItems = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 300, 'quantity' => 1],
        ];

        $result = $this->service->recalculateFromAppliedDiscounts($appliedDiscounts, $orderItems, 300);

        // Fixed 5000 > applicable 300, so capped at 300
        $this->assertEquals(300, $result['total_discount']);
    }

    public function test_recalculate_filters_out_level_discounts(): void
    {
        $appliedDiscounts = [
            [
                'name' => 'Level discount',
                'type' => 'level',
                'sourceType' => 'level',
                'percent' => 5,
                'amount' => 50,
            ],
            [
                'name' => 'Birthday',
                'type' => 'birthday',
                'sourceType' => 'birthday',
                'percent' => 10,
                'amount' => 100,
            ],
        ];

        $orderItems = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 1000, 'quantity' => 1],
        ];

        $result = $this->service->recalculateFromAppliedDiscounts($appliedDiscounts, $orderItems, 1000);

        // Level discount should be filtered out; only birthday should remain
        $this->assertCount(1, $result['discounts']);
        $this->assertEquals('birthday', $result['discounts'][0]['type']);
    }

    public function test_recalculate_filters_out_rounding_discounts(): void
    {
        $appliedDiscounts = [
            [
                'name' => 'Rounding',
                'type' => 'rounding',
                'sourceType' => 'rounding',
                'amount' => 3,
            ],
            [
                'name' => 'Birthday',
                'type' => 'birthday',
                'sourceType' => 'birthday',
                'percent' => 10,
                'amount' => 100,
            ],
        ];

        $orderItems = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 1000, 'quantity' => 1],
        ];

        $result = $this->service->recalculateFromAppliedDiscounts($appliedDiscounts, $orderItems, 1000);

        $this->assertCount(1, $result['discounts']);
        $this->assertEquals('birthday', $result['discounts'][0]['type']);
    }

    public function test_recalculate_respects_max_discount(): void
    {
        $appliedDiscounts = [
            [
                'name' => 'Big percent promo',
                'type' => 'promotion',
                'sourceType' => 'promotion',
                'percent' => 50,
                'maxDiscount' => 200,
                'amount' => 200,
            ],
        ];

        $orderItems = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 1000, 'quantity' => 1],
        ];

        $result = $this->service->recalculateFromAppliedDiscounts($appliedDiscounts, $orderItems, 1000);

        // 50% of 1000 = 500, but maxDiscount = 200
        $this->assertEquals(200, $result['total_discount']);
    }

    // =========================================================================
    // 17. Static: calculateApplicableTotal
    // =========================================================================

    public function test_applicable_total_whole_order(): void
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 2],
            ['dish_id' => 2, 'category_id' => 2, 'price' => 300, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'whole_order',
        ]);

        $this->assertEquals(1300, $total);
    }

    public function test_applicable_total_specific_dishes(): void
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 2],
            ['dish_id' => 2, 'category_id' => 1, 'price' => 300, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'dishes',
            'applicable_dishes' => [1],
        ]);

        // Only dish_id=1: 500*2 = 1000
        $this->assertEquals(1000, $total);
    }

    public function test_applicable_total_specific_categories(): void
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 1],
            ['dish_id' => 2, 'category_id' => 2, 'price' => 300, 'quantity' => 1],
            ['dish_id' => 3, 'category_id' => 1, 'price' => 200, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'categories',
            'applicable_categories' => [1],
        ]);

        // category_id=1: 500 + 200 = 700
        $this->assertEquals(700, $total);
    }

    public function test_applicable_total_with_excluded_dishes(): void
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 1],
            ['dish_id' => 2, 'category_id' => 1, 'price' => 300, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'whole_order',
            'excluded_dishes' => [2],
        ]);

        // Excluded dish_id=2: only 500
        $this->assertEquals(500, $total);
    }

    public function test_applicable_total_with_excluded_categories(): void
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 1],
            ['dish_id' => 2, 'category_id' => 2, 'price' => 300, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'whole_order',
            'excluded_categories' => [2],
        ]);

        $this->assertEquals(500, $total);
    }

    public function test_applicable_total_empty_items(): void
    {
        $total = DiscountCalculatorService::calculateApplicableTotal([], [
            'applies_to' => 'whole_order',
        ]);

        $this->assertEquals(0, $total);
    }

    public function test_applicable_total_no_matching_dishes(): void
    {
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 1],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'dishes',
            'applicable_dishes' => [999],
        ]);

        $this->assertEquals(0, $total);
    }

    public function test_applicable_total_combo_requires_all_dishes(): void
    {
        $items = [
            ['dish_id' => 1, 'price' => 500, 'quantity' => 3],
            ['dish_id' => 2, 'price' => 150, 'quantity' => 2],
        ];

        $total = DiscountCalculatorService::calculateApplicableTotal($items, [
            'applies_to' => 'dishes',
            'applicable_dishes' => [1, 2],
            'requires_all_dishes' => true,
        ]);

        // min(3,2) = 2 combos: 2*(500+150) = 1300
        $this->assertEquals(1300, $total);
    }

    // =========================================================================
    // 18. Static: calculateComboTotal
    // =========================================================================

    public function test_combo_total_full_sets(): void
    {
        $items = [
            ['dish_id' => 1, 'price' => 500, 'quantity' => 3],
            ['dish_id' => 2, 'price' => 150, 'quantity' => 2],
        ];

        $total = DiscountCalculatorService::calculateComboTotal($items, [1, 2]);

        // 2 combos * (500 + 150) = 1300
        $this->assertEquals(1300, $total);
    }

    public function test_combo_total_missing_item_returns_zero(): void
    {
        $items = [
            ['dish_id' => 1, 'price' => 500, 'quantity' => 3],
        ];

        $total = DiscountCalculatorService::calculateComboTotal($items, [1, 2]);

        $this->assertEquals(0, $total);
    }

    public function test_combo_total_extra_items_ignored(): void
    {
        $items = [
            ['dish_id' => 1, 'price' => 500, 'quantity' => 5],
            ['dish_id' => 2, 'price' => 150, 'quantity' => 2],
            ['dish_id' => 3, 'price' => 300, 'quantity' => 10], // not in combo
        ];

        $total = DiscountCalculatorService::calculateComboTotal($items, [1, 2]);

        // min(5,2) = 2 combos * (500+150) = 1300
        $this->assertEquals(1300, $total);
    }

    public function test_combo_total_three_item_combo(): void
    {
        $items = [
            ['dish_id' => 1, 'price' => 500, 'quantity' => 2],
            ['dish_id' => 2, 'price' => 150, 'quantity' => 3],
            ['dish_id' => 3, 'price' => 100, 'quantity' => 2],
        ];

        $total = DiscountCalculatorService::calculateComboTotal($items, [1, 2, 3]);

        // min(2,3,2) = 2 combos * (500+150+100) = 1500
        $this->assertEquals(1500, $total);
    }

    public function test_combo_total_empty_items(): void
    {
        $total = DiscountCalculatorService::calculateComboTotal([], [1, 2]);

        $this->assertEquals(0, $total);
    }

    public function test_combo_total_empty_applicable_dishes(): void
    {
        $items = [
            ['dish_id' => 1, 'price' => 500, 'quantity' => 2],
        ];

        $total = DiscountCalculatorService::calculateComboTotal($items, []);

        $this->assertEquals(0, $total);
    }

    // =========================================================================
    // 19. Edge cases: final_total never negative
    // =========================================================================

    public function test_final_total_never_negative(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 90);

        $this->createPromotion([
            'type' => 'discount_fixed',
            'discount_value' => 500,
        ]);

        $result = $this->service->calculate([
            'items' => [['dish_id' => 1, 'category_id' => 1, 'price' => 100, 'quantity' => 1]],
            'subtotal' => 100,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $this->assertGreaterThanOrEqual(0, $result['final_total']);
    }

    // =========================================================================
    // 20. Default parameter handling
    // =========================================================================

    public function test_default_order_type_is_dine_in(): void
    {
        // Order type restricted promotion that only works for pickup
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'order_types' => ['pickup'],
        ]);

        // Not passing order_type should default to 'dine_in'
        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(0, $promoDiscounts);
    }

    // =========================================================================
    // 21. Promotion with first_order_only
    // =========================================================================

    public function test_first_order_promotion_applied_for_new_customer(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 0, ordersCount: 0);

        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 15,
            'is_first_order_only' => true,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(1, $promoDiscounts);
    }

    public function test_first_order_promotion_not_applied_for_returning_customer(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 0, ordersCount: 5);

        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 15,
            'is_first_order_only' => true,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(0, $promoDiscounts);
    }

    // =========================================================================
    // 22. Promotion with loyalty level restriction
    // =========================================================================

    public function test_promotion_restricted_to_loyalty_level_applied(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 5);
        $levelId = $customer->loyalty_level_id;

        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'loyalty_levels' => [$levelId],
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(1, $promoDiscounts);
    }

    public function test_promotion_restricted_to_other_loyalty_level_not_applied(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 5);

        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'loyalty_levels' => [999], // non-matching level
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(0, $promoDiscounts);
    }

    // =========================================================================
    // 23. Promotion with excluded customer
    // =========================================================================

    public function test_promotion_excludes_specific_customer(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 0);

        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'excluded_customers' => [$customer->id],
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(0, $promoDiscounts);
    }

    // =========================================================================
    // 24. free_delivery flag defaults to false
    // =========================================================================

    public function test_free_delivery_defaults_to_false(): void
    {
        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $this->assertFalse($result['free_delivery']);
    }

    // =========================================================================
    // 25. gift_items defaults to empty array
    // =========================================================================

    public function test_gift_items_defaults_to_empty_array(): void
    {
        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $this->assertIsArray($result['gift_items']);
        $this->assertEmpty($result['gift_items']);
    }

    // =========================================================================
    // 26. Rounding of discount values
    // =========================================================================

    public function test_loyalty_discount_rounded_to_two_decimals(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 7);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 999,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        // 999 * 7% = 69.93
        $levelDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'level');
        $levelDiscount = reset($levelDiscounts);
        $this->assertEquals(69.93, $levelDiscount['amount']);
    }

    public function test_total_discount_rounded_to_two_decimals(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 3);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 777,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        // 777 * 3% = 23.31
        $this->assertEquals(23.31, $result['total_discount']);
    }

    // =========================================================================
    // 27. Constructor with custom restaurant ID
    // =========================================================================

    public function test_service_uses_restaurant_id_for_promotions(): void
    {
        // Create promo for a different restaurant
        $otherRestaurant = Restaurant::factory()->create();
        Promotion::create([
            'restaurant_id' => $otherRestaurant->id,
            'name' => 'Other restaurant promo',
            'type' => 'discount_percent',
            'applies_to' => 'whole_order',
            'discount_value' => 50,
            'is_active' => true,
            'is_automatic' => true,
            'requires_promo_code' => false,
            'stackable' => true,
            'is_exclusive' => false,
            'priority' => 1,
            'sort_order' => 1,
        ]);

        // Our service uses $this->restaurant->id - should NOT pick up other restaurant's promo
        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(0, $promoDiscounts);
    }

    // =========================================================================
    // 28. Promotion applies_to dishes / categories filter
    // =========================================================================

    public function test_promotion_applies_to_specific_dishes_only(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 20,
            'applies_to' => 'dishes',
            'applicable_dishes' => [1], // only dish_id=1
        ]);

        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 2],
            ['dish_id' => 2, 'category_id' => 2, 'price' => 300, 'quantity' => 1],
        ];

        $result = $this->service->calculate([
            'items' => $items,
            'subtotal' => 1300,
            'order_type' => 'dine_in',
        ]);

        // Should apply 20% only to dish_id=1: 1000 * 20% = 200
        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $promo = reset($promoDiscounts);
        $this->assertEquals(200, $promo['amount']);
    }

    public function test_promotion_applies_to_specific_categories_only(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'applies_to' => 'categories',
            'applicable_categories' => [1], // only category_id=1
        ]);

        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 1],
            ['dish_id' => 2, 'category_id' => 2, 'price' => 300, 'quantity' => 1],
        ];

        $result = $this->service->calculate([
            'items' => $items,
            'subtotal' => 800,
            'order_type' => 'dine_in',
        ]);

        // Should apply 10% only to category_id=1: 500 * 10% = 50
        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $promo = reset($promoDiscounts);
        $this->assertEquals(50, $promo['amount']);
    }

    // =========================================================================
    // 29. Promotion: progressive_discount type
    // =========================================================================

    public function test_progressive_discount_promotion(): void
    {
        $this->createPromotion([
            'type' => 'progressive_discount',
            'discount_value' => 0,
            'progressive_tiers' => [
                ['min_amount' => 500, 'discount_percent' => 5],
                ['min_amount' => 1000, 'discount_percent' => 10],
                ['min_amount' => 2000, 'discount_percent' => 15],
            ],
        ]);

        // Order total = 1500 => should match the 1000 tier (10%)
        $result = $this->service->calculate([
            'items' => [['dish_id' => 1, 'category_id' => 1, 'price' => 1500, 'quantity' => 1]],
            'subtotal' => 1500,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $promo = reset($promoDiscounts);
        // 1500 * 10% = 150
        $this->assertEquals(150, $promo['amount']);
        $this->assertEquals(10, $promo['percent']);
    }

    public function test_progressive_discount_highest_tier(): void
    {
        $this->createPromotion([
            'type' => 'progressive_discount',
            'discount_value' => 0,
            'progressive_tiers' => [
                ['min_amount' => 500, 'discount_percent' => 5],
                ['min_amount' => 1000, 'discount_percent' => 10],
                ['min_amount' => 2000, 'discount_percent' => 15],
            ],
        ]);

        $result = $this->service->calculate([
            'items' => [['dish_id' => 1, 'category_id' => 1, 'price' => 3000, 'quantity' => 1]],
            'subtotal' => 3000,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $promo = reset($promoDiscounts);
        // 3000 * 15% = 450
        $this->assertEquals(450, $promo['amount']);
    }

    public function test_progressive_discount_below_all_tiers(): void
    {
        $this->createPromotion([
            'type' => 'progressive_discount',
            'discount_value' => 0,
            'progressive_tiers' => [
                ['min_amount' => 500, 'discount_percent' => 5],
                ['min_amount' => 1000, 'discount_percent' => 10],
            ],
        ]);

        $result = $this->service->calculate([
            'items' => [['dish_id' => 1, 'category_id' => 1, 'price' => 200, 'quantity' => 1]],
            'subtotal' => 200,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        // Should not apply because order total 200 < lowest tier 500
        $this->assertCount(0, $promoDiscounts);
    }

    // =========================================================================
    // 30. Combo promotion (requires_all_dishes) integration
    // =========================================================================

    public function test_combo_promotion_applied_when_all_dishes_present(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 20,
            'applies_to' => 'dishes',
            'applicable_dishes' => [1, 2],
            'requires_all_dishes' => true,
        ]);

        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 2],
            ['dish_id' => 2, 'category_id' => 2, 'price' => 300, 'quantity' => 2],
        ];

        $result = $this->service->calculate([
            'items' => $items,
            'subtotal' => 1600,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(1, $promoDiscounts);

        // 2 combos * (500+300) = 1600 applicable, 20% = 320
        $promo = reset($promoDiscounts);
        $this->assertEquals(320, $promo['amount']);
    }

    public function test_combo_promotion_not_applied_when_dish_missing(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 20,
            'applies_to' => 'dishes',
            'applicable_dishes' => [1, 2],
            'requires_all_dishes' => true,
        ]);

        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 2],
            // dish_id=2 missing
        ];

        $result = $this->service->calculate([
            'items' => $items,
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(0, $promoDiscounts);
    }

    // =========================================================================
    // 31. Discount does not exceed order total
    // =========================================================================

    public function test_total_discount_does_not_exceed_subtotal(): void
    {
        $customer = $this->createCustomerWithLevel(discountPercent: 50);

        $this->createPromotion([
            'type' => 'discount_fixed',
            'discount_value' => 10000,
        ]);

        $result = $this->service->calculate([
            'items' => [['dish_id' => 1, 'category_id' => 1, 'price' => 100, 'quantity' => 1]],
            'subtotal' => 100,
            'order_type' => 'dine_in',
            'customer_id' => $customer->id,
        ]);

        // With 50% level discount (50) + 10000 fixed (capped at remaining 50)
        // total_discount should not make final_total negative
        $this->assertGreaterThanOrEqual(0, $result['final_total']);
    }

    // =========================================================================
    // 32. Promotion with min_items_count
    // =========================================================================

    public function test_promotion_not_applied_below_min_items_count(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'min_items_count' => 5,
        ]);

        // Only 3 items total
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 2],
            ['dish_id' => 2, 'category_id' => 2, 'price' => 300, 'quantity' => 1],
        ];

        $result = $this->service->calculate([
            'items' => $items,
            'subtotal' => 1300,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(0, $promoDiscounts);
    }

    public function test_promotion_applied_at_min_items_count(): void
    {
        $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 10,
            'min_items_count' => 3,
        ]);

        // Exactly 3 items
        $items = [
            ['dish_id' => 1, 'category_id' => 1, 'price' => 500, 'quantity' => 2],
            ['dish_id' => 2, 'category_id' => 2, 'price' => 300, 'quantity' => 1],
        ];

        $result = $this->service->calculate([
            'items' => $items,
            'subtotal' => 1300,
            'order_type' => 'dine_in',
        ]);

        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(1, $promoDiscounts);
    }

    // =========================================================================
    // 33. Promotion sorting by priority
    // =========================================================================

    public function test_promotions_applied_in_priority_order(): void
    {
        // Higher priority first
        $highPrio = $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 5,
            'name' => 'High Priority',
            'priority' => 10,
            'sort_order' => 2,
            'stackable' => false,
        ]);

        $lowPrio = $this->createPromotion([
            'type' => 'discount_percent',
            'discount_value' => 20,
            'name' => 'Low Priority',
            'priority' => 1,
            'sort_order' => 1,
            'stackable' => false,
        ]);

        $result = $this->service->calculate([
            'items' => $this->sampleItems(),
            'subtotal' => 1000,
            'order_type' => 'dine_in',
        ]);

        // High priority promo is non-stackable, so only it should apply
        $promoDiscounts = array_filter($result['discounts'], fn($d) => $d['type'] === 'promotion');
        $this->assertCount(1, $promoDiscounts);
        $promo = reset($promoDiscounts);
        $this->assertEquals('High Priority', $promo['name']);
    }
}
