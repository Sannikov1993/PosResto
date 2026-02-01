<?php

namespace Tests\Feature\Api\Finance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\CashShift;
use App\Models\CashOperation;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FinanceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'role' => 'super_admin', // Has all permissions
        ]);
    }

    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    // =========================================================================
    // CASH SHIFTS - OPEN/CLOSE
    // =========================================================================

    public function test_can_open_shift(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/finance/shifts/open', [
            'cashier_id' => $this->user->id,
            'opening_amount' => 5000,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Смена открыта',
            ]);

        $this->assertDatabaseHas('cash_shifts', [
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opening_amount' => 5000,
        ]);
    }

    public function test_cannot_open_shift_when_one_is_already_open(): void
    {
        $this->authenticate();

        CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->postJson('/api/finance/shifts/open', [
            'opening_amount' => 5000,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_close_shift(): void
    {
        $this->authenticate();

        $shift = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_amount' => 5000,
        ]);

        $response = $this->postJson("/api/finance/shifts/{$shift->id}/close", [
            'closing_amount' => 7500,
            'notes' => 'Test notes',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Смена закрыта',
            ]);

        $shift->refresh();
        $this->assertEquals('closed', $shift->status);
        $this->assertEquals(7500, $shift->closing_amount);
        $this->assertNotNull($shift->closed_at);
    }

    public function test_cannot_close_already_closed_shift(): void
    {
        $this->authenticate();

        $shift = CashShift::factory()->closed()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->postJson("/api/finance/shifts/{$shift->id}/close", [
            'closing_amount' => 5000,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Смена уже закрыта',
            ]);
    }

    // =========================================================================
    // CASH SHIFTS - GET
    // =========================================================================

    public function test_can_get_current_shift(): void
    {
        $this->authenticate();

        $shift = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->getJson('/api/finance/shifts/current');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals($shift->id, $response->json('data.id'));
    }

    public function test_current_shift_returns_null_when_no_open_shift(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/finance/shifts/current');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => null,
            ]);
    }

    public function test_can_list_shifts(): void
    {
        $this->authenticate();

        CashShift::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->getJson('/api/finance/shifts');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_filter_shifts_by_status(): void
    {
        $this->authenticate();

        CashShift::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        CashShift::factory()->closed()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->getJson('/api/finance/shifts?status=open');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_show_shift_details(): void
    {
        $this->authenticate();

        $shift = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->getJson("/api/finance/shifts/{$shift->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $shift->id,
                ],
            ]);
    }

    // =========================================================================
    // CASH OPERATIONS - DEPOSIT/WITHDRAWAL
    // =========================================================================

    public function test_can_deposit_cash(): void
    {
        $this->authenticate();

        CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->postJson('/api/finance/operations/deposit', [
            'amount' => 1000,
            'staff_id' => $this->user->id,
            'description' => 'Размен',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Деньги внесены в кассу',
            ]);

        $this->assertDatabaseHas('cash_operations', [
            'restaurant_id' => $this->restaurant->id,
            'type' => 'deposit',
            'amount' => 1000,
        ]);
    }

    public function test_cannot_deposit_without_open_shift(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/finance/operations/deposit', [
            'amount' => 1000,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Нет открытой смены',
            ]);
    }

    public function test_can_withdraw_cash(): void
    {
        $this->authenticate();

        $shift = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_amount' => 10000,
        ]);

        // Add some income first
        CashOperation::create([
            'restaurant_id' => $this->restaurant->id,
            'cash_shift_id' => $shift->id,
            'type' => 'income',
            'category' => 'order',
            'amount' => 5000,
            'payment_method' => 'cash',
        ]);

        $response = $this->postJson('/api/finance/operations/withdrawal', [
            'amount' => 2000,
            'category' => 'purchase',
            'description' => 'Закупка продуктов',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Деньги изъяты из кассы',
            ]);

        $this->assertDatabaseHas('cash_operations', [
            'restaurant_id' => $this->restaurant->id,
            'type' => 'withdrawal',
            'category' => 'purchase',
            'amount' => 2000,
        ]);
    }

    public function test_cannot_withdraw_more_than_available(): void
    {
        $this->authenticate();

        CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_amount' => 1000,
        ]);

        $response = $this->postJson('/api/finance/operations/withdrawal', [
            'amount' => 5000,
            'category' => 'purchase',
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'success' => false,
            ]);
    }

    public function test_withdrawal_validates_category(): void
    {
        $this->authenticate();

        CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->postJson('/api/finance/operations/withdrawal', [
            'amount' => 100,
            'category' => 'invalid_category',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    // =========================================================================
    // CASH OPERATIONS - REFUND
    // =========================================================================

    public function test_can_process_refund(): void
    {
        $this->authenticate();

        CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_amount' => 10000,
        ]);

        $response = $this->postJson('/api/finance/operations/refund', [
            'amount' => 500,
            'refund_method' => 'cash',
            'order_number' => 'ORD-001',
            'reason' => 'Customer request',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Возврат оформлен',
            ]);

        $this->assertDatabaseHas('cash_operations', [
            'restaurant_id' => $this->restaurant->id,
            'type' => 'expense',
            'category' => 'refund',
            'amount' => 500,
        ]);
    }

    public function test_cannot_refund_more_than_cash_available(): void
    {
        $this->authenticate();

        CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_amount' => 100,
        ]);

        $response = $this->postJson('/api/finance/operations/refund', [
            'amount' => 500,
            'refund_method' => 'cash',
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'success' => false,
            ]);
    }

    // =========================================================================
    // REPORTS
    // =========================================================================

    public function test_can_get_x_report(): void
    {
        $this->authenticate();

        $shift = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_amount' => 5000,
        ]);

        // Add some operations
        CashOperation::create([
            'restaurant_id' => $this->restaurant->id,
            'cash_shift_id' => $shift->id,
            'type' => 'income',
            'category' => 'order',
            'amount' => 1500,
            'payment_method' => 'cash',
        ]);

        $response = $this->getJson('/api/finance/x-report');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'shift',
                    'expected_cash',
                    'operations_summary',
                    'generated_at',
                ],
            ]);
    }

    public function test_x_report_requires_open_shift(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/finance/x-report');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Нет открытой смены',
            ]);
    }

    public function test_can_get_z_report(): void
    {
        $this->authenticate();

        $shift = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_amount' => 5000,
        ]);

        // Note: Z-report is GET, not POST according to routes
        $response = $this->getJson("/api/finance/shifts/{$shift->id}/z-report?closing_amount=6500");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $shift->refresh();
        $this->assertEquals('closed', $shift->status);
    }

    public function test_can_get_daily_summary(): void
    {
        $this->authenticate();

        // Create some orders
        Order::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'total' => 1000,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/finance/summary/daily');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'date',
                    'orders' => [
                        'total',
                        'paid',
                        'revenue',
                        'cash',
                        'card',
                        'online',
                        'average_check',
                    ],
                    'operations',
                    'shifts',
                ],
            ]);
    }

    public function test_can_get_period_summary(): void
    {
        $this->authenticate();

        Order::factory()->count(5)->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'total' => 2000,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/finance/summary/period?' . http_build_query([
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period',
                    'totals' => [
                        'revenue',
                        'orders',
                        'average_check',
                    ],
                    'daily',
                ],
            ]);
    }

    // =========================================================================
    // OPERATIONS LIST
    // =========================================================================

    public function test_can_list_operations(): void
    {
        $this->authenticate();

        $shift = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Create operations directly
        for ($i = 0; $i < 5; $i++) {
            CashOperation::create([
                'restaurant_id' => $this->restaurant->id,
                'cash_shift_id' => $shift->id,
                'type' => 'income',
                'category' => 'order',
                'amount' => 100 + $i * 10,
                'payment_method' => 'cash',
            ]);
        }

        $response = $this->getJson('/api/finance/operations');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_can_filter_operations_by_shift(): void
    {
        $this->authenticate();

        $shift1 = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $shift2 = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Create operations for shift1
        for ($i = 0; $i < 3; $i++) {
            CashOperation::create([
                'restaurant_id' => $this->restaurant->id,
                'cash_shift_id' => $shift1->id,
                'type' => 'income',
                'category' => 'order',
                'amount' => 100,
                'payment_method' => 'cash',
            ]);
        }

        // Create operations for shift2
        for ($i = 0; $i < 2; $i++) {
            CashOperation::create([
                'restaurant_id' => $this->restaurant->id,
                'cash_shift_id' => $shift2->id,
                'type' => 'income',
                'category' => 'order',
                'amount' => 200,
                'payment_method' => 'card',
            ]);
        }

        $response = $this->getJson("/api/finance/operations?shift_id={$shift1->id}");

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    // =========================================================================
    // AUTHORIZATION
    // =========================================================================

    public function test_operations_require_authentication(): void
    {
        $response = $this->getJson('/api/finance/operations');

        $response->assertUnauthorized();
    }

    public function test_shifts_require_authentication(): void
    {
        $response = $this->getJson('/api/finance/shifts');

        $response->assertUnauthorized();
    }
}
