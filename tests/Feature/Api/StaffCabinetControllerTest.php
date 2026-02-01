<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\WorkSession;
use App\Models\StaffSchedule;
use App\Models\SalaryPeriod;
use App\Models\SalaryCalculation;
use App\Models\SalaryPayment;
use App\Models\Order;
use App\Models\Tip;
use App\Models\Notification;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use App\Services\WebAuthnService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;

class StaffCabinetControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Role $waiterRole;
    protected Role $adminRole;
    protected User $waiter;
    protected User $admin;
    protected string $waiterToken;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        // Create admin role
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

        // Create Sanctum tokens
        $this->waiterToken = $this->waiter->createToken('test')->plainTextToken;
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper method to make authenticated API requests
     */
    protected function apiAs(User $user): self
    {
        $token = $user === $this->waiter ? $this->waiterToken : $this->adminToken;
        return $this->withHeaders(['Authorization' => "Bearer {$token}"]);
    }

    // ============================================
    // AUTHENTICATION TESTS
    // ============================================

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/cabinet/dashboard');

        $response->assertStatus(401);
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->getJson('/api/cabinet/dashboard');

        $response->assertStatus(401);
    }

    // ============================================
    // DASHBOARD TESTS
    // ============================================

    public function test_can_get_dashboard(): void
    {
        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/dashboard');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'role',
                        'role_label',
                        'avatar',
                        'email',
                        'phone',
                    ],
                    'today_shift',
                    'upcoming_shifts',
                    'active_session',
                    'month_stats' => [
                        'hours_worked',
                        'days_worked',
                        'avg_hours_per_day',
                    ],
                    'salary',
                    'sales',
                    'tips',
                    'unread_notifications',
                ],
            ]);
    }

    public function test_dashboard_shows_today_shift(): void
    {
        // Create a shift for today
        StaffSchedule::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/dashboard');

        $response->assertOk();
        $this->assertNotNull($response->json('data.today_shift'));
    }

    public function test_dashboard_shows_upcoming_shifts(): void
    {
        // Create shifts for the next few days
        for ($i = 1; $i <= 3; $i++) {
            StaffSchedule::create([
                'restaurant_id' => $this->restaurant->id,
                'user_id' => $this->waiter->id,
                'date' => now()->addDays($i)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '17:00',
                'status' => StaffSchedule::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);
        }

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/dashboard');

        $response->assertOk();
        $this->assertCount(3, $response->json('data.upcoming_shifts'));
    }

    public function test_dashboard_shows_active_session(): void
    {
        // Create active work session
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => now()->subHours(2),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/dashboard');

        $response->assertOk();
        $this->assertNotNull($response->json('data.active_session'));
    }

    public function test_dashboard_shows_month_stats(): void
    {
        // Create completed work sessions for this month
        // Use dates within the current month to avoid cross-month boundary issues
        $monthStart = now()->startOfMonth();

        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => $monthStart->copy()->addDays(1)->setHour(9),
            'clock_out' => $monthStart->copy()->addDays(1)->setHour(17),
            'hours_worked' => 8,
            'status' => WorkSession::STATUS_COMPLETED,
        ]);

        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => $monthStart->copy()->addDays(2)->setHour(9),
            'clock_out' => $monthStart->copy()->addDays(2)->setHour(17),
            'hours_worked' => 8,
            'status' => WorkSession::STATUS_COMPLETED,
        ]);

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/dashboard');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('data.month_stats.hours_worked'));
        $this->assertEquals(2, $response->json('data.month_stats.days_worked'));
    }

    public function test_dashboard_shows_salary_info_when_period_exists(): void
    {
        // Create salary period
        $period = SalaryPeriod::createForMonth($this->restaurant->id, now()->year, now()->month, $this->admin->id);

        // Create salary calculation
        SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 30000,
            'paid_amount' => 10000,
            'balance' => 20000,
            'status' => 'approved',
        ]);

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/dashboard');

        $response->assertOk();
        $salary = $response->json('data.salary');
        $this->assertNotNull($salary);
        $this->assertEquals(30000, $salary['net_amount']);
        $this->assertEquals(20000, $salary['balance']);
    }

    public function test_dashboard_shows_unread_notifications_count(): void
    {
        // Create unread notifications
        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test notification 1',
            'message' => 'Test message 1',
        ]);

        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test notification 2',
            'message' => 'Test message 2',
        ]);

        // Create read notification (should not be counted)
        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Read notification',
            'message' => 'Already read',
            'read_at' => now(),
        ]);

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/dashboard');

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.unread_notifications'));
    }

    // ============================================
    // SCHEDULE TESTS
    // ============================================

    public function test_can_get_my_schedule(): void
    {
        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/schedule');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'shifts',
                    'by_date',
                    'start_date',
                    'end_date',
                ],
            ]);
    }

    public function test_can_get_schedule_for_custom_date_range(): void
    {
        $startDate = now()->addDays(1)->format('Y-m-d');
        $endDate = now()->addDays(14)->format('Y-m-d');

        // Create shifts in the date range
        StaffSchedule::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '18:00',
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $response = $this->apiAs($this->waiter)
            ->getJson("/api/cabinet/schedule?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
        $this->assertEquals($startDate, $response->json('data.start_date'));
        $this->assertEquals($endDate, $response->json('data.end_date'));
    }

    public function test_schedule_shows_only_published_shifts(): void
    {
        // Create published shift
        StaffSchedule::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'date' => now()->addDays(1)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        // Create draft shift (should not be visible)
        StaffSchedule::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'date' => now()->addDays(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => StaffSchedule::STATUS_DRAFT,
        ]);

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/schedule');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.shifts'));
    }

    public function test_schedule_groups_by_date(): void
    {
        $date = now()->addDays(1)->format('Y-m-d');

        StaffSchedule::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'date' => $date,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        StaffSchedule::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'date' => $date,
            'start_time' => '14:00',
            'end_time' => '18:00',
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/schedule');

        $response->assertOk();
        $this->assertArrayHasKey($date, $response->json('data.by_date'));
        $this->assertCount(2, $response->json('data.by_date')[$date]);
    }

    // ============================================
    // TIMESHEET TESTS
    // ============================================

    public function test_can_get_my_timesheet(): void
    {
        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/timesheet');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'sessions',
                    'by_date',
                    'stats',
                    'start_date',
                    'end_date',
                ],
            ]);
    }

    public function test_can_get_timesheet_for_custom_date_range(): void
    {
        $startDate = now()->subMonth()->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->apiAs($this->waiter)
            ->getJson("/api/cabinet/timesheet?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
        $this->assertEquals($startDate, $response->json('data.start_date'));
        $this->assertEquals($endDate, $response->json('data.end_date'));
    }

    public function test_timesheet_shows_work_sessions(): void
    {
        // Create completed work sessions within current month
        $monthStart = now()->startOfMonth();

        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => $monthStart->copy()->addDays(1)->setHour(9),
            'clock_out' => $monthStart->copy()->addDays(1)->setHour(17),
            'hours_worked' => 8,
            'status' => WorkSession::STATUS_COMPLETED,
        ]);

        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => $monthStart->copy()->addDays(2)->setHour(10),
            'clock_out' => $monthStart->copy()->addDays(2)->setHour(18),
            'hours_worked' => 8,
            'status' => WorkSession::STATUS_COMPLETED,
        ]);

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/timesheet');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.sessions'));
    }

    public function test_timesheet_includes_stats(): void
    {
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => now()->subDays(1)->setHour(9),
            'clock_out' => now()->subDays(1)->setHour(17),
            'hours_worked' => 8,
            'status' => WorkSession::STATUS_COMPLETED,
        ]);

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/timesheet');

        $response->assertOk();
        $stats = $response->json('data.stats');
        $this->assertArrayHasKey('total_hours', $stats);
        $this->assertArrayHasKey('days_worked', $stats);
        $this->assertArrayHasKey('avg_hours_per_day', $stats);
    }

    // ============================================
    // CLOCK IN/OUT TESTS
    // ============================================

    public function test_can_clock_in(): void
    {
        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/clock-in');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Смена начата',
            ]);

        $this->assertDatabaseHas('work_sessions', [
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'status' => WorkSession::STATUS_ACTIVE,
        ]);
    }

    public function test_cannot_clock_in_when_already_clocked_in(): void
    {
        // Create active session
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => now()->subHours(2),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/clock-in');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Вы уже на смене',
            ]);
    }

    public function test_can_clock_out(): void
    {
        // Create active session
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => now()->subHours(4),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/clock-out');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Смена завершена',
            ]);

        $this->assertDatabaseHas('work_sessions', [
            'user_id' => $this->waiter->id,
            'status' => WorkSession::STATUS_COMPLETED,
        ]);
    }

    public function test_cannot_clock_out_when_not_clocked_in(): void
    {
        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/clock-out');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Вы не на смене',
            ]);
    }

    // ============================================
    // SALARY TESTS
    // ============================================

    public function test_can_get_my_salary(): void
    {
        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/salary');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'calculations',
                    'payments',
                    'current_info' => [
                        'salary_type',
                        'salary_type_label',
                        'base_salary',
                        'hourly_rate',
                        'percent_rate',
                    ],
                ],
            ]);
    }

    public function test_salary_shows_current_info(): void
    {
        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/salary');

        $response->assertOk();
        $currentInfo = $response->json('data.current_info');
        $this->assertEquals('hourly', $currentInfo['salary_type']);
        $this->assertEquals(30000, $currentInfo['base_salary']);
        $this->assertEquals(300, $currentInfo['hourly_rate']);
    }

    public function test_salary_shows_calculations(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, now()->year, now()->month, $this->admin->id);

        SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'net_amount' => 25000,
            'balance' => 25000,
            'status' => 'calculated',
        ]);

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/salary');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.calculations'));
    }

    public function test_salary_shows_payments(): void
    {
        SalaryPayment::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'created_by' => $this->admin->id,
            'type' => 'advance',
            'amount' => 5000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/salary');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.payments'));
    }

    public function test_can_get_salary_details(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, now()->year, now()->month, $this->admin->id);

        $calculation = SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'hourly',
            'base_amount' => 0,
            'hourly_amount' => 24000,
            'gross_amount' => 24000,
            'net_amount' => 24000,
            'balance' => 24000,
            'status' => 'calculated',
        ]);

        $response = $this->apiAs($this->waiter)
            ->getJson("/api/cabinet/salary/{$calculation->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'calculation',
                    'breakdown',
                ],
            ]);
    }

    public function test_cannot_view_other_user_salary_details(): void
    {
        $period = SalaryPeriod::createForMonth($this->restaurant->id, now()->year, now()->month, $this->admin->id);

        // Create calculation for admin
        $calculation = SalaryCalculation::create([
            'salary_period_id' => $period->id,
            'user_id' => $this->admin->id,
            'restaurant_id' => $this->restaurant->id,
            'salary_type' => 'fixed',
            'net_amount' => 50000,
            'balance' => 50000,
            'status' => 'calculated',
        ]);

        // Try to access as waiter
        $response = $this->apiAs($this->waiter)
            ->getJson("/api/cabinet/salary/{$calculation->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Доступ запрещён',
            ]);
    }

    // ============================================
    // STATS TESTS (FOR WAITERS)
    // ============================================

    public function test_can_get_my_stats(): void
    {
        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/stats');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'orders' => ['count', 'total', 'average'],
                    'orders_by_day',
                    'tips',
                    'tips_by_day',
                    'work',
                    'start_date',
                    'end_date',
                ],
            ]);
    }

    public function test_stats_can_use_custom_date_range(): void
    {
        $startDate = now()->subMonth()->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->apiAs($this->waiter)
            ->getJson("/api/cabinet/stats?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
        $this->assertEquals($startDate, $response->json('data.start_date'));
        $this->assertEquals($endDate, $response->json('data.end_date'));
    }

    // ============================================
    // PROFILE TESTS
    // ============================================

    public function test_can_get_my_profile(): void
    {
        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/profile');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->waiter->id,
                    'name' => $this->waiter->name,
                    'email' => $this->waiter->email,
                    'role' => 'waiter',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'role',
                    'role_label',
                    'avatar',
                    'hire_date',
                    'birth_date',
                    'has_password',
                    'has_pin',
                    'telegram_connected',
                    'notification_settings',
                ],
            ]);
    }

    public function test_can_update_profile(): void
    {
        $response = $this->apiAs($this->waiter)
            ->patchJson('/api/cabinet/profile', [
                'phone' => '+79991234567',
                'birth_date' => '1990-05-15',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Профиль обновлён',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->waiter->id,
            'phone' => '+79991234567',
        ]);
    }

    public function test_update_profile_validates_fields(): void
    {
        $response = $this->apiAs($this->waiter)
            ->patchJson('/api/cabinet/profile', [
                'phone' => str_repeat('1', 30), // Too long
                'birth_date' => 'invalid-date',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'birth_date']);
    }

    // ============================================
    // PIN CHANGE TESTS
    // ============================================

    public function test_can_change_pin(): void
    {
        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/profile/pin', [
                'new_pin' => '1234',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'PIN изменён',
            ]);

        $this->waiter->refresh();
        $this->assertTrue($this->waiter->verifyPin('1234'));
    }

    public function test_change_pin_validates_format(): void
    {
        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/profile/pin', [
                'new_pin' => '12', // Too short
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['new_pin']);
    }

    public function test_change_pin_requires_current_pin_when_set(): void
    {
        // Set PIN first
        $this->waiter->setPin('1111');

        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/profile/pin', [
                'current_pin' => '9999', // Wrong current PIN
                'new_pin' => '2222',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Неверный текущий PIN',
            ]);
    }

    // ============================================
    // PASSWORD CHANGE TESTS
    // ============================================

    public function test_can_change_password(): void
    {
        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/profile/password', [
                'current_password' => 'password', // Factory default
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Пароль изменён',
            ]);
    }

    public function test_change_password_validates_confirmation(): void
    {
        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/profile/password', [
                'current_password' => 'password',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'differentpassword',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_change_password_validates_min_length(): void
    {
        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/profile/password', [
                'current_password' => 'password',
                'new_password' => '123',
                'new_password_confirmation' => '123',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);
    }

    // ============================================
    // NOTIFICATION SETTINGS TESTS
    // ============================================

    public function test_can_update_notification_settings(): void
    {
        $settings = [
            'shift_reminders' => true,
            'salary_notifications' => false,
            'order_notifications' => true,
        ];

        $response = $this->apiAs($this->waiter)
            ->patchJson('/api/cabinet/profile/notifications', [
                'settings' => $settings,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Настройки сохранены',
            ]);
    }

    public function test_update_notification_settings_validates_required(): void
    {
        $response = $this->apiAs($this->waiter)
            ->patchJson('/api/cabinet/profile/notifications', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['settings']);
    }

    // ============================================
    // NOTIFICATIONS TESTS
    // ============================================

    public function test_can_get_my_notifications(): void
    {
        // Create notifications
        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SHIFT_REMINDER,
            'title' => 'Напоминание о смене',
            'message' => 'Ваша смена начинается через час',
        ]);

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/notifications');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'current_page',
                    'total',
                ],
            ]);
    }

    public function test_notifications_are_paginated(): void
    {
        // Create many notifications
        for ($i = 0; $i < 25; $i++) {
            Notification::create([
                'user_id' => $this->waiter->id,
                'restaurant_id' => $this->restaurant->id,
                'type' => Notification::TYPE_SYSTEM,
                'title' => "Notification {$i}",
                'message' => 'Test message',
            ]);
        }

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/notifications?per_page=10');

        $response->assertOk();
        $this->assertCount(10, $response->json('data.data'));
        $this->assertEquals(25, $response->json('data.total'));
    }

    public function test_can_mark_notification_as_read(): void
    {
        $notification = Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test notification',
            'message' => 'Test message',
        ]);

        $response = $this->apiAs($this->waiter)
            ->postJson("/api/cabinet/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_cannot_mark_other_user_notification_as_read(): void
    {
        // Create notification for admin
        $notification = Notification::create([
            'user_id' => $this->admin->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Admin notification',
            'message' => 'For admin only',
        ]);

        // Try to mark as read as waiter
        $response = $this->apiAs($this->waiter)
            ->postJson("/api/cabinet/notifications/{$notification->id}/read");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Доступ запрещён',
            ]);
    }

    public function test_can_mark_all_notifications_as_read(): void
    {
        // Create unread notifications
        for ($i = 0; $i < 5; $i++) {
            Notification::create([
                'user_id' => $this->waiter->id,
                'restaurant_id' => $this->restaurant->id,
                'type' => Notification::TYPE_SYSTEM,
                'title' => "Notification {$i}",
                'message' => 'Test message',
            ]);
        }

        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/notifications/read-all');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Все уведомления прочитаны',
            ]);

        // Verify all are marked as read
        $unreadCount = Notification::where('user_id', $this->waiter->id)
            ->whereNull('read_at')
            ->count();
        $this->assertEquals(0, $unreadCount);
    }

    // ============================================
    // PUSH NOTIFICATIONS TESTS
    // ============================================

    public function test_can_get_vapid_public_key(): void
    {
        // Mock WebPushService
        $this->app->bind(WebPushService::class, function () {
            $mock = Mockery::mock(WebPushService::class);
            $mock->shouldReceive('getPublicKey')->andReturn('test-public-key');
            $mock->shouldReceive('isConfigured')->andReturn(true);
            return $mock;
        });

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/push/vapid-key');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'public_key' => 'test-public-key',
                    'is_configured' => true,
                ],
            ]);
    }

    public function test_can_subscribe_to_push(): void
    {
        // Mock WebPushService
        $fakeSubscription = new PushSubscription([
            'id' => 1,
            'user_id' => $this->waiter->id,
            'endpoint' => 'https://push.example.com/test',
            'device_info' => ['device_name' => 'Test Device'],
        ]);

        $this->app->bind(WebPushService::class, function () use ($fakeSubscription) {
            $mock = Mockery::mock(WebPushService::class);
            $mock->shouldReceive('saveUserSubscription')->andReturn($fakeSubscription);
            return $mock;
        });

        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/push/subscribe', [
                'endpoint' => 'https://push.example.com/test',
                'keys' => [
                    'p256dh' => 'test-p256dh-key',
                    'auth' => 'test-auth-key',
                ],
                'device_name' => 'Test Device',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Подписка на уведомления активирована',
            ]);
    }

    public function test_subscribe_push_validates_fields(): void
    {
        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/push/subscribe', [
                'endpoint' => 'invalid-url',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }

    public function test_can_unsubscribe_from_push(): void
    {
        $this->app->bind(WebPushService::class, function () {
            $mock = Mockery::mock(WebPushService::class);
            $mock->shouldReceive('deleteSubscription')->andReturn(true);
            return $mock;
        });

        $response = $this->apiAs($this->waiter)
            ->deleteJson('/api/cabinet/push/unsubscribe', [
                'endpoint' => 'https://push.example.com/test',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Подписка отменена',
            ]);
    }

    public function test_can_test_push_notification(): void
    {
        $this->app->bind(WebPushService::class, function () {
            $mock = Mockery::mock(WebPushService::class);
            $mock->shouldReceive('sendToUser')->andReturn(1);
            return $mock;
        });

        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/push/test');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    // ============================================
    // BIOMETRIC TESTS
    // ============================================

    public function test_can_get_biometric_credentials(): void
    {
        $this->app->bind(WebAuthnService::class, function () {
            $mock = Mockery::mock(WebAuthnService::class);
            $mock->shouldReceive('getUserCredentials')->andReturn([]);
            $mock->shouldReceive('userHasBiometric')->andReturn(false);
            return $mock;
        });

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/biometric/credentials');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'credentials' => [],
                    'has_biometric' => false,
                ],
            ]);
    }

    public function test_can_get_biometric_register_options(): void
    {
        $options = [
            'challenge' => 'test-challenge',
            'rp' => ['name' => 'MenuLab', 'id' => 'localhost'],
            'user' => ['id' => base64_encode($this->waiter->id), 'name' => $this->waiter->email],
        ];

        $this->app->bind(WebAuthnService::class, function () use ($options) {
            $mock = Mockery::mock(WebAuthnService::class);
            $mock->shouldReceive('generateRegistrationOptions')->andReturn($options);
            return $mock;
        });

        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/biometric/register-options');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => $options,
            ]);
    }

    public function test_can_toggle_biometric_requirement(): void
    {
        $this->app->bind(WebAuthnService::class, function () {
            $mock = Mockery::mock(WebAuthnService::class);
            $mock->shouldReceive('userHasBiometric')->andReturn(true);
            return $mock;
        });

        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/biometric/toggle-requirement', [
                'require' => true,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Биометрия обязательна для отметок',
            ]);
    }

    public function test_cannot_require_biometric_without_credentials(): void
    {
        $this->app->bind(WebAuthnService::class, function () {
            $mock = Mockery::mock(WebAuthnService::class);
            $mock->shouldReceive('userHasBiometric')->andReturn(false);
            return $mock;
        });

        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/biometric/toggle-requirement', [
                'require' => true,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Сначала зарегистрируйте биометрию',
            ]);
    }

    // ============================================
    // CLOCK IN/OUT WITH BIOMETRIC TESTS
    // ============================================

    public function test_can_clock_in_without_biometric_when_not_required(): void
    {
        $this->waiter->update(['require_biometric_clock' => false]);

        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/clock-in-biometric');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Смена начата',
            ]);

        $this->assertDatabaseHas('work_sessions', [
            'user_id' => $this->waiter->id,
            'clock_in_verified_by' => 'manual',
            'status' => WorkSession::STATUS_ACTIVE,
        ]);
    }

    public function test_clock_in_with_biometric_requires_verification_when_enabled(): void
    {
        $this->waiter->update(['require_biometric_clock' => true]);

        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/clock-in-biometric', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['biometric']);
    }

    public function test_cannot_clock_in_biometric_when_already_clocked_in(): void
    {
        $this->waiter->update(['require_biometric_clock' => false]);

        // Create active session
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => now()->subHours(2),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/clock-in-biometric');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Вы уже на смене',
            ]);
    }

    public function test_can_clock_out_without_biometric_when_not_required(): void
    {
        $this->waiter->update(['require_biometric_clock' => false]);

        // Create active session
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => now()->subHours(4),
            'status' => WorkSession::STATUS_ACTIVE,
        ]);

        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/clock-out-biometric');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Смена завершена',
            ]);
    }

    public function test_cannot_clock_out_biometric_when_not_clocked_in(): void
    {
        $this->waiter->update(['require_biometric_clock' => false]);

        $response = $this->apiAs($this->waiter)
            ->postJson('/api/cabinet/clock-out-biometric');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Вы не на смене',
            ]);
    }

    // ============================================
    // MULTI-USER ISOLATION TESTS
    // ============================================

    public function test_users_can_only_see_their_own_schedule(): void
    {
        // Create shift for waiter
        StaffSchedule::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'date' => now()->addDays(1)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        // Create shift for admin
        StaffSchedule::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'date' => now()->addDays(1)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '18:00',
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        // Waiter should only see their own shift
        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/schedule');

        $response->assertOk();
        $shifts = $response->json('data.shifts');
        $this->assertCount(1, $shifts);
        $this->assertEquals($this->waiter->id, $shifts[0]['user_id']);
    }

    public function test_users_can_only_see_their_own_timesheet(): void
    {
        // Use dates within current month
        $monthStart = now()->startOfMonth();

        // Create work sessions for waiter
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'clock_in' => $monthStart->copy()->addDays(1)->setHour(9),
            'clock_out' => $monthStart->copy()->addDays(1)->setHour(17),
            'hours_worked' => 8,
            'status' => WorkSession::STATUS_COMPLETED,
        ]);

        // Create work session for admin
        WorkSession::create([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->admin->id,
            'clock_in' => $monthStart->copy()->addDays(1)->setHour(10),
            'clock_out' => $monthStart->copy()->addDays(1)->setHour(18),
            'hours_worked' => 8,
            'status' => WorkSession::STATUS_COMPLETED,
        ]);

        // Waiter should only see their own sessions
        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/timesheet');

        $response->assertOk();
        $sessions = $response->json('data.sessions');
        $this->assertCount(1, $sessions);
        $this->assertEquals($this->waiter->id, $sessions[0]['user_id']);
    }

    public function test_users_can_only_see_their_own_notifications(): void
    {
        // Create notification for waiter
        Notification::create([
            'user_id' => $this->waiter->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Waiter notification',
            'message' => 'For waiter',
        ]);

        // Create notification for admin
        Notification::create([
            'user_id' => $this->admin->id,
            'restaurant_id' => $this->restaurant->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Admin notification',
            'message' => 'For admin',
        ]);

        // Waiter should only see their own notification
        $response = $this->apiAs($this->waiter)
            ->getJson('/api/cabinet/notifications');

        $response->assertOk();
        $notifications = $response->json('data.data');
        $this->assertCount(1, $notifications);
        $this->assertEquals($this->waiter->id, $notifications[0]['user_id']);
    }
}
