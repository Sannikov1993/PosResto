<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Role $adminRole;
    protected Role $waiterRole;
    protected User $admin;
    protected User $waiter;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create([
            'name' => 'Test Restaurant',
            'address' => 'Test Address 123',
            'phone' => '+79991234567',
            'email' => 'test@restaurant.com',
            'settings' => [
                'round_amounts' => false,
                'timezone' => 'Europe/Moscow',
                'currency' => 'RUB',
                'business_day_ends_at' => 5,
            ],
        ]);

        // Create admin role with settings permissions
        $this->adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Administator',
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

        // Create waiter role with limited permissions
        $this->waiterRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'waiter',
            'name' => 'Waiter',
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

        // Create permissions for admin
        $adminPermissions = [
            'settings.view', 'settings.edit', 'settings.roles',
            'staff.view', 'staff.create', 'staff.edit', 'staff.delete',
            'orders.view', 'orders.create', 'orders.edit', 'orders.cancel',
            'finance.view', 'finance.shifts', 'finance.operations',
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

        // Create permissions for waiter (only view)
        $waiterPermissions = ['orders.view', 'orders.create', 'menu.view'];
        foreach ($waiterPermissions as $key) {
            $perm = Permission::firstOrCreate([
                'restaurant_id' => $this->restaurant->id,
                'key' => $key,
            ], [
                'name' => $key,
                'group' => explode('.', $key)[0],
            ]);
            $this->waiterRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        // Create admin user
        $this->admin = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Create waiter user
        $this->waiter = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'role_id' => $this->waiterRole->id,
            'is_active' => true,
        ]);

        // Set default user to admin
        $this->user = $this->admin;
    }

    /**
     * Authenticate the current user and set the Authorization header
     */
    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * Authenticate as admin user
     */
    protected function authenticateAsAdmin(): void
    {
        $this->user = $this->admin;
        $this->authenticate();
    }

    /**
     * Authenticate as waiter user
     */
    protected function authenticateAsWaiter(): void
    {
        $this->user = $this->waiter;
        $this->authenticate();
    }

    // ============================================
    // INDEX (GET ALL SETTINGS) TESTS
    // ============================================

    public function test_can_get_all_settings(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'general',
                    'integrations',
                ],
                'settings',
                'notifications',
            ]);
    }

    public function test_settings_include_restaurant_data(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings');

        $response->assertOk();

        $settings = $response->json('settings');
        $this->assertEquals('Test Restaurant', $settings['name']);
        $this->assertEquals('Test Address 123', $settings['address']);
    }

    public function test_settings_include_default_values(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings');

        $response->assertOk();

        $settings = $response->json('settings');
        $this->assertArrayHasKey('timezone', $settings);
        $this->assertArrayHasKey('currency', $settings);
        $this->assertArrayHasKey('working_hours', $settings);
    }

    public function test_settings_include_integrations_status(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'integrations' => [
                        'atol',
                        'telegram',
                        'yandex',
                        'sms',
                        'email',
                    ],
                ],
            ]);
    }

    public function test_settings_returns_restaurant_specific_data(): void
    {
        // Create a second restaurant with different settings
        $otherRestaurant = Restaurant::factory()->create([
            'name' => 'Other Restaurant',
            'address' => 'Other Address',
            'settings' => [
                'timezone' => 'Asia/Tokyo',
                'currency' => 'JPY',
            ],
        ]);

        $otherAdminRole = Role::create([
            'restaurant_id' => $otherRestaurant->id,
            'key' => 'admin',
            'name' => 'Administrator',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
        ]);

        // Create permissions for other admin
        $settingsPermission = Permission::firstOrCreate([
            'restaurant_id' => $otherRestaurant->id,
            'key' => 'settings.view',
        ], [
            'name' => 'settings.view',
            'group' => 'settings',
        ]);
        $otherAdminRole->permissions()->syncWithoutDetaching([$settingsPermission->id]);

        $otherUser = User::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'role' => 'admin',
            'role_id' => $otherAdminRole->id,
            'is_active' => true,
        ]);

        // Authenticate as the other user
        $this->user = $otherUser;
        $this->authenticate();

        // Get settings for other restaurant
        $response = $this->getJson('/api/settings');

        $response->assertOk();

        // Should return data for the other restaurant
        $settings = $response->json('settings');
        $this->assertEquals('Other Restaurant', $settings['name']);
        $this->assertEquals('Other Address', $settings['address']);
    }

    // ============================================
    // GENERAL SETTINGS TESTS
    // ============================================

    public function test_can_get_general_settings(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings/general');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'round_amounts',
                    'working_hours',
                    'timezone',
                    'currency',
                    'business_day_ends_at',
                ],
            ]);
    }

    public function test_general_settings_include_all_days(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings/general');

        $response->assertOk();

        $workingHours = $response->json('data.working_hours');
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            $this->assertArrayHasKey($day, $workingHours);
            $this->assertArrayHasKey('enabled', $workingHours[$day]);
            $this->assertArrayHasKey('open', $workingHours[$day]);
            $this->assertArrayHasKey('close', $workingHours[$day]);
        }
    }

    // ============================================
    // UPDATE SETTINGS TESTS
    // ============================================

    public function test_can_update_restaurant_name(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings', [
            'name' => 'Updated Restaurant Name',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Настройки сохранены',
            ]);

        $this->assertDatabaseHas('restaurants', [
            'id' => $this->restaurant->id,
            'name' => 'Updated Restaurant Name',
        ]);
    }

    public function test_can_update_restaurant_address(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings', [
            'address' => 'New Address 456',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('restaurants', [
            'id' => $this->restaurant->id,
            'address' => 'New Address 456',
        ]);
    }

    public function test_can_update_restaurant_contact_info(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings', [
            'phone' => '+79998887766',
            'email' => 'new@email.com',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('restaurants', [
            'id' => $this->restaurant->id,
            'phone' => '+79998887766',
            'email' => 'new@email.com',
        ]);
    }

    public function test_can_update_timezone(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings', [
            'timezone' => 'Asia/Vladivostok',
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $this->assertEquals('Asia/Vladivostok', $this->restaurant->getSetting('timezone'));
    }

    public function test_can_update_currency(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings', [
            'currency' => 'USD',
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $this->assertEquals('USD', $this->restaurant->getSetting('currency'));
    }

    public function test_can_update_round_amounts(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings', [
            'round_amounts' => true,
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $this->assertTrue($this->restaurant->getSetting('round_amounts'));
    }

    public function test_can_update_business_day_ends_at(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings', [
            'business_day_ends_at' => 6,
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $this->assertEquals(6, $this->restaurant->getSetting('business_day_ends_at'));
    }

    public function test_can_update_working_hours(): void
    {
        $this->authenticate();

        $workingHours = [
            'monday' => ['enabled' => true, 'open' => '08:00', 'close' => '22:00'],
            'tuesday' => ['enabled' => true, 'open' => '08:00', 'close' => '22:00'],
            'wednesday' => ['enabled' => true, 'open' => '08:00', 'close' => '22:00'],
            'thursday' => ['enabled' => true, 'open' => '08:00', 'close' => '22:00'],
            'friday' => ['enabled' => true, 'open' => '08:00', 'close' => '23:00'],
            'saturday' => ['enabled' => true, 'open' => '10:00', 'close' => '23:00'],
            'sunday' => ['enabled' => false, 'open' => '10:00', 'close' => '22:00'],
        ];

        $response = $this->putJson('/api/backoffice/settings', [
            'working_hours' => $workingHours,
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $savedHours = $this->restaurant->getSetting('working_hours');
        $this->assertEquals('08:00', $savedHours['monday']['open']);
        $this->assertFalse($savedHours['sunday']['enabled']);
    }

    public function test_update_validates_name_max_length(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings', [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_validates_email_format(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings', [
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_validates_business_day_ends_at_range(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings', [
            'business_day_ends_at' => 15,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['business_day_ends_at']);
    }

    // ============================================
    // ROLES TESTS
    // ============================================

    public function test_can_get_roles_list(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings/roles');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'permissions',
                        'color',
                    ],
                ],
            ]);
    }

    public function test_roles_include_all_standard_roles(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings/roles');

        $response->assertOk();

        $roles = collect($response->json('data'));
        $roleIds = $roles->pluck('id');

        $this->assertContains('admin', $roleIds);
        $this->assertContains('manager', $roleIds);
        $this->assertContains('cashier', $roleIds);
        $this->assertContains('waiter', $roleIds);
        $this->assertContains('cook', $roleIds);
        $this->assertContains('courier', $roleIds);
    }

    // ============================================
    // STAFF WITH ROLES TESTS
    // ============================================

    public function test_can_get_staff_with_roles(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings/staff-roles');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'role',
                        'is_active',
                    ],
                ],
            ]);
    }

    public function test_staff_with_roles_returns_sorted_by_role(): void
    {
        $this->authenticate();

        // Create multiple users with different roles
        User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'cook',
            'is_active' => true,
        ]);

        User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/settings/staff-roles');

        $response->assertOk();

        // Should include all staff
        $this->assertGreaterThanOrEqual(4, count($response->json('data')));
    }

    // ============================================
    // UPDATE STAFF ROLE TESTS
    // ============================================

    public function test_can_update_staff_role(): void
    {
        $this->authenticate();

        $staff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
        ]);

        $response = $this->patchJson("/api/settings/staff/{$staff->id}/role", [
            'role' => 'cashier',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Роль обновлена',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'role' => 'cashier',
        ]);
    }

    public function test_update_staff_role_validates_role(): void
    {
        $this->authenticate();

        $staff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
        ]);

        $response = $this->patchJson("/api/settings/staff/{$staff->id}/role", [
            'role' => 'invalid_role',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_update_staff_role_requires_role_field(): void
    {
        $this->authenticate();

        $staff = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'waiter',
            'is_active' => true,
        ]);

        $response = $this->patchJson("/api/settings/staff/{$staff->id}/role", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    // ============================================
    // INTEGRATIONS TESTS
    // ============================================

    public function test_can_get_integrations_status(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings/integrations');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'atol' => [
                        'name',
                        'description',
                        'enabled',
                        'configured',
                    ],
                    'telegram' => [
                        'name',
                        'description',
                        'enabled',
                        'configured',
                    ],
                    'yandex' => [
                        'name',
                        'description',
                        'enabled',
                        'configured',
                    ],
                    'sms' => [
                        'name',
                        'description',
                        'enabled',
                        'configured',
                    ],
                    'email' => [
                        'name',
                        'description',
                        'enabled',
                        'configured',
                    ],
                ],
            ]);
    }

    public function test_can_check_integration_atol(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/settings/integrations/check', [
            'integration' => 'atol',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'message',
                ],
            ]);
    }

    public function test_can_check_integration_telegram(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/settings/integrations/check', [
            'integration' => 'telegram',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'message',
                ],
            ]);
    }

    public function test_can_check_integration_sms(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/settings/integrations/check', [
            'integration' => 'sms',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'message',
                ],
            ]);
    }

    public function test_can_check_integration_email(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/settings/integrations/check', [
            'integration' => 'email',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'message',
                ],
            ]);
    }

    public function test_check_integration_validates_integration_type(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/settings/integrations/check', [
            'integration' => 'invalid_integration',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['integration']);
    }

    // ============================================
    // NOTIFICATIONS SETTINGS TESTS
    // ============================================

    public function test_can_get_notification_settings(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings/notifications');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'new_order',
                    'order_ready',
                    'new_reservation',
                    'low_stock',
                    'shift_end',
                ],
            ]);
    }

    public function test_notification_settings_include_channels(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings/notifications');

        $response->assertOk();

        $newOrder = $response->json('data.new_order');
        $this->assertArrayHasKey('sound', $newOrder);
        $this->assertArrayHasKey('push', $newOrder);
    }

    public function test_can_update_notification_settings(): void
    {
        $this->authenticate();

        $settings = [
            'new_order' => [
                'sound' => false,
                'push' => true,
                'telegram' => true,
            ],
            'order_ready' => [
                'sound' => true,
                'push' => false,
                'telegram' => false,
            ],
        ];

        $response = $this->putJson('/api/settings/notifications', [
            'settings' => $settings,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Настройки уведомлений сохранены',
            ]);

        $this->restaurant->refresh();
        $savedSettings = $this->restaurant->getSetting('notifications');
        $this->assertFalse($savedSettings['new_order']['sound']);
        $this->assertTrue($savedSettings['new_order']['telegram']);
    }

    // ============================================
    // PRINT SETTINGS TESTS
    // ============================================

    public function test_can_get_print_settings(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings/print');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'auto_print_receipt',
                    'auto_print_kitchen',
                    'receipt_copies',
                    'kitchen_copies',
                    'receipt_header_name',
                    'print_logo',
                    'print_qr',
                    'show_waiter',
                    'show_table',
                    'receipt_footer_line1',
                    'kitchen_beep',
                    'precheck_title',
                ],
            ]);
    }

    public function test_can_update_print_settings(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/settings/print', [
            'auto_print_receipt' => true,
            'auto_print_kitchen' => false,
            'receipt_copies' => 2,
            'kitchen_copies' => 1,
            'receipt_header_name' => 'My Restaurant',
            'print_logo' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Настройки печати сохранены',
            ]);

        $this->restaurant->refresh();
        $printSettings = $this->restaurant->getSetting('print');
        $this->assertTrue($printSettings['auto_print_receipt']);
        $this->assertFalse($printSettings['auto_print_kitchen']);
        $this->assertEquals(2, $printSettings['receipt_copies']);
    }

    public function test_update_print_settings_validates_receipt_copies(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/settings/print', [
            'receipt_copies' => 10, // Max is 5
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['receipt_copies']);
    }

    public function test_update_print_settings_validates_kitchen_copies(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/settings/print', [
            'kitchen_copies' => 0, // Min is 1
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['kitchen_copies']);
    }

    public function test_can_update_receipt_header(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/settings/print', [
            'receipt_header_name' => 'Restaurant Name',
            'receipt_header_address' => 'Restaurant Address',
            'receipt_header_phone' => '+79991234567',
            'receipt_header_inn' => '1234567890',
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $printSettings = $this->restaurant->getSetting('print');
        $this->assertEquals('Restaurant Name', $printSettings['receipt_header_name']);
        $this->assertEquals('1234567890', $printSettings['receipt_header_inn']);
    }

    public function test_can_update_receipt_footer(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/settings/print', [
            'receipt_footer_line1' => 'Thank you for your visit!',
            'receipt_footer_line2' => 'Come back soon!',
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $printSettings = $this->restaurant->getSetting('print');
        $this->assertEquals('Thank you for your visit!', $printSettings['receipt_footer_line1']);
    }

    public function test_can_update_kitchen_settings(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/settings/print', [
            'kitchen_beep' => false,
            'kitchen_large_font' => true,
            'kitchen_bold_items' => true,
            'kitchen_header_text' => 'NEW ORDER',
            'kitchen_show_table' => true,
            'kitchen_show_modifiers' => true,
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $printSettings = $this->restaurant->getSetting('print');
        $this->assertFalse($printSettings['kitchen_beep']);
        $this->assertEquals('NEW ORDER', $printSettings['kitchen_header_text']);
    }

    public function test_can_update_precheck_settings(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/settings/print', [
            'precheck_title' => 'PRE-CHECK',
            'precheck_subtitle' => '(not a fiscal document)',
            'precheck_show_table' => true,
            'precheck_footer' => 'Enjoy your meal!',
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $printSettings = $this->restaurant->getSetting('print');
        $this->assertEquals('PRE-CHECK', $printSettings['precheck_title']);
    }

    public function test_can_update_delivery_print_settings(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/settings/print', [
            'delivery_footer_line1' => 'Thank you for ordering!',
            'delivery_show_customer' => true,
            'delivery_show_phone' => true,
            'delivery_show_address' => true,
            'delivery_show_courier' => true,
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $printSettings = $this->restaurant->getSetting('print');
        $this->assertEquals('Thank you for ordering!', $printSettings['delivery_footer_line1']);
    }

    // ============================================
    // POS SETTINGS TESTS
    // ============================================

    public function test_can_get_pos_settings(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings/pos');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'theme',
                    'fontSize',
                    'tileSize',
                    'showDishPhotos',
                    'showCalories',
                    'defaultPaymentMethod',
                    'soundNewOrder',
                    'soundVolume',
                    'quickDiscounts',
                    'autoLogoutMinutes',
                ],
            ]);
    }

    public function test_pos_settings_have_default_values(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings/pos');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals('dark', $data['theme']);
        $this->assertEquals('medium', $data['fontSize']);
        $this->assertEquals('cash', $data['defaultPaymentMethod']);
        $this->assertEquals([5, 10, 15, 20], $data['quickDiscounts']);
    }

    public function test_can_update_pos_settings(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/settings/pos', [
            'theme' => 'light',
            'fontSize' => 'large',
            'showDishPhotos' => false,
            'soundVolume' => 50,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Настройки POS сохранены',
            ]);

        $this->restaurant->refresh();
        $posSettings = $this->restaurant->getSetting('pos');
        $this->assertEquals('light', $posSettings['theme']);
        $this->assertEquals('large', $posSettings['fontSize']);
        $this->assertFalse($posSettings['showDishPhotos']);
        $this->assertEquals(50, $posSettings['soundVolume']);
    }

    public function test_can_update_pos_payment_settings(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/settings/pos', [
            'defaultPaymentMethod' => 'card',
            'showChangeCalculator' => false,
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $posSettings = $this->restaurant->getSetting('pos');
        $this->assertEquals('card', $posSettings['defaultPaymentMethod']);
        $this->assertFalse($posSettings['showChangeCalculator']);
    }

    public function test_can_update_pos_security_settings(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/settings/pos', [
            'autoLogoutMinutes' => 15,
            'requirePinForCancel' => true,
            'requirePinForDiscount' => true,
            'requirePinForRefund' => true,
            'screenLockEnabled' => true,
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $posSettings = $this->restaurant->getSetting('pos');
        $this->assertEquals(15, $posSettings['autoLogoutMinutes']);
        $this->assertTrue($posSettings['requirePinForCancel']);
        $this->assertTrue($posSettings['screenLockEnabled']);
    }

    public function test_can_update_pos_delivery_settings(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/settings/pos', [
            'minDeliveryAmount' => 1000,
            'autoAssignCourier' => true,
            'showDeliveryMap' => true,
            'defaultDeliveryRadius' => 10,
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $posSettings = $this->restaurant->getSetting('pos');
        $this->assertEquals(1000, $posSettings['minDeliveryAmount']);
        $this->assertTrue($posSettings['autoAssignCourier']);
        $this->assertEquals(10, $posSettings['defaultDeliveryRadius']);
    }

    public function test_can_update_quick_discounts(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/settings/pos', [
            'quickDiscounts' => [10, 20, 30, 50],
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $posSettings = $this->restaurant->getSetting('pos');
        $this->assertEquals([10, 20, 30, 50], $posSettings['quickDiscounts']);
    }

    // ============================================
    // MANUAL DISCOUNTS SETTINGS TESTS
    // ============================================

    public function test_can_get_manual_discount_settings(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings/manual-discounts');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'preset_percentages',
                    'max_discount_without_pin',
                    'allow_custom_percent',
                    'allow_fixed_amount',
                    'require_reason',
                    'reasons',
                ],
            ]);
    }

    public function test_manual_discount_settings_have_defaults(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings/manual-discounts');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals([5, 10, 15, 20], $data['preset_percentages']);
        $this->assertEquals(20, $data['max_discount_without_pin']);
        $this->assertTrue($data['allow_custom_percent']);
    }

    public function test_manual_discount_settings_include_reasons(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/settings/manual-discounts');

        $response->assertOk();

        $reasons = $response->json('data.reasons');
        $this->assertNotEmpty($reasons);

        $reasonIds = collect($reasons)->pluck('id');
        $this->assertContains('birthday', $reasonIds);
        $this->assertContains('regular', $reasonIds);
        $this->assertContains('complaint', $reasonIds);
    }

    public function test_can_update_manual_discount_settings(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/settings/manual-discounts', [
            'preset_percentages' => [5, 10, 20, 25, 30],
            'max_discount_without_pin' => 15,
            'allow_custom_percent' => true,
            'allow_fixed_amount' => false,
            'require_reason' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Настройки скидок сохранены',
            ]);

        $this->restaurant->refresh();
        $settings = $this->restaurant->getSetting('manual_discounts');
        $this->assertEquals([5, 10, 20, 25, 30], $settings['preset_percentages']);
        $this->assertEquals(15, $settings['max_discount_without_pin']);
        $this->assertTrue($settings['require_reason']);
    }

    public function test_can_update_discount_reasons(): void
    {
        $this->authenticate();

        $reasons = [
            ['id' => 'vip', 'label' => 'VIP Guest'],
            ['id' => 'error', 'label' => 'Kitchen Error'],
            ['id' => 'promo', 'label' => 'Promotion'],
        ];

        $response = $this->putJson('/api/settings/manual-discounts', [
            'reasons' => $reasons,
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $settings = $this->restaurant->getSetting('manual_discounts');
        $this->assertEquals($reasons, $settings['reasons']);
    }

    public function test_update_manual_discount_validates_percentages(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/settings/manual-discounts', [
            'preset_percentages' => [0, 150], // 0 and 150 are invalid
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['preset_percentages.0', 'preset_percentages.1']);
    }

    public function test_update_manual_discount_validates_max_without_pin(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/settings/manual-discounts', [
            'max_discount_without_pin' => 150, // Max is 100
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['max_discount_without_pin']);
    }

    public function test_update_manual_discount_validates_reasons_structure(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/settings/manual-discounts', [
            'reasons' => [
                ['id' => 'test'], // Missing label
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reasons.0.label']);
    }

    // ============================================
    // YANDEX SETTINGS TESTS
    // ============================================

    public function test_can_get_yandex_settings(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/backoffice/settings/yandex');

        $response->assertOk()
            ->assertJsonStructure([
                'enabled',
                'api_key',
                'city',
                'restaurant_address',
                'restaurant_lat',
                'restaurant_lng',
            ]);
    }

    public function test_yandex_api_key_is_masked(): void
    {
        $this->authenticate();

        // Set a Yandex API key
        $this->restaurant->setSetting('yandex', [
            'enabled' => true,
            'api_key' => 'abcd1234efgh5678ijkl9012mnop3456',
            'city' => 'Moscow',
        ]);

        $response = $this->getJson('/api/backoffice/settings/yandex');

        $response->assertOk();

        $apiKey = $response->json('api_key');
        // API key should be masked, showing only last 8 chars
        $this->assertStringStartsWith('*', $apiKey);
        $this->assertStringEndsWith('mnop3456', $apiKey);
    }

    public function test_can_update_yandex_settings(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings/yandex', [
            'enabled' => true,
            'api_key' => 'new_api_key_12345678901234567890',
            'city' => 'Saint Petersburg',
            'restaurant_address' => 'Nevsky Prospekt 1',
            'restaurant_lat' => 59.9343,
            'restaurant_lng' => 30.3351,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Настройки Яндекс Карт сохранены',
            ]);

        $this->restaurant->refresh();
        $yandexSettings = $this->restaurant->getSetting('yandex');
        $this->assertTrue($yandexSettings['enabled']);
        $this->assertEquals('Saint Petersburg', $yandexSettings['city']);
    }

    public function test_update_yandex_validates_required_fields(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings/yandex', [
            'enabled' => true,
            // Missing required fields
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['api_key', 'restaurant_lat', 'restaurant_lng']);
    }

    public function test_update_yandex_validates_coordinates(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings/yandex', [
            'enabled' => true,
            'api_key' => 'test_key_12345678901234567890123',
            'restaurant_lat' => 'not-a-number',
            'restaurant_lng' => 'not-a-number',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['restaurant_lat', 'restaurant_lng']);
    }

    public function test_yandex_uses_existing_key_when_masked(): void
    {
        $this->authenticate();

        // Set existing key
        $this->restaurant->setSetting('yandex', [
            'enabled' => true,
            'api_key' => 'original_api_key_1234567890123456',
            'city' => 'Moscow',
            'restaurant_lat' => 55.7558,
            'restaurant_lng' => 37.6173,
        ]);

        // Update with masked key (as frontend would send)
        $response = $this->putJson('/api/backoffice/settings/yandex', [
            'enabled' => true,
            'api_key' => '****************************90123456',
            'restaurant_lat' => 55.7558,
            'restaurant_lng' => 37.6173,
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $yandexSettings = $this->restaurant->getSetting('yandex');
        // Original key should be preserved
        $this->assertEquals('original_api_key_1234567890123456', $yandexSettings['api_key']);
    }

    // ============================================
    // PERMISSION TESTS
    // ============================================

    public function test_waiter_cannot_access_settings(): void
    {
        $this->authenticateAsWaiter();

        $response = $this->getJson('/api/settings');

        $response->assertStatus(403);
    }

    public function test_waiter_cannot_update_settings(): void
    {
        $this->authenticateAsWaiter();

        // Note: The backoffice route /api/backoffice/settings doesn't have permission middleware
        // so we test the finance/settings route which does have permission middleware
        $response = $this->putJson('/api/settings/notifications', [
            'settings' => ['new_order' => ['sound' => false]],
        ]);

        $response->assertStatus(403);
    }

    public function test_waiter_cannot_update_notifications(): void
    {
        $this->authenticateAsWaiter();

        $response = $this->putJson('/api/settings/notifications', [
            'settings' => ['new_order' => ['sound' => false]],
        ]);

        $response->assertStatus(403);
    }

    public function test_waiter_cannot_update_pos_settings(): void
    {
        $this->authenticateAsWaiter();

        $response = $this->postJson('/api/settings/pos', [
            'theme' => 'light',
        ]);

        $response->assertStatus(403);
    }

    // ============================================
    // UNAUTHENTICATED TESTS
    // ============================================

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/settings');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_cannot_update_settings(): void
    {
        $response = $this->putJson('/api/backoffice/settings', [
            'name' => 'Hacked',
        ]);

        $response->assertStatus(401);
    }

    // ============================================
    // BACKOFFICE ROUTES TESTS
    // ============================================

    public function test_can_access_settings_via_backoffice_route(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/backoffice/settings');

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_can_update_settings_via_backoffice_route(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings', [
            'name' => 'Backoffice Updated Name',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('restaurants', [
            'id' => $this->restaurant->id,
            'name' => 'Backoffice Updated Name',
        ]);
    }

    public function test_can_update_notifications_via_backoffice_route(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/backoffice/settings/notifications', [
            'settings' => [
                'new_order' => ['sound' => true, 'push' => true],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Настройки уведомлений сохранены',
            ]);
    }
}
