<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\PromoCode;
use App\Models\Customer;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class PromoCodeTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->restaurant = Restaurant::factory()->create();
    }

    // ==========================================
    // РАСЧЁТ СКИДКИ
    // ==========================================

    /** @test */
    public function it_calculates_percent_discount()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'PCT20',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 20,
            'is_active' => true,
        ]);

        $this->assertEquals(200, $promoCode->calculateDiscount(1000));
        $this->assertEquals(500, $promoCode->calculateDiscount(2500));
        $this->assertEquals(0, $promoCode->calculateDiscount(0));
    }

    /** @test */
    public function it_calculates_fixed_discount()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'FIX300',
            'name' => 'Test',
            'type' => 'fixed',
            'value' => 300,
            'is_active' => true,
        ]);

        $this->assertEquals(300, $promoCode->calculateDiscount(1000));
        $this->assertEquals(300, $promoCode->calculateDiscount(500));
        $this->assertEquals(200, $promoCode->calculateDiscount(200)); // Не больше суммы заказа
    }

    /** @test */
    public function it_respects_max_discount_for_percent()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'MAXED',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 50,
            'max_discount' => 200,
            'is_active' => true,
        ]);

        // 50% от 1000 = 500, но max = 200
        $this->assertEquals(200, $promoCode->calculateDiscount(1000));

        // 50% от 300 = 150, меньше max
        $this->assertEquals(150, $promoCode->calculateDiscount(300));
    }

    // ==========================================
    // ПРОВЕРКА ВАЛИДНОСТИ
    // ==========================================

    /** @test */
    public function it_validates_active_promo_code()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'ACTIVE',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
        ]);

        $result = $promoCode->checkValidity();

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    /** @test */
    public function it_rejects_inactive_promo_code()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'INACTIVE',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 10,
            'is_active' => false,
        ]);

        $result = $promoCode->checkValidity();

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('неактивен', $result['error']);
    }

    /** @test */
    public function it_rejects_expired_promo_code()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'EXPIRED',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
            'expires_at' => Carbon::yesterday(),
        ]);

        $result = $promoCode->checkValidity();

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('истёк', $result['error']);
    }

    /** @test */
    public function it_rejects_not_yet_started_promo_code()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'FUTURE',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
            'starts_at' => Carbon::tomorrow(),
        ]);

        $result = $promoCode->checkValidity();

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('не активен', $result['error']);
    }

    /** @test */
    public function it_rejects_exhausted_usage_limit()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'LIMITED',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
            'usage_limit' => 5,
            'usage_count' => 5,
        ]);

        $result = $promoCode->checkValidity();

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('исчерпан', $result['error']);
    }

    /** @test */
    public function it_rejects_order_below_minimum()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'MINORD',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
            'min_order_amount' => 1000,
        ]);

        $result = $promoCode->checkValidity(null, 500);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Минимальная сумма', $result['error']);
    }

    /** @test */
    public function it_accepts_order_above_minimum()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'MINOK',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
            'min_order_amount' => 1000,
        ]);

        $result = $promoCode->checkValidity(null, 1500);

        $this->assertTrue($result['valid']);
    }

    // ==========================================
    // ПОИСК ПО КОДУ
    // ==========================================

    /** @test */
    public function it_finds_promo_code_by_code()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'FINDME',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
        ]);

        $found = PromoCode::findByCode('FINDME', $this->restaurant->id);

        $this->assertNotNull($found);
        $this->assertEquals($promoCode->id, $found->id);
    }

    /** @test */
    public function it_finds_promo_code_case_insensitive()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'UPPERCASE',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
        ]);

        $found = PromoCode::findByCode('uppercase', $this->restaurant->id);

        $this->assertNotNull($found);
        $this->assertEquals($promoCode->id, $found->id);
    }

    /** @test */
    public function it_returns_null_for_unknown_code()
    {
        $found = PromoCode::findByCode('UNKNOWN', $this->restaurant->id);

        $this->assertNull($found);
    }

    // ==========================================
    // СТАТУСЫ И АТРИБУТЫ
    // ==========================================

    /** @test */
    public function it_returns_correct_status_active()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'STATUS1',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
        ]);

        $this->assertEquals('active', $promoCode->status);
    }

    /** @test */
    public function it_returns_correct_status_inactive()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'STATUS2',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 10,
            'is_active' => false,
        ]);

        $this->assertEquals('inactive', $promoCode->status);
    }

    /** @test */
    public function it_returns_correct_status_expired()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'STATUS3',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
            'expires_at' => Carbon::yesterday(),
        ]);

        $this->assertEquals('expired', $promoCode->status);
    }

    /** @test */
    public function it_returns_correct_status_exhausted()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'STATUS4',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 10,
            'is_active' => true,
            'usage_limit' => 10,
            'usage_count' => 10,
        ]);

        $this->assertEquals('exhausted', $promoCode->status);
    }

    /** @test */
    public function it_returns_formatted_value_for_percent()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'FMT1',
            'name' => 'Test',
            'type' => 'percent',
            'value' => 25,
            'is_active' => true,
        ]);

        $this->assertEquals('25%', $promoCode->formatted_value);
    }

    /** @test */
    public function it_returns_formatted_value_for_fixed()
    {
        $promoCode = PromoCode::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'FMT2',
            'name' => 'Test',
            'type' => 'fixed',
            'value' => 500,
            'is_active' => true,
        ]);

        $this->assertStringContainsString('500', $promoCode->formatted_value);
    }

    // ==========================================
    // ГЕНЕРАЦИЯ КОДА
    // ==========================================

    /** @test */
    public function it_generates_unique_code()
    {
        $code1 = PromoCode::generateCode();
        $code2 = PromoCode::generateCode();

        $this->assertEquals(8, strlen($code1));
        $this->assertEquals(8, strlen($code2));
        $this->assertNotEquals($code1, $code2);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $code1);
    }

    /** @test */
    public function it_generates_code_with_custom_length()
    {
        $code = PromoCode::generateCode(12);

        $this->assertEquals(12, strlen($code));
    }
}
