<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\StaffSchedule;
use App\Models\ScheduleTemplate;
use App\Services\StaffNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;

class StaffScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Role $adminRole;
    protected Role $waiterRole;
    protected Role $managerRole;
    protected User $admin;
    protected User $waiter;
    protected User $cook;
    protected User $manager;
    protected string $adminToken;
    protected string $waiterToken;
    protected string $managerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        // Create admin role with full permissions
        $this->adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Администратор',
            'is_system' => true,
            'is_active' => true,
            'max_discount_percent' => 50,
            'max_refund_amount' => 10000,
            'max_cancel_amount' => 50000,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
            'can_access_kitchen' => true,
            'can_access_delivery' => true,
        ]);

        // Create manager role
        $this->managerRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'manager',
            'name' => 'Менеджер',
            'is_system' => true,
            'is_active' => true,
            'max_discount_percent' => 30,
            'max_refund_amount' => 5000,
            'max_cancel_amount' => 20000,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
            'can_access_kitchen' => false,
            'can_access_delivery' => false,
        ]);

        // Create waiter role with limited permissions
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

        // Create permissions
        $adminPermissions = [
            'staff.view', 'staff.create', 'staff.edit', 'staff.delete',
            'schedule.view', 'schedule.create', 'schedule.edit', 'schedule.delete',
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
            $this->managerRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        // Create admin user
        $this->admin = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Create manager user
        $this->manager = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'manager',
            'role_id' => $this->managerRole->id,
            'is_active' => true,
        ]);

        // Create waiter user
        $this->waiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
        ]);

        // Create cook user
        $this->cook = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'cook',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
        ]);

        // Create tokens
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->managerToken = $this->manager->createToken('test')->plainTextToken;
        $this->waiterToken = $this->waiter->createToken('test')->plainTextToken;
    }

    /**
     * Skip test if required table doesn't exist
     */
    protected function skipIfTableMissing(string $table): void
    {
        if (!Schema::hasTable($table)) {
            $this->markTestSkipped("Table '{$table}' does not exist in test database.");
        }
    }

    /**
     * Get default headers for authenticated requests
     */
    protected function authHeaders(string $token = null): array
    {
        return [
            'Authorization' => 'Bearer ' . ($token ?? $this->adminToken),
            'X-Restaurant-ID' => $this->restaurant->id,
        ];
    }

    /**
     * Create a staff schedule for testing
     */
    protected function createSchedule(array $attributes = []): StaffSchedule
    {
        return StaffSchedule::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'date' => Carbon::tomorrow(),
            'start_time' => '10:00',
            'end_time' => '18:00',
            'break_minutes' => 30,
            'status' => StaffSchedule::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    /**
     * Create a schedule template for testing
     */
    protected function createTemplate(array $attributes = []): ScheduleTemplate
    {
        return ScheduleTemplate::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Утренняя смена',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'break_minutes' => 30,
            'color' => '#f59e0b',
            'is_active' => true,
        ], $attributes));
    }

    // ============================================
    // SCHEDULE INDEX TESTS (Week Schedule Viewing)
    // ============================================

    public function test_can_get_week_schedule(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $weekStart = Carbon::now()->startOfWeek();

        // Create schedules for the week
        $this->createSchedule([
            'date' => $weekStart->copy()->addDay(),
        ]);

        $this->createSchedule([
            'user_id' => $this->cook->id,
            'date' => $weekStart->copy()->addDays(2),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/schedule');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'schedules',
                    'staff',
                    'week_start',
                    'week_end',
                    'has_drafts',
                ],
            ]);

        // Should have 7 days of schedules
        $schedules = $response->json('data.schedules');
        $this->assertCount(7, $schedules);

        // Should have drafts
        $this->assertTrue($response->json('data.has_drafts'));
    }

    public function test_can_get_schedule_for_specific_week(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $specificWeek = Carbon::parse('2026-03-02');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/schedule?week_start=' . $specificWeek->format('Y-m-d'));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'week_start' => '2026-03-02',
                ],
            ]);
    }

    public function test_schedule_includes_staff_list(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/schedule');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'staff' => [
                        '*' => ['id', 'name', 'role'],
                    ],
                ],
            ]);

        // Should only include active staff with appropriate roles
        $staff = $response->json('data.staff');
        $this->assertNotEmpty($staff);
    }

    public function test_schedule_groups_by_date(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $weekStart = Carbon::now()->startOfWeek();

        // Create multiple schedules on same day
        $this->createSchedule([
            'date' => $weekStart->copy()->addDay(),
            'start_time' => '08:00',
            'end_time' => '14:00',
        ]);

        $this->createSchedule([
            'user_id' => $this->cook->id,
            'date' => $weekStart->copy()->addDay(),
            'start_time' => '14:00',
            'end_time' => '22:00',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/schedule');

        $response->assertOk();

        $schedules = $response->json('data.schedules');
        $dateKey = $weekStart->copy()->addDay()->format('Y-m-d');

        $this->assertArrayHasKey($dateKey, $schedules);
        $this->assertCount(2, $schedules[$dateKey]);
    }

    public function test_schedule_shows_has_drafts_false_when_all_published(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $weekStart = Carbon::now()->startOfWeek();

        // Create published schedule only
        $this->createSchedule([
            'date' => $weekStart->copy()->addDay(),
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/schedule');

        $response->assertOk();
        $this->assertFalse($response->json('data.has_drafts'));
    }

    // ============================================
    // SCHEDULE STORE TESTS (Create Schedule Entry)
    // ============================================

    public function test_can_create_schedule_entry(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule', [
                'user_id' => $this->waiter->id,
                'date' => Carbon::tomorrow()->format('Y-m-d'),
                'start_time' => '10:00',
                'end_time' => '18:00',
                'break_minutes' => 30,
                'position' => 'Официант',
                'notes' => 'Утренняя смена',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Смена добавлена',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'user_id', 'date', 'start_time', 'end_time', 'user'],
            ]);

        $this->assertDatabaseHas('staff_schedules', [
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'position' => 'Официант',
            'notes' => 'Утренняя смена',
            'status' => StaffSchedule::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_create_schedule_validates_required_fields(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'date', 'start_time', 'end_time']);
    }

    public function test_create_schedule_validates_user_exists(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule', [
                'user_id' => 99999,
                'date' => Carbon::tomorrow()->format('Y-m-d'),
                'start_time' => '10:00',
                'end_time' => '18:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_create_schedule_validates_time_format(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule', [
                'user_id' => $this->waiter->id,
                'date' => Carbon::tomorrow()->format('Y-m-d'),
                'start_time' => 'invalid',
                'end_time' => '18:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time']);
    }

    public function test_create_schedule_validates_break_minutes_range(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule', [
                'user_id' => $this->waiter->id,
                'date' => Carbon::tomorrow()->format('Y-m-d'),
                'start_time' => '10:00',
                'end_time' => '18:00',
                'break_minutes' => 150, // Max is 120
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['break_minutes']);
    }

    public function test_create_schedule_with_template(): void
    {
        $this->skipIfTableMissing('staff_schedules');
        $this->skipIfTableMissing('schedule_templates');

        $template = $this->createTemplate([
            'start_time' => '08:00',
            'end_time' => '16:00',
            'break_minutes' => 45,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule', [
                'user_id' => $this->waiter->id,
                'date' => Carbon::tomorrow()->format('Y-m-d'),
                'start_time' => '10:00', // Will be overridden by template
                'end_time' => '18:00',
                'template_id' => $template->id,
            ]);

        $response->assertOk();

        // Should use template values
        $schedule = StaffSchedule::where('user_id', $this->waiter->id)->first();
        $this->assertStringContainsString('08:00', $schedule->start_time);
        $this->assertStringContainsString('16:00', $schedule->end_time);
        $this->assertEquals(45, $schedule->break_minutes);
    }

    public function test_create_schedule_detects_overlapping_shift(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $date = Carbon::tomorrow();

        // Create existing shift
        $this->createSchedule([
            'date' => $date,
            'start_time' => '10:00',
            'end_time' => '18:00',
        ]);

        // Try to create overlapping shift
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule', [
                'user_id' => $this->waiter->id,
                'date' => $date->format('Y-m-d'),
                'start_time' => '14:00',
                'end_time' => '22:00',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'У сотрудника уже есть смена в это время',
            ]);
    }

    public function test_create_schedule_allows_non_overlapping_shift(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $date = Carbon::tomorrow();

        // Create existing shift
        $this->createSchedule([
            'date' => $date,
            'start_time' => '08:00',
            'end_time' => '14:00',
        ]);

        // Create non-overlapping shift (starts at the time previous ends)
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule', [
                'user_id' => $this->waiter->id,
                'date' => $date->format('Y-m-d'),
                'start_time' => '14:00',
                'end_time' => '22:00',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_create_schedule_allows_same_time_for_different_users(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $date = Carbon::tomorrow();

        // Create schedule for waiter
        $this->createSchedule([
            'user_id' => $this->waiter->id,
            'date' => $date,
            'start_time' => '10:00',
            'end_time' => '18:00',
        ]);

        // Create schedule for cook at same time
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule', [
                'user_id' => $this->cook->id,
                'date' => $date->format('Y-m-d'),
                'start_time' => '10:00',
                'end_time' => '18:00',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_create_schedule_sets_default_break_minutes(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule', [
                'user_id' => $this->waiter->id,
                'date' => Carbon::tomorrow()->format('Y-m-d'),
                'start_time' => '10:00',
                'end_time' => '18:00',
                // No break_minutes provided
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('staff_schedules', [
            'user_id' => $this->waiter->id,
            'break_minutes' => 0,
        ]);
    }

    // ============================================
    // SCHEDULE UPDATE TESTS
    // ============================================

    public function test_can_update_schedule_entry(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $schedule = $this->createSchedule();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/schedule/{$schedule->id}", [
                'start_time' => '09:00',
                'end_time' => '17:00',
                'position' => 'Старший официант',
                'notes' => 'Обновленные заметки',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Смена обновлена',
            ]);

        $this->assertDatabaseHas('staff_schedules', [
            'id' => $schedule->id,
            'position' => 'Старший официант',
            'notes' => 'Обновленные заметки',
        ]);
    }

    public function test_can_update_schedule_user(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $schedule = $this->createSchedule([
            'user_id' => $this->waiter->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/schedule/{$schedule->id}", [
                'user_id' => $this->cook->id,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('staff_schedules', [
            'id' => $schedule->id,
            'user_id' => $this->cook->id,
        ]);
    }

    public function test_can_update_schedule_date(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $schedule = $this->createSchedule([
            'date' => Carbon::tomorrow(),
        ]);

        $newDate = Carbon::tomorrow()->addDays(2);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/schedule/{$schedule->id}", [
                'date' => $newDate->format('Y-m-d'),
            ]);

        $response->assertOk();

        $schedule->refresh();
        $this->assertEquals($newDate->format('Y-m-d'), $schedule->date->format('Y-m-d'));
    }

    public function test_update_schedule_detects_overlap_with_other_shifts(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $date = Carbon::tomorrow();

        // Create first shift
        $this->createSchedule([
            'date' => $date,
            'start_time' => '08:00',
            'end_time' => '14:00',
        ]);

        // Create second shift
        $schedule = $this->createSchedule([
            'date' => $date,
            'start_time' => '14:00',
            'end_time' => '22:00',
        ]);

        // Try to update to overlap with first
        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/schedule/{$schedule->id}", [
                'start_time' => '12:00',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'У сотрудника уже есть смена в это время',
            ]);
    }

    public function test_update_schedule_allows_same_time_when_only_entry(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $schedule = $this->createSchedule([
            'start_time' => '10:00',
            'end_time' => '18:00',
        ]);

        // Update to different time should work
        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/schedule/{$schedule->id}", [
                'start_time' => '08:00',
                'end_time' => '20:00',
            ]);

        $response->assertOk();
    }

    public function test_update_schedule_validates_partial_fields(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $schedule = $this->createSchedule();

        // Update with invalid break_minutes
        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/schedule/{$schedule->id}", [
                'break_minutes' => 200, // Max is 120
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['break_minutes']);
    }

    public function test_update_nonexistent_schedule_returns_404(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/schedule/99999', [
                'start_time' => '09:00',
            ]);

        $response->assertStatus(404);
    }

    // ============================================
    // SCHEDULE DELETE TESTS
    // ============================================

    public function test_can_delete_schedule_entry(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $schedule = $this->createSchedule();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/schedule/{$schedule->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Смена удалена',
            ]);

        $this->assertDatabaseMissing('staff_schedules', [
            'id' => $schedule->id,
        ]);
    }

    public function test_can_delete_published_schedule(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $schedule = $this->createSchedule([
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/schedule/{$schedule->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('staff_schedules', [
            'id' => $schedule->id,
        ]);
    }

    public function test_delete_nonexistent_schedule_returns_404(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/schedule/99999');

        $response->assertStatus(404);
    }

    // ============================================
    // PUBLISH WEEK TESTS
    // ============================================

    public function test_can_publish_week_schedules(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        // Mock notification service
        $this->mock(StaffNotificationService::class, function ($mock) {
            $mockNotification = Mockery::mock(\App\Models\Notification::class);
            $mock->shouldReceive('notifySchedulePublished')->andReturn($mockNotification);
        });

        $weekStart = Carbon::now()->startOfWeek();

        // Create draft schedules
        $this->createSchedule([
            'date' => $weekStart->copy()->addDay(),
            'status' => StaffSchedule::STATUS_DRAFT,
        ]);

        $this->createSchedule([
            'user_id' => $this->cook->id,
            'date' => $weekStart->copy()->addDays(2),
            'status' => StaffSchedule::STATUS_DRAFT,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule/publish', [
                'week_start' => $weekStart->format('Y-m-d'),
            ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['published_count', 'notified_users'],
            ]);

        $this->assertEquals(2, $response->json('data.published_count'));

        // Check schedules are published
        $this->assertDatabaseHas('staff_schedules', [
            'user_id' => $this->waiter->id,
            'status' => StaffSchedule::STATUS_PUBLISHED,
        ]);

        $this->assertDatabaseHas('staff_schedules', [
            'user_id' => $this->cook->id,
            'status' => StaffSchedule::STATUS_PUBLISHED,
        ]);
    }

    public function test_publish_week_fails_when_no_drafts(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $weekStart = Carbon::now()->startOfWeek();

        // Create only published schedule
        $this->createSchedule([
            'date' => $weekStart->copy()->addDay(),
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule/publish', [
                'week_start' => $weekStart->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Нет неопубликованных смен',
            ]);
    }

    public function test_publish_week_validates_required_fields(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule/publish', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['week_start']);
    }

    public function test_publish_week_notifies_affected_users(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $weekStart = Carbon::now()->startOfWeek();

        // Create draft schedules for multiple users
        $this->createSchedule([
            'user_id' => $this->waiter->id,
            'date' => $weekStart->copy()->addDay(),
            'status' => StaffSchedule::STATUS_DRAFT,
        ]);

        $this->createSchedule([
            'user_id' => $this->cook->id,
            'date' => $weekStart->copy()->addDays(2),
            'status' => StaffSchedule::STATUS_DRAFT,
        ]);

        // Mock notification service - expect 2 calls
        $this->mock(StaffNotificationService::class, function ($mock) {
            $mockNotification = Mockery::mock(\App\Models\Notification::class);
            $mock->shouldReceive('notifySchedulePublished')
                ->twice()
                ->andReturn($mockNotification);
        });

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule/publish', [
                'week_start' => $weekStart->format('Y-m-d'),
            ]);

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.notified_users'));
    }

    // ============================================
    // COPY WEEK TESTS
    // ============================================

    public function test_can_copy_week_schedules(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $fromWeek = Carbon::now()->startOfWeek();
        $toWeek = $fromWeek->copy()->addWeek();

        // Create schedules in source week
        $this->createSchedule([
            'date' => $fromWeek->copy()->addDay(),
            'start_time' => '10:00',
            'end_time' => '18:00',
            'position' => 'Официант',
        ]);

        $this->createSchedule([
            'user_id' => $this->cook->id,
            'date' => $fromWeek->copy()->addDays(2),
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule/copy-week', [
                'from_week' => $fromWeek->format('Y-m-d'),
                'to_week' => $toWeek->format('Y-m-d'),
            ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['copied_count'],
            ]);

        $this->assertEquals(2, $response->json('data.copied_count'));

        // Verify schedules were copied to target week - use whereDate for SQLite compatibility
        $this->assertTrue(
            StaffSchedule::where('user_id', $this->waiter->id)
                ->whereDate('date', $toWeek->copy()->addDay())
                ->where('status', StaffSchedule::STATUS_DRAFT)
                ->exists()
        );

        $this->assertTrue(
            StaffSchedule::where('user_id', $this->cook->id)
                ->whereDate('date', $toWeek->copy()->addDays(2))
                ->where('status', StaffSchedule::STATUS_DRAFT)
                ->exists()
        );
    }

    public function test_copy_week_validates_required_fields(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule/copy-week', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['from_week', 'to_week']);
    }

    public function test_copy_week_skips_existing_schedules(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $fromWeek = Carbon::now()->startOfWeek();
        $toWeek = $fromWeek->copy()->addWeek();

        // Create schedule in source week
        $this->createSchedule([
            'date' => $fromWeek->copy()->addDay(),
            'start_time' => '10:00',
            'end_time' => '18:00',
        ]);

        // Create schedule in target week with same time
        $this->createSchedule([
            'date' => $toWeek->copy()->addDay(),
            'start_time' => '10:00',
            'end_time' => '18:00',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule/copy-week', [
                'from_week' => $fromWeek->format('Y-m-d'),
                'to_week' => $toWeek->format('Y-m-d'),
            ]);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.copied_count'));
    }

    public function test_copy_week_creates_drafts(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $fromWeek = Carbon::now()->startOfWeek();
        $toWeek = $fromWeek->copy()->addWeek();

        // Create published schedule in source week
        $this->createSchedule([
            'date' => $fromWeek->copy()->addDay(),
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule/copy-week', [
                'from_week' => $fromWeek->format('Y-m-d'),
                'to_week' => $toWeek->format('Y-m-d'),
            ]);

        $response->assertOk();

        // Copied schedule should be draft - use whereDate for SQLite compatibility
        $this->assertTrue(
            StaffSchedule::whereDate('date', $toWeek->copy()->addDay())
                ->where('status', StaffSchedule::STATUS_DRAFT)
                ->exists()
        );
    }

    // ============================================
    // TEMPLATES TESTS
    // ============================================

    public function test_can_get_schedule_templates(): void
    {
        $this->skipIfTableMissing('schedule_templates');

        $this->createTemplate();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/schedule/templates');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'start_time', 'end_time', 'break_minutes', 'color'],
                ],
            ]);
    }

    public function test_templates_creates_defaults_if_none_exist(): void
    {
        $this->skipIfTableMissing('schedule_templates');

        // Ensure no templates exist
        ScheduleTemplate::where('restaurant_id', $this->restaurant->id)->delete();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/schedule/templates');

        $response->assertOk();

        // Should have default templates now
        $count = ScheduleTemplate::where('restaurant_id', $this->restaurant->id)->count();
        $this->assertGreaterThan(0, $count);
    }

    public function test_templates_only_returns_active_templates(): void
    {
        $this->skipIfTableMissing('schedule_templates');

        // Clear existing templates
        ScheduleTemplate::where('restaurant_id', $this->restaurant->id)->delete();

        // Create active template
        $this->createTemplate(['name' => 'Active Template', 'is_active' => true]);

        // Create inactive template
        $this->createTemplate(['name' => 'Inactive Template', 'is_active' => false]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/schedule/templates');

        $response->assertOk();

        $templates = $response->json('data');
        $names = array_column($templates, 'name');

        $this->assertContains('Active Template', $names);
        $this->assertNotContains('Inactive Template', $names);
    }

    public function test_can_create_schedule_template(): void
    {
        $this->skipIfTableMissing('schedule_templates');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule/templates', [
                'name' => 'Ночная смена',
                'start_time' => '22:00',
                'end_time' => '06:00',
                'break_minutes' => 30,
                'color' => '#8b5cf6',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Шаблон создан',
            ]);

        $this->assertDatabaseHas('schedule_templates', [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Ночная смена',
            'color' => '#8b5cf6',
        ]);
    }

    public function test_create_template_validates_required_fields(): void
    {
        $this->skipIfTableMissing('schedule_templates');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule/templates', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'start_time', 'end_time']);
    }

    public function test_create_template_sets_default_color(): void
    {
        $this->skipIfTableMissing('schedule_templates');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule/templates', [
                'name' => 'Test Template',
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('schedule_templates', [
            'name' => 'Test Template',
            'color' => '#f97316', // Default color
        ]);
    }

    public function test_can_update_schedule_template(): void
    {
        $this->skipIfTableMissing('schedule_templates');

        $template = $this->createTemplate();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/schedule/templates/{$template->id}", [
                'name' => 'Обновленный шаблон',
                'break_minutes' => 45,
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Шаблон обновлён',
            ]);

        $this->assertDatabaseHas('schedule_templates', [
            'id' => $template->id,
            'name' => 'Обновленный шаблон',
            'break_minutes' => 45,
            'is_active' => false,
        ]);
    }

    public function test_update_template_validates_fields(): void
    {
        $this->skipIfTableMissing('schedule_templates');

        $template = $this->createTemplate();

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/schedule/templates/{$template->id}", [
                'break_minutes' => 200, // Max is 120
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['break_minutes']);
    }

    public function test_can_delete_schedule_template(): void
    {
        $this->skipIfTableMissing('schedule_templates');

        $template = $this->createTemplate();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/schedule/templates/{$template->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Шаблон удалён',
            ]);

        $this->assertDatabaseMissing('schedule_templates', [
            'id' => $template->id,
        ]);
    }

    // ============================================
    // MY SCHEDULE TESTS (Personal Schedule Access)
    // ============================================

    public function test_staff_can_get_their_own_schedule(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        // Create published schedule for waiter
        $this->createSchedule([
            'user_id' => $this->waiter->id,
            'date' => Carbon::tomorrow(),
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        // Create another schedule for the day after
        $this->createSchedule([
            'user_id' => $this->waiter->id,
            'date' => Carbon::tomorrow()->addDay(),
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->waiterToken,
        ])->getJson('/api/schedule/my');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'date', 'start_time', 'end_time'],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_my_schedule_only_shows_published_schedules(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        // Create published schedule
        $this->createSchedule([
            'user_id' => $this->waiter->id,
            'date' => Carbon::tomorrow(),
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        // Create draft schedule
        $this->createSchedule([
            'user_id' => $this->waiter->id,
            'date' => Carbon::tomorrow()->addDay(),
            'status' => StaffSchedule::STATUS_DRAFT,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->waiterToken,
        ])->getJson('/api/schedule/my');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_my_schedule_only_shows_future_schedules(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        // Create past schedule
        $this->createSchedule([
            'user_id' => $this->waiter->id,
            'date' => Carbon::yesterday(),
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        // Create future schedule
        $this->createSchedule([
            'user_id' => $this->waiter->id,
            'date' => Carbon::tomorrow(),
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->waiterToken,
        ])->getJson('/api/schedule/my');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_my_schedule_respects_limit_parameter(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        // Create many schedules
        for ($i = 1; $i <= 20; $i++) {
            $this->createSchedule([
                'user_id' => $this->waiter->id,
                'date' => Carbon::today()->addDays($i),
                'status' => StaffSchedule::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->waiterToken,
        ])->getJson('/api/schedule/my?limit=5');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    public function test_my_schedule_does_not_show_other_users_schedules(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        // Create schedule for cook
        $this->createSchedule([
            'user_id' => $this->cook->id,
            'date' => Carbon::tomorrow(),
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        // Waiter should not see cook's schedule
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->waiterToken,
        ])->getJson('/api/schedule/my');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    // ============================================
    // WEEK STATS TESTS
    // ============================================

    public function test_can_get_week_stats(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $weekStart = Carbon::now()->startOfWeek();

        // Create various schedules
        $this->createSchedule([
            'user_id' => $this->waiter->id,
            'date' => $weekStart->copy()->addDay(),
            'start_time' => '10:00',
            'end_time' => '18:00',
            'break_minutes' => 30,
            'status' => StaffSchedule::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->createSchedule([
            'user_id' => $this->cook->id,
            'date' => $weekStart->copy()->addDay(),
            'start_time' => '08:00',
            'end_time' => '16:00',
            'break_minutes' => 30,
            'status' => StaffSchedule::STATUS_DRAFT,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/schedule/stats?week_start=' . $weekStart->format('Y-m-d'));

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_shifts',
                    'total_hours',
                    'draft_count',
                    'published_count',
                    'hours_by_user',
                    'hours_by_day',
                ],
            ]);

        $this->assertEquals(2, $response->json('data.total_shifts'));
        $this->assertEquals(1, $response->json('data.draft_count'));
        $this->assertEquals(1, $response->json('data.published_count'));
    }

    public function test_week_stats_includes_hours_by_user(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $weekStart = Carbon::now()->startOfWeek();

        // Create multiple schedules for one user
        $this->createSchedule([
            'user_id' => $this->waiter->id,
            'date' => $weekStart->copy()->addDay(),
            'start_time' => '10:00',
            'end_time' => '18:00',
            'break_minutes' => 30,
        ]);

        $this->createSchedule([
            'user_id' => $this->waiter->id,
            'date' => $weekStart->copy()->addDays(2),
            'start_time' => '10:00',
            'end_time' => '18:00',
            'break_minutes' => 30,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/schedule/stats?week_start=' . $weekStart->format('Y-m-d'));

        $response->assertOk();

        $hoursByUser = $response->json('data.hours_by_user');
        $this->assertNotEmpty($hoursByUser);

        // Find waiter's entry
        $waiterStats = collect($hoursByUser)->firstWhere('user.id', $this->waiter->id);
        $this->assertNotNull($waiterStats);
        $this->assertEquals(2, $waiterStats['shifts_count']);
    }

    public function test_week_stats_includes_hours_by_day(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $weekStart = Carbon::now()->startOfWeek();
        $mondayDate = $weekStart->copy()->addDay()->format('Y-m-d');

        // Create schedule for Monday
        $this->createSchedule([
            'date' => $weekStart->copy()->addDay(),
            'start_time' => '10:00',
            'end_time' => '18:00',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/schedule/stats?week_start=' . $weekStart->format('Y-m-d'));

        $response->assertOk();

        $hoursByDay = $response->json('data.hours_by_day');
        $this->assertArrayHasKey($mondayDate, $hoursByDay);
        $this->assertEquals(1, $hoursByDay[$mondayDate]['shifts_count']);
        $this->assertEquals(1, $hoursByDay[$mondayDate]['staff_count']);
    }

    // ============================================
    // AUTHENTICATION TESTS
    // ============================================

    public function test_unauthenticated_request_to_my_schedule_returns_401(): void
    {
        $response = $this->getJson('/api/schedule/my');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_schedule_index(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/schedule');

        $response->assertOk();
    }

    public function test_manager_can_manage_schedules(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        // Create schedule
        $response = $this->withHeaders($this->authHeaders($this->managerToken))
            ->postJson('/api/schedule', [
                'user_id' => $this->waiter->id,
                'date' => Carbon::tomorrow()->format('Y-m-d'),
                'start_time' => '10:00',
                'end_time' => '18:00',
            ]);

        $response->assertOk();
        $scheduleId = $response->json('data.id');

        // Update schedule
        $response = $this->withHeaders($this->authHeaders($this->managerToken))
            ->putJson("/api/schedule/{$scheduleId}", [
                'notes' => 'Updated by manager',
            ]);

        $response->assertOk();

        // Delete schedule
        $response = $this->withHeaders($this->authHeaders($this->managerToken))
            ->deleteJson("/api/schedule/{$scheduleId}");

        $response->assertOk();
    }

    // ============================================
    // MODEL CALCULATION TESTS
    // ============================================

    public function test_schedule_work_hours_calculation(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $schedule = $this->createSchedule([
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_minutes' => 60,
        ]);

        // 9 hours total - 1 hour break = 8 hours
        $this->assertEquals(9.0, $schedule->duration_hours);
        $this->assertEquals(8.0, $schedule->work_hours);
    }

    public function test_schedule_overnight_hours_calculation(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $schedule = $this->createSchedule([
            'start_time' => '22:00',
            'end_time' => '06:00',
            'break_minutes' => 30,
        ]);

        // 8 hours total - 0.5 hour break = 7.5 hours
        $this->assertEquals(8.0, $schedule->duration_hours);
        $this->assertEquals(7.5, $schedule->work_hours);
    }

    public function test_schedule_status_methods(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $schedule = $this->createSchedule([
            'status' => StaffSchedule::STATUS_DRAFT,
        ]);

        $this->assertTrue($schedule->isDraft());
        $this->assertFalse($schedule->isPublished());

        $schedule->publish();

        $this->assertFalse($schedule->isDraft());
        $this->assertTrue($schedule->isPublished());
        $this->assertNotNull($schedule->published_at);

        $schedule->unpublish();

        $this->assertTrue($schedule->isDraft());
        $this->assertFalse($schedule->isPublished());
        $this->assertNull($schedule->published_at);
    }

    public function test_schedule_time_range_format(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $schedule = $this->createSchedule([
            'start_time' => '10:00',
            'end_time' => '18:00',
        ]);

        $timeRange = $schedule->getTimeRange();
        $this->assertEquals('10:00 - 18:00', $timeRange);
    }

    public function test_schedule_overlaps_detection(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $date = Carbon::tomorrow();

        // Create existing schedule
        $this->createSchedule([
            'date' => $date,
            'start_time' => '10:00',
            'end_time' => '18:00',
        ]);

        // Create new schedule that would overlap
        $newSchedule = new StaffSchedule([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'date' => $date,
            'start_time' => '14:00',
            'end_time' => '22:00',
        ]);

        $this->assertTrue($newSchedule->overlapsWithExisting());
    }

    public function test_schedule_no_overlap_when_same_user_different_date(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        // Create existing schedule
        $this->createSchedule([
            'date' => Carbon::tomorrow(),
            'start_time' => '10:00',
            'end_time' => '18:00',
        ]);

        // Create new schedule for different date
        $newSchedule = new StaffSchedule([
            'restaurant_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'date' => Carbon::tomorrow()->addDay(),
            'start_time' => '10:00',
            'end_time' => '18:00',
        ]);

        $this->assertFalse($newSchedule->overlapsWithExisting());
    }

    // ============================================
    // TEMPLATE MODEL TESTS
    // ============================================

    public function test_template_duration_calculation(): void
    {
        $this->skipIfTableMissing('schedule_templates');

        $template = $this->createTemplate([
            'start_time' => '10:00',
            'end_time' => '22:00',
        ]);

        $this->assertEquals(12.0, $template->duration_hours);
    }

    public function test_template_overnight_duration(): void
    {
        $this->skipIfTableMissing('schedule_templates');

        $template = $this->createTemplate([
            'start_time' => '22:00',
            'end_time' => '06:00',
        ]);

        $this->assertEquals(8.0, $template->duration_hours);
    }

    public function test_template_time_range_format(): void
    {
        $this->skipIfTableMissing('schedule_templates');

        $template = $this->createTemplate([
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $this->assertEquals('08:00 - 16:00', $template->getTimeRange());
    }

    public function test_template_can_create_schedule(): void
    {
        $this->skipIfTableMissing('staff_schedules');
        $this->skipIfTableMissing('schedule_templates');

        $template = $this->createTemplate([
            'start_time' => '08:00',
            'end_time' => '16:00',
            'break_minutes' => 45,
        ]);

        $schedule = $template->createSchedule(
            $this->waiter->id,
            Carbon::tomorrow(),
            $this->admin->id
        );

        $this->assertDatabaseHas('staff_schedules', [
            'id' => $schedule->id,
            'user_id' => $this->waiter->id,
            'break_minutes' => 45,
            'status' => StaffSchedule::STATUS_DRAFT,
            'created_by' => $this->admin->id,
        ]);
    }

    // ============================================
    // EDGE CASES
    // ============================================

    public function test_empty_week_returns_empty_schedules(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/schedule');

        $response->assertOk();

        $schedules = $response->json('data.schedules');
        foreach ($schedules as $daySchedules) {
            $this->assertEmpty($daySchedules);
        }
    }

    public function test_schedule_with_zero_break_minutes(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule', [
                'user_id' => $this->waiter->id,
                'date' => Carbon::tomorrow()->format('Y-m-d'),
                'start_time' => '10:00',
                'end_time' => '18:00',
                'break_minutes' => 0,
            ]);

        $response->assertOk();

        $schedule = StaffSchedule::where('user_id', $this->waiter->id)->first();
        $this->assertEquals(0, $schedule->break_minutes);
        $this->assertEquals(8.0, $schedule->work_hours);
    }

    public function test_schedule_with_position_and_notes(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/schedule', [
                'user_id' => $this->waiter->id,
                'date' => Carbon::tomorrow()->format('Y-m-d'),
                'start_time' => '10:00',
                'end_time' => '18:00',
                'position' => 'Официант у барной стойки',
                'notes' => 'Особые инструкции для смены',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('staff_schedules', [
            'user_id' => $this->waiter->id,
            'position' => 'Официант у барной стойки',
            'notes' => 'Особые инструкции для смены',
        ]);
    }

    public function test_multiple_users_can_work_same_day(): void
    {
        $this->skipIfTableMissing('staff_schedules');

        $date = Carbon::tomorrow();

        // Create schedules for multiple users
        $users = [$this->waiter, $this->cook, $this->manager];
        foreach ($users as $user) {
            $this->createSchedule([
                'user_id' => $user->id,
                'date' => $date,
            ]);
        }

        $weekStart = $date->copy()->startOfWeek();
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/schedule?week_start=' . $weekStart->format('Y-m-d'));

        $response->assertOk();

        $dateKey = $date->format('Y-m-d');
        $schedules = $response->json('data.schedules');
        $this->assertCount(3, $schedules[$dateKey]);
    }
}
