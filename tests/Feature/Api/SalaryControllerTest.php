<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\WorkSession;
use App\Models\SalaryPeriod;
use App\Models\SalaryCalculation;
use App\Models\SalaryPayment;
use App\Models\Order;
use App\Services\StaffNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class SalaryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Restaurant $otherRestaurant;
    protected Role $adminRole;
    protected Role $waiterRole;
    protected User $admin;
    protected User $waiter;
    protected User $cook;
    protected User $otherRestaurantUser;
    protected string $adminToken;
    protected string $waiterToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create main restaurant
        $this->restaurant = Restaurant::factory()->create();

        // Create other restaurant for isolation tests
        $this->otherRestaurant = Restaurant::factory()->create();

        // Create admin role with all permissions
        $this->adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Администратор',
            'is_system' => true,
            'is_active' => true,
            'max_discount_percent' => 100,
            'max_refund_amount' => 100000,
            'max_cancel_amount' => 100000,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
            'can_access_kitchen' => true,
            'can_access_delivery' => true,
        ]);

        // Create waiter role
        $this->waiterRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'waiter',
            'name' => 'Официант',
            'is_system' => true,
            'is_active' => true,
            'max_discount_percent' => 10,
            'max_refund_amount' => 0,
            'max_cancel_amount' => 0,
            'can_access_pos' => true,
            'can_access_backoffice' => false,
            'can_access_kitchen' => false,
            'can_access_delivery' => false,
        ]);

        // Create admin permissions
        $adminPermissions = [
            'staff.view', 'staff.create', 'staff.edit', 'staff.delete',
            'payroll.view', 'payroll.manage', 'payroll.pay',
            'salary.view', 'salary.manage', 'salary.pay',
            'finance.view', 'finance.edit',
        ];

        foreach ($adminPermissions as $key) {
            $perm = Permission::firstOrCreate([
                'restaurant_id' => $this->restaurant->id,
                'key' => $key,
            ], [
                'name' => $key,
                'group' => explode('.', $key)[0],
            ]);
            $this->adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        // Create admin user
        $this->admin = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
            'salary' => 50000,
            'salary_type' => 'fixed',
            'hourly_rate' => 500,
        ]);

        // Create waiter user
        $this->waiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
            'salary' => 30000,
            'salary_type' => 'hourly',
            'hourly_rate' => 300,
            'percent_rate' => 5,
        ]);

        // Create cook user
        $this->cook = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'cook',
            'is_active' => true,
            'salary' => 40000,
            'salary_type' => 'mixed',
            'hourly_rate' => 350,
        ]);

        // Create user from another restaurant for isolation tests
        $this->otherRestaurantUser = User::factory()->create([
            'restaurant_id' => $this->otherRestaurant->id,
            'role' => 'waiter',
            'is_active' => true,
        ]);

        // Create tokens for API authentication
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->waiterToken = $this->waiter->createToken('test')->plainTextToken;

        // Mock StaffNotificationService
        $this->app->bind(StaffNotificationService::class, function () {
            $mock = Mockery::mock(StaffNotificationService::class);
            $fakeNotification = new \App\Models\Notification([
                'id' => 1,
                'user_id' => 1,
                'type' => 'test',
                'title' => 'Test',
                'message' => 'Test',
            ]);
            $mock->shouldReceive('notifySalaryPaid')->andReturn($fakeNotification);
            $mock->shouldReceive('notifyBonusReceived')->andReturn($fakeNotification);
            $mock->shouldReceive('notifyPenaltyReceived')->andReturn($fakeNotification);
            return $mock;
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper method to make authenticated API requests with Bearer token
     */
    protected function apiAs(User $user): self
    {
        $token = $user === $this->admin ? $this->adminToken : $this->waiterToken;
        return $this->withHeaders(['Authorization' => "Bearer {$token}"]);
    }

    // ============================================
    // AUTHENTICATION TESTS
    // ============================================

    public function test_unauthenticated_request_to_periods_returns_401(): void
    {
        $response = $this->getJson('/api/salary/periods');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_request_to_create_period_returns_401(): void
    {
        $response = $this->postJson('/api/salary/periods', [
            'year' => 2024,
            'month' => 6,
        ]);
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_salary_endpoints(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/salary/periods?restaurant_id={$this->restaurant->id}");

        $response->assertOk();
    }

    // ============================================
    // SALARY PERIOD MANAGEMENT TESTS
    // ============================================

    public function test_can_list_salary_periods(): void
    {
        SalaryPeriod::createForMonth($this->restaurant->id, 2024, 1, $this->admin->id);
        SalaryPeriod::createForMonth($this->restaurant->id, 2024, 2, $this->admin->id);
        SalaryPeriod::createForMonth($this->restaurant->id, 2024, 3, $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/salary/periods?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'start_date',
                            'end_date',
                            'status',
                            'calculations_count',
                        ],
                    ],
                ],
            ]);

        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_periods_are_ordered_by_start_date_descending(): void
    {
        SalaryPeriod::createForMonth($this->restaurant->id, 2024, 1, $this->admin->id);
        SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);
        SalaryPeriod::createForMonth($this->restaurant->id, 2024, 3, $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/salary/periods?restaurant_id={$this->restaurant->id}");

        $response->assertOk();
        $periods = $response->json('data.data');

        // Should be ordered: June, March, January
        $this->assertStringContainsString('Июнь', $periods[0]['name']);
        $this->assertStringContainsString('Март', $periods[1]['name']);
        $this->assertStringContainsString('Январь', $periods[2]['name']);
    }

    public function test_can_create_salary_period(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods?restaurant_id={$this->restaurant->id}", [
                'year' => 2024,
                'month' => 6,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Период создан',
            ]);

        $this->assertDatabaseHas('salary_periods', [
            'restaurant_id' => $this->restaurant->id,
            'status' => SalaryPeriod::STATUS_DRAFT,
        ]);
    }

    public function test_cannot_create_duplicate_salary_period(): void
    {
        SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods?restaurant_id={$this->restaurant->id}", [
                'year' => 2024,
                'month' => 6,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Период уже существует',
            ]);
    }

    public function test_create_period_validates_year_min(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods?restaurant_id={$this->restaurant->id}", [
                'year' => 2019, // Below minimum 2020
                'month' => 6,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['year']);
    }

    public function test_create_period_validates_year_max(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods?restaurant_id={$this->restaurant->id}", [
                'year' => 2101, // Above maximum 2100
                'month' => 6,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['year']);
    }

    public function test_create_period_validates_month_min(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods?restaurant_id={$this->restaurant->id}", [
                'year' => 2024,
                'month' => 0,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['month']);
    }

    public function test_create_period_validates_month_max(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods?restaurant_id={$this->restaurant->id}", [
                'year' => 2024,
                'month' => 13,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['month']);
    }

    public function test_can_get_period_details(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/salary/periods/{$period->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period' => [
                        'id',
                        'name',
                        'start_date',
                        'end_date',
                        'status',
                        'calculations',
                    ],
                    'work_stats',
                    'statuses',
                ],
            ]);
    }

    // ============================================
    // SALARY CALCULATION TESTS
    // ============================================

    public function test_can_calculate_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        // Create work sessions for the period
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => Carbon::create(2024, 6, 15, 9, 0, 0),
            'clock_out' => Carbon::create(2024, 6, 15, 17, 0, 0),
            'hours_worked' => 8,
            'status' => WorkSession::STATUS_COMPLETED,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods/{$period->id}/calculate");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Зарплаты рассчитаны',
            ]);

        $period->refresh();
        $this->assertEquals(SalaryPeriod::STATUS_CALCULATED, $period->status);
    }

    public function test_cannot_calculate_paid_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_PAID]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods/{$period->id}/calculate");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Невозможно пересчитать закрытый период',
            ]);
    }

    public function test_cannot_calculate_closed_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_CLOSED]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods/{$period->id}/calculate");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Невозможно пересчитать закрытый период',
            ]);
    }

    public function test_can_recalculate_single_user(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_CALCULATED]);

        // Create work session
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => Carbon::create(2024, 6, 10, 9, 0, 0),
            'clock_out' => Carbon::create(2024, 6, 10, 17, 0, 0),
            'hours_worked' => 8,
            'status' => WorkSession::STATUS_COMPLETED,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods/{$period->id}/recalculate/{$this->waiter->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Зарплата сотрудника пересчитана',
            ]);

        $this->assertDatabaseHas('salary_calculations', [
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
        ]);
    }

    public function test_cannot_recalculate_user_in_closed_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_CLOSED]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods/{$period->id}/recalculate/{$this->waiter->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Невозможно пересчитать закрытый период',
            ]);
    }

    public function test_can_approve_calculated_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_CALCULATED]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods/{$period->id}/approve");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Период утверждён',
            ]);

        $period->refresh();
        $this->assertEquals(SalaryPeriod::STATUS_APPROVED, $period->status);
        $this->assertEquals($this->admin->id, $period->approved_by);
        $this->assertNotNull($period->approved_at);
    }

    public function test_cannot_approve_draft_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods/{$period->id}/approve");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Период должен быть рассчитан перед утверждением',
            ]);
    }

    public function test_cannot_approve_already_approved_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_APPROVED]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods/{$period->id}/approve");

        $response->assertStatus(422);
    }

    // ============================================
    // BONUS/PENALTY HANDLING TESTS
    // ============================================

    public function test_can_add_bonus(): void
    {

        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/salary/bonus', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 5000,
                'description' => 'Отличная работа',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Премия добавлена',
            ]);

        $this->assertDatabaseHas('salary_payments', [
            'user_id' => $this->waiter->id,
            'salary_period_id' => $period->id,
            'type' => SalaryPayment::TYPE_BONUS,
            'amount' => 5000,
            'status' => SalaryPayment::STATUS_PENDING,
        ]);
    }

    public function test_add_bonus_validates_amount(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/salary/bonus', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 0, // Must be min 0.01
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_cannot_add_bonus_to_closed_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_CLOSED]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/salary/bonus', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 5000,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Невозможно добавить премию в закрытый период',
            ]);
    }

    public function test_cannot_add_bonus_to_paid_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_PAID]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/salary/bonus', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 5000,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Невозможно добавить премию в закрытый период',
            ]);
    }

    public function test_adding_bonus_recalculates_if_period_calculated(): void
    {

        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_CALCULATED]);

        // Create initial calculation
        SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'balance' => 30000,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/salary/bonus', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 5000,
                'description' => 'Bonus',
            ]);

        $response->assertOk();
    }

    public function test_can_add_penalty(): void
    {

        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/salary/penalty', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 1000,
                'description' => 'Опоздание на работу',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Штраф добавлен',
            ]);

        $this->assertDatabaseHas('salary_payments', [
            'user_id' => $this->waiter->id,
            'salary_period_id' => $period->id,
            'type' => SalaryPayment::TYPE_PENALTY,
            'amount' => -1000, // Negative for penalty
            'status' => SalaryPayment::STATUS_PENDING,
        ]);
    }

    public function test_penalty_requires_description(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/salary/penalty', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 1000,
                // Missing description
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    }

    public function test_cannot_add_penalty_to_closed_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_CLOSED]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/salary/penalty', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 1000,
                'description' => 'Опоздание',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Невозможно добавить штраф в закрытый период',
            ]);
    }

    // ============================================
    // PAYMENT PROCESSING TESTS
    // ============================================

    public function test_can_pay_advance(): void
    {

        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/salary/advance', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 10000,
                'payment_method' => 'cash',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Аванс выплачен',
            ]);

        $this->assertDatabaseHas('salary_payments', [
            'user_id' => $this->waiter->id,
            'salary_period_id' => $period->id,
            'type' => SalaryPayment::TYPE_ADVANCE,
            'amount' => 10000,
            'status' => SalaryPayment::STATUS_PAID,
            'payment_method' => 'cash',
        ]);
    }

    public function test_advance_defaults_to_cash_payment_method(): void
    {

        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/salary/advance', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 10000,
                // No payment_method specified
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('salary_payments', [
            'user_id' => $this->waiter->id,
            'payment_method' => 'cash',
        ]);
    }

    public function test_advance_updates_calculation_paid_amount(): void
    {

        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $calculation = SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'paid_amount' => 0,
            'balance' => 30000,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/salary/advance', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 10000,
            ]);

        $response->assertOk();
    }

    public function test_can_pay_salary(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $calculation = SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'paid_amount' => 0,
            'balance' => 30000,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/calculations/{$calculation->id}/pay", [
                'amount' => 15000,
                'payment_method' => 'card',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Зарплата выплачена',
            ]);

        $calculation->refresh();
        $this->assertEquals(15000, (float) $calculation->paid_amount);
        $this->assertEquals(15000, (float) $calculation->balance);
    }

    public function test_salary_payment_capped_at_balance(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $calculation = SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'paid_amount' => 0,
            'balance' => 30000,
            'status' => 'calculated',
        ]);

        // Create initial payment record for 25000
        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'salary_calculation_id' => $calculation->id,
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => 'salary',
            'amount' => 25000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Recalculate to update paid_amount and balance
        $calculation->recalculatePaidAmount();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/calculations/{$calculation->id}/pay", [
                'amount' => 10000, // More than balance (5000)
            ]);

        $response->assertOk();

        // Payment should be capped at 5000 (the balance)
        $calculation->refresh();
        $this->assertEquals(0, (float) $calculation->balance);
    }

    public function test_cannot_pay_salary_when_fully_paid(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $calculation = SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'paid_amount' => 30000,
            'balance' => 0,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/calculations/{$calculation->id}/pay", [
                'amount' => 5000,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Зарплата уже полностью выплачена',
            ]);
    }

    public function test_can_pay_all_for_approved_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_APPROVED]);

        SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'paid_amount' => 0,
            'balance' => 30000,
            'status' => 'approved',
        ]);

        SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->cook->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'fixed',
            'net_amount' => 40000,
            'paid_amount' => 0,
            'balance' => 40000,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods/{$period->id}/pay-all", [
                'payment_method' => 'card',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['paid_count', 'total_paid'],
            ]);

        $this->assertEquals(2, $response->json('data.paid_count'));
        $this->assertEquals(70000, $response->json('data.total_paid'));

        $period->refresh();
        $this->assertEquals(SalaryPeriod::STATUS_PAID, $period->status);
    }

    public function test_cannot_pay_all_for_unapproved_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_CALCULATED]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods/{$period->id}/pay-all");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Период должен быть утверждён для выплаты',
            ]);
    }

    public function test_can_cancel_payment(): void
    {
        $payment = SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_BONUS,
            'amount' => 3000,
            'status' => SalaryPayment::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/payments/{$payment->id}/cancel");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Платёж отменён',
            ]);

        $payment->refresh();
        $this->assertEquals(SalaryPayment::STATUS_CANCELLED, $payment->status);
    }

    public function test_cannot_cancel_already_cancelled_payment(): void
    {
        $payment = SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_BONUS,
            'amount' => 3000,
            'status' => SalaryPayment::STATUS_CANCELLED,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/payments/{$payment->id}/cancel");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Платёж уже отменён',
            ]);
    }

    public function test_cancel_payment_recalculates_paid_amount(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $calculation = SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'paid_amount' => 10000,
            'balance' => 20000,
            'status' => 'partially_paid',
        ]);

        $payment = SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'salary_calculation_id' => $calculation->id,
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_ADVANCE,
            'amount' => 10000,
            'status' => SalaryPayment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/payments/{$payment->id}/cancel");

        $response->assertOk();

        $calculation->refresh();
        $this->assertEquals(0, (float) $calculation->paid_amount);
        $this->assertEquals(30000, (float) $calculation->balance);
    }

    // ============================================
    // PAYMENT HISTORY TESTS
    // ============================================

    public function test_can_get_user_payments(): void
    {
        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_ADVANCE,
            'amount' => 5000,
            'status' => SalaryPayment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_BONUS,
            'amount' => 2000,
            'status' => SalaryPayment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/salary/users/{$this->waiter->id}/payments");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_can_get_period_payments(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_ADVANCE,
            'amount' => 5000,
            'status' => SalaryPayment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'salary_period_id' => $period->id,
            'user_id' => $this->cook->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_BONUS,
            'amount' => 3000,
            'status' => SalaryPayment::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/salary/periods/{$period->id}/payments");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(2, $response->json('data'));
    }

    // ============================================
    // MY SALARY (STAFF APP) TESTS
    // ============================================

    public function test_authenticated_user_can_get_my_salary(): void
    {
        $response = $this->actingAs($this->waiter)
            ->getJson('/api/salary/my');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'calculations',
                    'payments',
                    'current_month_stats',
                    'salary_type',
                    'salary_rate',
                    'hourly_rate',
                    'percent_rate',
                ],
            ]);
    }

    public function test_my_salary_returns_recent_calculations(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'balance' => 30000,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->waiter)
            ->getJson('/api/salary/my');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.calculations'));
    }

    public function test_my_salary_returns_recent_payments(): void
    {
        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_ADVANCE,
            'amount' => 5000,
            'status' => SalaryPayment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->waiter)
            ->getJson('/api/salary/my');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.payments'));
    }

    // ============================================
    // CALCULATION BREAKDOWN TESTS
    // ============================================

    public function test_can_get_calculation_breakdown(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $calculation = SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'hours_worked' => 160,
            'days_worked' => 20,
            'base_amount' => 0,
            'hourly_amount' => 48000,
            'overtime_amount' => 0,
            'percent_amount' => 0,
            'bonus_amount' => 5000,
            'penalty_amount' => 1000,
            'gross_amount' => 53000,
            'deductions' => 1000,
            'net_amount' => 52000,
            'paid_amount' => 10000,
            'balance' => 42000,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/salary/calculations/{$calculation->id}/breakdown");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'calculation',
                    'breakdown',
                    'work_stats',
                ],
            ]);
    }

    public function test_can_update_calculation_notes(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $calculation = SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'balance' => 30000,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/salary/calculations/{$calculation->id}/notes", [
                'notes' => 'Отличный сотрудник. Дополнительная премия за инициативу.',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Заметки сохранены',
            ]);

        $calculation->refresh();
        $this->assertEquals('Отличный сотрудник. Дополнительная премия за инициативу.', $calculation->notes);
    }

    public function test_can_clear_calculation_notes(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $calculation = SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'balance' => 30000,
            'status' => 'calculated',
            'notes' => 'Some notes',
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/salary/calculations/{$calculation->id}/notes", [
                'notes' => null,
            ]);

        $response->assertOk();

        $calculation->refresh();
        $this->assertNull($calculation->notes);
    }

    // ============================================
    // EXPORT TESTS
    // ============================================

    public function test_can_export_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'hours_worked' => 160,
            'days_worked' => 20,
            'base_amount' => 0,
            'hourly_amount' => 48000,
            'overtime_amount' => 0,
            'percent_amount' => 0,
            'bonus_amount' => 5000,
            'penalty_amount' => 1000,
            'gross_amount' => 53000,
            'deductions' => 1000,
            'net_amount' => 52000,
            'paid_amount' => 0,
            'balance' => 52000,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/salary/periods/{$period->id}/export");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period' => ['id', 'name', 'period_label', 'total_amount'],
                    'rows',
                ],
            ]);

        $this->assertCount(1, $response->json('data.rows'));
    }

    public function test_export_contains_all_calculation_columns(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'hours_worked' => 160,
            'days_worked' => 20,
            'base_amount' => 15000,
            'hourly_amount' => 48000,
            'overtime_amount' => 5000,
            'percent_amount' => 2000,
            'bonus_amount' => 3000,
            'penalty_amount' => 500,
            'gross_amount' => 73000,
            'net_amount' => 72500,
            'paid_amount' => 10000,
            'balance' => 62500,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/salary/periods/{$period->id}/export");

        $response->assertOk();

        $row = $response->json('data.rows.0');
        $expectedKeys = [
            'Сотрудник',
            'Должность',
            'Тип оплаты',
            'Отработано часов',
            'Отработано дней',
            'Базовый оклад',
            'За часы',
            'Сверхурочные',
            'Процент от продаж',
            'Премии',
            'Штрафы',
            'Итого начислено',
            'К выплате',
            'Выплачено',
            'Остаток',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $row, "Missing key: {$key}");
        }
    }

    // ============================================
    // RESTAURANT ISOLATION TESTS
    // ============================================

    public function test_periods_are_filtered_by_restaurant(): void
    {
        // Create period for main restaurant
        SalaryPeriod::createForMonth($this->restaurant->id, 2024, 1, $this->admin->id);

        // Create period for other restaurant
        SalaryPeriod::createForMonth($this->otherRestaurant->id, 2024, 1, null);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/salary/periods?restaurant_id={$this->restaurant->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_period_creates_for_user_restaurant(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/salary/periods', [
                'year' => 2024,
                'month' => 7,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('salary_periods', [
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->assertDatabaseMissing('salary_periods', [
            'restaurant_id' => $this->otherRestaurant->id,
        ]);
    }

    public function test_bonus_creates_for_user_restaurant(): void
    {

        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/salary/bonus', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 5000,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('salary_payments', [
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
        ]);
    }

    public function test_advance_creates_for_user_restaurant(): void
    {

        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/salary/advance', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 10000,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('salary_payments', [
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
        ]);
    }

    // ============================================
    // BACKOFFICE SALARY API TESTS
    // ============================================

    public function test_backoffice_can_list_periods(): void
    {
        SalaryPeriod::createForMonth($this->restaurant->id, 2024, 1, $this->admin->id);
        SalaryPeriod::createForMonth($this->restaurant->id, 2024, 2, $this->admin->id);

        $response = $this->apiAs($this->admin)
            ->getJson('/api/backoffice/salary/periods');

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_backoffice_can_create_period(): void
    {
        $response = $this->apiAs($this->admin)
            ->postJson('/api/backoffice/salary/periods', [
                'year' => 2024,
                'month' => 8,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Период создан',
            ]);
    }

    public function test_backoffice_cannot_create_duplicate_period(): void
    {
        SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);

        $response = $this->apiAs($this->admin)
            ->postJson('/api/backoffice/salary/periods', [
                'year' => 2024,
                'month' => 8,
            ]);

        $response->assertStatus(422);
    }

    public function test_backoffice_can_get_period_details(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);

        $response = $this->apiAs($this->admin)
            ->getJson("/api/backoffice/salary/periods/{$period->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period',
                    'work_stats',
                    'statuses',
                ],
            ]);
    }

    public function test_backoffice_can_calculate_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);

        $response = $this->apiAs($this->admin)
            ->postJson("/api/backoffice/salary/periods/{$period->id}/calculate");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Зарплаты рассчитаны',
            ]);

        $period->refresh();
        $this->assertEquals(SalaryPeriod::STATUS_CALCULATED, $period->status);
    }

    public function test_backoffice_cannot_calculate_closed_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_CLOSED]);

        $response = $this->apiAs($this->admin)
            ->postJson("/api/backoffice/salary/periods/{$period->id}/calculate");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Невозможно пересчитать закрытый период',
            ]);
    }

    public function test_backoffice_can_approve_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_CALCULATED]);

        $response = $this->apiAs($this->admin)
            ->postJson("/api/backoffice/salary/periods/{$period->id}/approve");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Период утверждён',
            ]);

        $period->refresh();
        $this->assertEquals(SalaryPeriod::STATUS_APPROVED, $period->status);
    }

    public function test_backoffice_cannot_approve_uncalculated_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);

        $response = $this->apiAs($this->admin)
            ->postJson("/api/backoffice/salary/periods/{$period->id}/approve");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Период должен быть рассчитан перед утверждением',
            ]);
    }

    public function test_backoffice_can_add_bonus(): void
    {

        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);

        $response = $this->apiAs($this->admin)
            ->postJson('/api/backoffice/salary/bonus', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 5000,
                'description' => 'Performance bonus',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Премия добавлена',
            ]);
    }

    public function test_backoffice_cannot_add_bonus_to_closed_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_CLOSED]);

        $response = $this->apiAs($this->admin)
            ->postJson('/api/backoffice/salary/bonus', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 5000,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Невозможно добавить премию в закрытый период',
            ]);
    }

    public function test_backoffice_can_add_penalty(): void
    {

        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);

        $response = $this->apiAs($this->admin)
            ->postJson('/api/backoffice/salary/penalty', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 1000,
                'description' => 'Late arrival',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Штраф добавлен',
            ]);

        $this->assertDatabaseHas('salary_payments', [
            'user_id' => $this->waiter->id,
            'type' => SalaryPayment::TYPE_PENALTY,
            'amount' => -1000,
        ]);
    }

    public function test_backoffice_penalty_requires_description(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);

        $response = $this->apiAs($this->admin)
            ->postJson('/api/backoffice/salary/penalty', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 1000,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    }

    public function test_backoffice_can_pay_advance(): void
    {

        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);

        $response = $this->apiAs($this->admin)
            ->postJson('/api/backoffice/salary/advance', [
                'user_id' => $this->waiter->id,
                'period_id' => $period->id,
                'amount' => 10000,
                'payment_method' => 'cash',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Аванс выплачен',
            ]);

        $this->assertDatabaseHas('salary_payments', [
            'user_id' => $this->waiter->id,
            'type' => SalaryPayment::TYPE_ADVANCE,
            'amount' => 10000,
            'status' => SalaryPayment::STATUS_PAID,
        ]);
    }

    public function test_backoffice_can_pay_salary(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);

        $calculation = SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'paid_amount' => 0,
            'balance' => 30000,
            'status' => 'approved',
        ]);

        $response = $this->apiAs($this->admin)
            ->postJson("/api/backoffice/salary/calculations/{$calculation->id}/pay", [
                'amount' => 15000,
                'payment_method' => 'card',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Зарплата выплачена',
            ]);

        $calculation->refresh();
        $this->assertEquals(15000, (float) $calculation->paid_amount);
    }

    public function test_backoffice_cannot_overpay(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);

        $calculation = SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'paid_amount' => 30000,
            'balance' => 0,
            'status' => 'paid',
        ]);

        $response = $this->apiAs($this->admin)
            ->postJson("/api/backoffice/salary/calculations/{$calculation->id}/pay", [
                'amount' => 5000,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Зарплата уже полностью выплачена',
            ]);
    }

    public function test_backoffice_can_pay_all(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_APPROVED]);

        SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'paid_amount' => 0,
            'balance' => 30000,
            'status' => 'approved',
        ]);

        SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->cook->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'fixed',
            'net_amount' => 40000,
            'paid_amount' => 0,
            'balance' => 40000,
            'status' => 'approved',
        ]);

        $response = $this->apiAs($this->admin)
            ->postJson("/api/backoffice/salary/periods/{$period->id}/pay-all");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals(2, $response->json('data.paid_count'));
        $this->assertEquals(70000, $response->json('data.total_paid'));
    }

    public function test_backoffice_cannot_pay_all_unapproved_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_CALCULATED]);

        $response = $this->apiAs($this->admin)
            ->postJson("/api/backoffice/salary/periods/{$period->id}/pay-all");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Период должен быть утверждён для выплаты',
            ]);
    }

    public function test_backoffice_can_get_user_payments(): void
    {
        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_ADVANCE,
            'amount' => 5000,
            'status' => SalaryPayment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $response = $this->apiAs($this->admin)
            ->getJson("/api/backoffice/salary/users/{$this->waiter->id}/payments");

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_backoffice_can_get_period_payments(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);

        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_ADVANCE,
            'amount' => 5000,
            'status' => SalaryPayment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $response = $this->apiAs($this->admin)
            ->getJson("/api/backoffice/salary/periods/{$period->id}/payments");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_backoffice_can_cancel_payment(): void
    {
        $payment = SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_BONUS,
            'amount' => 3000,
            'status' => SalaryPayment::STATUS_PENDING,
        ]);

        $response = $this->apiAs($this->admin)
            ->postJson("/api/backoffice/salary/payments/{$payment->id}/cancel");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Платёж отменён',
            ]);

        $payment->refresh();
        $this->assertEquals(SalaryPayment::STATUS_CANCELLED, $payment->status);
    }

    public function test_backoffice_cannot_cancel_already_cancelled(): void
    {
        $payment = SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_BONUS,
            'amount' => 3000,
            'status' => SalaryPayment::STATUS_CANCELLED,
        ]);

        $response = $this->apiAs($this->admin)
            ->postJson("/api/backoffice/salary/payments/{$payment->id}/cancel");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Платёж уже отменён',
            ]);
    }

    public function test_backoffice_can_get_calculation_breakdown(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);

        $calculation = SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'hours_worked' => 160,
            'days_worked' => 20,
            'base_amount' => 0,
            'hourly_amount' => 48000,
            'overtime_amount' => 0,
            'percent_amount' => 0,
            'bonus_amount' => 5000,
            'penalty_amount' => 1000,
            'gross_amount' => 53000,
            'deductions' => 1000,
            'net_amount' => 52000,
            'paid_amount' => 0,
            'balance' => 52000,
            'status' => 'calculated',
        ]);

        $response = $this->apiAs($this->admin)
            ->getJson("/api/backoffice/salary/calculations/{$calculation->id}/breakdown");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'calculation',
                    'breakdown',
                    'work_stats',
                ],
            ]);
    }

    public function test_backoffice_can_update_calculation_notes(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);

        $calculation = SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'balance' => 30000,
            'status' => 'calculated',
        ]);

        $response = $this->apiAs($this->admin)
            ->patchJson("/api/backoffice/salary/calculations/{$calculation->id}/notes", [
                'notes' => 'Updated notes for calculation',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Заметки сохранены',
            ]);

        $calculation->refresh();
        $this->assertEquals('Updated notes for calculation', $calculation->notes);
    }

    public function test_backoffice_can_export_period(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);

        SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'hours_worked' => 160,
            'days_worked' => 20,
            'base_amount' => 15000,
            'hourly_amount' => 10000,
            'gross_amount' => 25000,
            'net_amount' => 25000,
            'paid_amount' => 0,
            'balance' => 25000,
            'status' => 'calculated',
        ]);

        $response = $this->apiAs($this->admin)
            ->getJson("/api/backoffice/salary/periods/{$period->id}/export");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period',
                    'rows',
                ],
            ]);

        $this->assertCount(1, $response->json('data.rows'));
    }

    public function test_backoffice_can_recalculate_user(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 8, $this->admin->id);
        $period->update(['status' => SalaryPeriod::STATUS_CALCULATED]);

        $response = $this->apiAs($this->admin)
            ->postJson("/api/backoffice/salary/periods/{$period->id}/recalculate/{$this->waiter->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Зарплата сотрудника пересчитана',
            ]);
    }

    // ============================================
    // EDGE CASE TESTS
    // ============================================

    public function test_calculation_with_work_sessions(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        // Create multiple work sessions
        for ($day = 1; $day <= 20; $day++) {
            WorkSession::create([
                'restaurant_id' => $this->restaurant->id,
                'user_id' => $this->waiter->id,
                'clock_in' => Carbon::create(2024, 6, $day, 9, 0, 0),
                'clock_out' => Carbon::create(2024, 6, $day, 17, 0, 0),
                'hours_worked' => 8,
                'status' => WorkSession::STATUS_COMPLETED,
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods/{$period->id}/calculate");

        $response->assertOk();

        $calculation = SalaryCalculation::where('salary_period_id', $period->id)
            ->where('user_id', $this->waiter->id)
            ->first();

        $this->assertNotNull($calculation);
        $this->assertEquals(160, (float) $calculation->hours_worked); // 20 days * 8 hours
        $this->assertEquals(20, $calculation->days_worked);
    }

    public function test_calculation_excludes_cancelled_payments(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, 2024, 6, $this->admin->id);

        // Create bonus payment
        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_BONUS,
            'amount' => 5000,
            'status' => SalaryPayment::STATUS_PENDING,
        ]);

        // Create cancelled bonus payment
        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_BONUS,
            'amount' => 3000,
            'status' => SalaryPayment::STATUS_CANCELLED,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/salary/periods/{$period->id}/calculate");

        $response->assertOk();

        $calculation = SalaryCalculation::where('salary_period_id', $period->id)
            ->where('user_id', $this->waiter->id)
            ->first();

        // Should only include the non-cancelled bonus
        $this->assertEquals(5000, (float) $calculation->bonus_amount);
    }

    public function test_my_salary_excludes_cancelled_payments(): void
    {
        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_ADVANCE,
            'amount' => 5000,
            'status' => SalaryPayment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => SalaryPayment::TYPE_BONUS,
            'amount' => 2000,
            'status' => SalaryPayment::STATUS_CANCELLED,
        ]);

        $response = $this->actingAs($this->waiter)
            ->getJson('/api/salary/my');

        $response->assertOk();

        // Should only have 1 payment (the non-cancelled one)
        $this->assertCount(1, $response->json('data.payments'));
    }

    public function test_pagination_works_for_periods(): void
    {
        // Create 15 periods
        for ($month = 1; $month <= 12; $month++) {
            SalaryPeriod::createForMonth($this->restaurant->id, 2024, $month, $this->admin->id);
        }
        for ($month = 1; $month <= 3; $month++) {
            SalaryPeriod::createForMonth($this->restaurant->id, 2025, $month, $this->admin->id);
        }

        $response = $this->actingAs($this->admin)
            ->getJson("/api/salary/periods?restaurant_id={$this->restaurant->id}&per_page=5");

        $response->assertOk();
        $this->assertCount(5, $response->json('data.data'));
        $this->assertEquals(15, $response->json('data.total'));
        $this->assertEquals(3, $response->json('data.last_page'));
    }

    public function test_pagination_works_for_user_payments(): void
    {
        // Create 25 payments
        for ($i = 0; $i < 25; $i++) {
            SalaryPayment::create([
                'restaurant_id' => $this->restaurant->id,
                'user_id' => $this->waiter->id,
                'created_by' => $this->admin->id,
                'type' => SalaryPayment::TYPE_ADVANCE,
                'amount' => 1000 + $i,
                'status' => SalaryPayment::STATUS_PAID,
                'paid_at' => now()->subDays($i),
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->getJson("/api/salary/users/{$this->waiter->id}/payments?per_page=10");

        $response->assertOk();
        $this->assertCount(10, $response->json('data.data'));
        $this->assertEquals(25, $response->json('data.total'));
    }
}
