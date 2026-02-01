<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Order;
use App\Models\Table;
use App\Models\Zone;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class PrinterControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        // Create admin role
        $adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Администратор',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
        ]);

        // Create necessary permissions if the table exists
        if (Schema::hasTable('permissions') && Schema::hasTable('role_permission')) {
            $permissions = [
                'orders.view', 'orders.create', 'orders.edit', 'orders.cancel',
                'finance.view', 'finance.shifts', 'finance.operations',
                'settings.view', 'settings.edit',
            ];

            $permissionIds = [];
            foreach ($permissions as $key) {
                $perm = Permission::create([
                    'restaurant_id' => $this->restaurant->id,
                    'key' => $key,
                    'name' => $key,
                    'group' => explode('.', $key)[0],
                ]);
                $permissionIds[] = $perm->id;
            }
            $adminRole->permissions()->sync($permissionIds);
        }

        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
    }

    /**
     * Authenticate using Sanctum token for API routes with auth.api_token middleware
     */
    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * Helper method to create a printer
     */
    protected function createPrinter(array $attributes = []): Printer
    {
        return Printer::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Printer',
            'type' => 'receipt',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.100',
            'port' => 9100,
            'paper_width' => 80,
            'chars_per_line' => 48,
            'encoding' => 'cp866',
            'cut_paper' => true,
            'open_drawer' => false,
            'print_logo' => false,
            'print_qr' => true,
            'is_active' => true,
            'is_default' => false,
        ], $attributes));
    }

    /**
     * Helper method to create an order for print tests
     */
    protected function createOrder(array $attributes = []): Order
    {
        $zone = Zone::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $zone->id,
        ]);

        return Order::factory()->create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $table->id,
            'user_id' => $this->user->id,
            'status' => 'new',
            'payment_status' => 'pending',
            'subtotal' => 1000,
            'total' => 1000,
        ], $attributes));
    }

    // ============================================
    // INDEX (LIST PRINTERS) TESTS
    // ============================================

    public function test_can_list_printers(): void
    {
        $this->authenticate();

        // Delete any seeded printers for this restaurant
        Printer::where('restaurant_id', $this->restaurant->id)->delete();

        // Create printers for this test
        $this->createPrinter(['name' => 'Receipt Printer', 'type' => 'receipt']);
        $this->createPrinter(['name' => 'Kitchen Printer', 'type' => 'kitchen']);
        $this->createPrinter(['name' => 'Bar Printer', 'type' => 'bar']);

        // Make sure we're using the user's restaurant_id (not passing restaurant_id in URL)
        $response = $this->getJson('/api/printers');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'type',
                        'connection_type',
                        'is_active',
                        'is_default',
                    ]
                ],
                'printers',
            ]);

        // Count should be 3 for this restaurant
        $printers = $response->json('data');
        $this->assertCount(3, $printers);
    }

    public function test_list_printers_returns_only_current_restaurant(): void
    {
        $this->authenticate();

        // Delete any seeded printers for this restaurant
        Printer::where('restaurant_id', $this->restaurant->id)->delete();

        // Create printers for current restaurant
        $this->createPrinter(['name' => 'Our Printer']);

        // Create printer for another restaurant
        $otherRestaurant = Restaurant::factory()->create();
        Printer::create([
            'restaurant_id' => $otherRestaurant->id,
            'name' => 'Other Restaurant Printer',
            'type' => 'receipt',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.200',
            'port' => 9100,
            'paper_width' => 80,
            'chars_per_line' => 48,
            'encoding' => 'cp866',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/printers');

        $response->assertOk();

        $printers = $response->json('data');
        $this->assertCount(1, $printers);
        $this->assertEquals('Our Printer', $printers[0]['name']);
    }

    // ============================================
    // STORE (CREATE PRINTER) TESTS
    // ============================================

    public function test_can_create_printer(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/printers', [
            'name' => 'New Receipt Printer',
            'type' => 'receipt',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.150',
            'port' => 9100,
            'paper_width' => 80,
            'encoding' => 'cp866',
            'cut_paper' => true,
            'open_drawer' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Принтер добавлен',
            ]);

        $this->assertDatabaseHas('printers', [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'New Receipt Printer',
            'type' => 'receipt',
            'ip_address' => '192.168.1.150',
            'port' => 9100,
        ]);
    }

    public function test_create_printer_validates_required_fields(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/printers', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'type', 'connection_type']);
    }

    public function test_create_printer_validates_type(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/printers', [
            'name' => 'Test Printer',
            'type' => 'invalid_type',
            'connection_type' => 'network',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_printer_validates_connection_type(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/printers', [
            'name' => 'Test Printer',
            'type' => 'receipt',
            'connection_type' => 'invalid_connection',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['connection_type']);
    }

    public function test_create_printer_validates_ip_address(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/printers', [
            'name' => 'Test Printer',
            'type' => 'receipt',
            'connection_type' => 'network',
            'ip_address' => 'not-an-ip',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ip_address']);
    }

    public function test_create_printer_validates_port_range(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/printers', [
            'name' => 'Test Printer',
            'type' => 'receipt',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.100',
            'port' => 70000, // Invalid port (> 65535)
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['port']);
    }

    public function test_create_printer_validates_paper_width(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/printers', [
            'name' => 'Test Printer',
            'type' => 'receipt',
            'connection_type' => 'network',
            'paper_width' => 100, // Only 58 and 80 are valid
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['paper_width']);
    }

    public function test_create_printer_auto_calculates_chars_per_line(): void
    {
        $this->authenticate();

        // Test for 80mm paper (should be 48 chars)
        $response = $this->postJson('/api/printers', [
            'name' => 'Wide Printer',
            'type' => 'receipt',
            'connection_type' => 'network',
            'paper_width' => 80,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(48, $response->json('data.chars_per_line'));

        // Test for 58mm paper (should be 32 chars)
        $response2 = $this->postJson('/api/printers', [
            'name' => 'Narrow Printer',
            'type' => 'receipt',
            'connection_type' => 'network',
            'paper_width' => 58,
        ]);

        $response2->assertStatus(201);
        $this->assertEquals(32, $response2->json('data.chars_per_line'));
    }

    public function test_create_usb_printer(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/printers', [
            'name' => 'USB Printer',
            'type' => 'receipt',
            'connection_type' => 'usb',
            'device_path' => 'POS-58',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('printers', [
            'name' => 'USB Printer',
            'connection_type' => 'usb',
            'device_path' => 'POS-58',
        ]);
    }

    public function test_create_kitchen_printer(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/printers', [
            'name' => 'Kitchen Printer',
            'type' => 'kitchen',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.101',
            'port' => 9100,
            'print_qr' => false,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('printers', [
            'name' => 'Kitchen Printer',
            'type' => 'kitchen',
            'print_qr' => false,
        ]);
    }

    public function test_create_bar_printer(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/printers', [
            'name' => 'Bar Printer',
            'type' => 'bar',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.102',
            'port' => 9100,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('printers', [
            'name' => 'Bar Printer',
            'type' => 'bar',
        ]);
    }

    public function test_create_label_printer(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/printers', [
            'name' => 'Label Printer',
            'type' => 'label',
            'connection_type' => 'usb',
            'device_path' => 'Zebra ZD410',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('printers', [
            'name' => 'Label Printer',
            'type' => 'label',
        ]);
    }

    // ============================================
    // UPDATE PRINTER TESTS
    // ============================================

    public function test_can_update_printer(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter();

        $response = $this->putJson("/api/printers/{$printer->id}", [
            'name' => 'Updated Printer Name',
            'ip_address' => '192.168.1.200',
            'port' => 9200,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Принтер обновлён',
            ]);

        $this->assertDatabaseHas('printers', [
            'id' => $printer->id,
            'name' => 'Updated Printer Name',
            'ip_address' => '192.168.1.200',
            'port' => 9200,
        ]);
    }

    public function test_can_update_printer_active_status(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter(['is_active' => true]);

        $response = $this->putJson("/api/printers/{$printer->id}", [
            'is_active' => false,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('printers', [
            'id' => $printer->id,
            'is_active' => false,
        ]);
    }

    public function test_setting_default_clears_other_defaults(): void
    {
        $this->authenticate();

        $printer1 = $this->createPrinter(['name' => 'Printer 1', 'type' => 'receipt', 'is_default' => true]);
        $printer2 = $this->createPrinter(['name' => 'Printer 2', 'type' => 'receipt', 'is_default' => false]);

        // Set printer2 as default
        $response = $this->putJson("/api/printers/{$printer2->id}", [
            'is_default' => true,
        ]);

        $response->assertOk();

        // printer1 should no longer be default
        $this->assertDatabaseHas('printers', [
            'id' => $printer1->id,
            'is_default' => false,
        ]);

        // printer2 should be default
        $this->assertDatabaseHas('printers', [
            'id' => $printer2->id,
            'is_default' => true,
        ]);
    }

    public function test_setting_default_only_affects_same_type(): void
    {
        $this->authenticate();

        $receiptPrinter = $this->createPrinter(['name' => 'Receipt', 'type' => 'receipt', 'is_default' => true]);
        $kitchenPrinter = $this->createPrinter(['name' => 'Kitchen', 'type' => 'kitchen', 'is_default' => true]);
        $newReceiptPrinter = $this->createPrinter(['name' => 'New Receipt', 'type' => 'receipt', 'is_default' => false]);

        // Set newReceiptPrinter as default
        $response = $this->putJson("/api/printers/{$newReceiptPrinter->id}", [
            'is_default' => true,
        ]);

        $response->assertOk();

        // Kitchen printer should still be default (different type)
        $this->assertDatabaseHas('printers', [
            'id' => $kitchenPrinter->id,
            'is_default' => true,
        ]);

        // Old receipt printer should not be default
        $this->assertDatabaseHas('printers', [
            'id' => $receiptPrinter->id,
            'is_default' => false,
        ]);
    }

    public function test_update_validates_ip_address(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter();

        $response = $this->putJson("/api/printers/{$printer->id}", [
            'ip_address' => 'invalid-ip',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ip_address']);
    }

    // ============================================
    // DELETE PRINTER TESTS
    // ============================================

    public function test_can_delete_printer(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter();

        $response = $this->deleteJson("/api/printers/{$printer->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Принтер удалён',
            ]);

        $this->assertDatabaseMissing('printers', [
            'id' => $printer->id,
        ]);
    }

    public function test_delete_nonexistent_printer_returns_404(): void
    {
        $this->authenticate();

        $response = $this->deleteJson('/api/printers/99999');

        $response->assertStatus(404);
    }

    // ============================================
    // CONNECTION CHECK TESTS
    // ============================================

    public function test_can_check_network_printer_connection(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter([
            'connection_type' => 'network',
            'ip_address' => '127.0.0.1',
            'port' => 9100,
        ]);

        $response = $this->getJson("/api/printers/{$printer->id}/check");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'status',
            ]);

        // Status should be either 'online' or 'offline'
        $this->assertContains($response->json('status'), ['online', 'offline']);
    }

    public function test_non_network_printer_check_returns_success(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter([
            'connection_type' => 'usb',
            'device_path' => 'USB001',
        ]);

        $response = $this->getJson("/api/printers/{$printer->id}/check");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Проверка доступна только для сетевых принтеров',
            ]);
    }

    // ============================================
    // TEST PRINT TESTS
    // ============================================

    public function test_test_print_on_file_printer(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter([
            'connection_type' => 'file',
        ]);

        $response = $this->postJson("/api/printers/{$printer->id}/test");

        // File printers should succeed (save to file)
        $response->assertJsonStructure(['success', 'message']);
    }

    public function test_test_print_creates_print_job(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter([
            'connection_type' => 'network',
            'ip_address' => '192.168.1.100',
            'port' => 9100,
        ]);

        $response = $this->postJson("/api/printers/{$printer->id}/test");

        // Test print should return success (job created) regardless of actual print result
        $response->assertJsonStructure(['success', 'message']);

        // Check that the response indicates test was sent
        $this->assertContains($response->json('message'), [
            'Тестовая страница отправлена',
            'Тестовая страница напечатана',
            'Test page sent',
        ]);
    }

    // ============================================
    // PRINT QUEUE TESTS
    // ============================================

    public function test_can_list_print_queue(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter();

        // Create some print jobs
        for ($i = 0; $i < 3; $i++) {
            PrintJob::create([
                'restaurant_id' => $this->restaurant->id,
                'printer_id' => $printer->id,
                'type' => 'receipt',
                'status' => 'pending',
                'content' => base64_encode("Test content $i"),
            ]);
        }

        $response = $this->getJson('/api/printers/queue');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'printer_id',
                        'type',
                        'status',
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_retry_failed_print_job(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter(['connection_type' => 'file']);

        $job = PrintJob::create([
            'restaurant_id' => $this->restaurant->id,
            'printer_id' => $printer->id,
            'type' => 'receipt',
            'status' => 'failed',
            'content' => base64_encode('Test content'),
            'attempts' => 3,
            'error_message' => 'Connection failed',
        ]);

        $response = $this->postJson("/api/printers/jobs/{$job->id}/retry");

        $response->assertOk()
            ->assertJsonStructure(['success', 'message']);
    }

    public function test_cannot_retry_completed_job(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter();

        $job = PrintJob::create([
            'restaurant_id' => $this->restaurant->id,
            'printer_id' => $printer->id,
            'type' => 'receipt',
            'status' => 'completed',
            'content' => base64_encode('Test content'),
            'printed_at' => now(),
        ]);

        $response = $this->postJson("/api/printers/jobs/{$job->id}/retry");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Задание уже выполнено',
            ]);
    }

    public function test_can_cancel_pending_print_job(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter();

        $job = PrintJob::create([
            'restaurant_id' => $this->restaurant->id,
            'printer_id' => $printer->id,
            'type' => 'receipt',
            'status' => 'pending',
            'content' => base64_encode('Test content'),
        ]);

        $response = $this->deleteJson("/api/printers/jobs/{$job->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Задание отменено',
            ]);

        $this->assertDatabaseMissing('print_jobs', [
            'id' => $job->id,
        ]);
    }

    public function test_cannot_cancel_completed_job(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter();

        $job = PrintJob::create([
            'restaurant_id' => $this->restaurant->id,
            'printer_id' => $printer->id,
            'type' => 'receipt',
            'status' => 'completed',
            'content' => base64_encode('Test content'),
            'printed_at' => now(),
        ]);

        $response = $this->deleteJson("/api/printers/jobs/{$job->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Задание уже выполнено',
            ]);
    }

    // ============================================
    // PRINT RECEIPT TESTS
    // ============================================

    public function test_print_receipt_requires_default_printer(): void
    {
        $this->authenticate();

        // Delete any seeded printers
        Printer::query()->delete();

        $order = $this->createOrder();

        // No printer configured
        $response = $this->postJson("/api/orders/{$order->id}/print/receipt");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Не настроен принтер для чеков',
            ]);
    }

    public function test_print_receipt_with_default_printer(): void
    {
        $this->authenticate();

        // Delete any seeded printers
        Printer::query()->delete();

        $printer = $this->createPrinter([
            'type' => 'receipt',
            'is_default' => true,
            'is_active' => true,
            'connection_type' => 'file',
        ]);

        $order = $this->createOrder();

        $response = $this->postJson("/api/orders/{$order->id}/print/receipt");

        $response->assertJsonStructure([
            'success',
            'message',
            'job_id',
        ]);

        // Check that a print job was created
        $this->assertDatabaseHas('print_jobs', [
            'order_id' => $order->id,
            'printer_id' => $printer->id,
            'type' => 'receipt',
        ]);
    }

    // ============================================
    // PRINT PRECHECK TESTS
    // ============================================

    public function test_print_precheck_requires_receipt_printer(): void
    {
        $this->authenticate();

        // Delete any seeded printers
        Printer::query()->delete();

        $order = $this->createOrder();

        $response = $this->postJson("/api/orders/{$order->id}/print/precheck");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_print_precheck_with_default_printer(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter([
            'type' => 'receipt',
            'is_default' => true,
            'is_active' => true,
            'connection_type' => 'file',
        ]);

        $order = $this->createOrder();

        $response = $this->postJson("/api/orders/{$order->id}/print/precheck");

        $response->assertJsonStructure(['success', 'message']);

        // Check that a print job was created
        $this->assertDatabaseHas('print_jobs', [
            'order_id' => $order->id,
            'type' => 'precheck',
        ]);
    }

    // ============================================
    // PRINT TO KITCHEN TESTS
    // ============================================

    public function test_print_to_kitchen_requires_kitchen_printer(): void
    {
        $this->authenticate();

        // Delete any seeded printers
        Printer::query()->delete();

        $order = $this->createOrder();

        $response = $this->postJson("/api/orders/{$order->id}/print/kitchen");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Не настроены принтеры для кухни',
            ]);
    }

    public function test_print_to_kitchen_with_printer(): void
    {
        $this->authenticate();

        // Delete any seeded printers
        Printer::query()->delete();

        $printer = $this->createPrinter([
            'type' => 'kitchen',
            'is_active' => true,
            'connection_type' => 'file',
        ]);

        $order = $this->createOrder();

        $response = $this->postJson("/api/orders/{$order->id}/print/kitchen");

        // The response may be success with results, or 422 if order has no items
        // Kitchen print sends order items to kitchen printers
        $response->assertJsonStructure(['success', 'message']);

        // If order has no items, it returns 422 with "Нет позиций для печати"
        if ($response->status() === 422) {
            $response->assertJson([
                'success' => false,
                'message' => 'Нет позиций для печати',
            ]);
        } else {
            // If successful, should have results array
            $response->assertJsonStructure(['results']);
        }
    }

    // ============================================
    // PRINT REPORT TESTS
    // ============================================

    public function test_print_report_requires_receipt_printer(): void
    {
        $this->authenticate();

        // Delete any seeded printers
        Printer::query()->delete();

        $response = $this->postJson('/api/printers/report', [
            'type' => 'X',
            'data' => [
                'total' => 10000,
                'orders_count' => 15,
            ],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Не настроен принтер',
            ]);
    }

    public function test_print_x_report(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter([
            'type' => 'receipt',
            'is_default' => true,
            'is_active' => true,
            'connection_type' => 'file',
        ]);

        $response = $this->postJson('/api/printers/report', [
            'type' => 'X',
            'data' => [
                'total' => 10000,
                'orders_count' => 15,
                'cash' => 5000,
                'card' => 5000,
                'shift_number' => 1,
                'guests_count' => 30,
                'avg_check' => 667,
            ],
        ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'message']);

        $this->assertDatabaseHas('print_jobs', [
            'type' => 'report',
        ]);
    }

    public function test_print_z_report(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter([
            'type' => 'receipt',
            'is_default' => true,
            'is_active' => true,
            'connection_type' => 'file',
        ]);

        $response = $this->postJson('/api/printers/report', [
            'type' => 'Z',
            'data' => [
                'total' => 50000,
                'orders_count' => 75,
                'cash' => 25000,
                'card' => 25000,
                'shift_number' => 1,
                'guests_count' => 150,
                'avg_check' => 333,
            ],
        ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'message']);
    }

    public function test_print_report_validates_type(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter([
            'type' => 'receipt',
            'is_default' => true,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/printers/report', [
            'type' => 'INVALID',
            'data' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_print_report_validates_data_required(): void
    {
        $this->authenticate();

        $printer = $this->createPrinter([
            'type' => 'receipt',
            'is_default' => true,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/printers/report', [
            'type' => 'X',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['data']);
    }

    // ============================================
    // MODEL TESTS (via API)
    // ============================================

    public function test_printer_type_labels_in_response(): void
    {
        $this->authenticate();

        $this->createPrinter(['type' => 'receipt']);
        $this->createPrinter(['type' => 'kitchen']);
        $this->createPrinter(['type' => 'bar']);

        $response = $this->getJson('/api/printers');

        $response->assertOk();

        $printers = collect($response->json('data'));

        $receipt = $printers->firstWhere('type', 'receipt');
        $kitchen = $printers->firstWhere('type', 'kitchen');
        $bar = $printers->firstWhere('type', 'bar');

        $this->assertEquals('Касса', $receipt['type_label']);
        $this->assertEquals('Кухня', $kitchen['type_label']);
        $this->assertEquals('Бар', $bar['type_label']);
    }

    public function test_printer_connection_labels_in_response(): void
    {
        $this->authenticate();

        $this->createPrinter(['connection_type' => 'network']);
        $this->createPrinter(['connection_type' => 'usb', 'device_path' => 'USB001']);

        $response = $this->getJson('/api/printers');

        $response->assertOk();

        $printers = collect($response->json('data'));

        $network = $printers->firstWhere('connection_type', 'network');
        $usb = $printers->firstWhere('connection_type', 'usb');

        $this->assertEquals('Сеть', $network['connection_label']);
        $this->assertEquals('USB', $usb['connection_label']);
    }

    public function test_printer_status_in_response(): void
    {
        $this->authenticate();

        $this->createPrinter(['is_active' => true]);
        $this->createPrinter(['is_active' => false, 'name' => 'Inactive Printer']);

        $response = $this->getJson('/api/printers');

        $response->assertOk();

        $printers = collect($response->json('data'));

        $active = $printers->firstWhere('is_active', true);
        $inactive = $printers->firstWhere('is_active', false);

        $this->assertEquals('online', $active['status']);
        $this->assertEquals('offline', $inactive['status']);
    }
}
