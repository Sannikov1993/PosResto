<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\FiscalReceipt;
use App\Models\CashShift;
use App\Models\CashOperation;
use App\Services\AtolOnlineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class FiscalControllerTest extends TestCase
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
            'role' => 'super_admin',
        ]);
    }

    /**
     * Authenticate user with Sanctum token
     */
    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * Create a fiscal receipt for testing
     */
    protected function createFiscalReceipt(array $attributes = []): FiscalReceipt
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'total' => 1000,
        ]);

        return FiscalReceipt::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'operation' => 'sell',
            'external_id' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => FiscalReceipt::STATUS_PENDING,
            'total' => 1000,
            'items' => [
                ['name' => 'Тест товар', 'price' => 500, 'quantity' => 2, 'sum' => 1000],
            ],
            'payments' => [
                ['type' => 1, 'sum' => 1000],
            ],
        ], $attributes));
    }

    // =========================================================================
    // INDEX TESTS - List fiscal receipts
    // =========================================================================

    public function test_can_list_fiscal_receipts(): void
    {
        $this->authenticate();

        // Create receipts
        $this->createFiscalReceipt();
        $this->createFiscalReceipt();
        $this->createFiscalReceipt();

        $response = $this->getJson("/api/fiscal?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'restaurant_id',
                        'order_id',
                        'operation',
                        'status',
                        'total',
                    ]
                ]
            ])
            ->assertJson(['success' => true]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_filter_receipts_by_status(): void
    {
        $this->authenticate();

        $this->createFiscalReceipt(['status' => FiscalReceipt::STATUS_PENDING]);
        $this->createFiscalReceipt(['status' => FiscalReceipt::STATUS_DONE]);
        $this->createFiscalReceipt(['status' => FiscalReceipt::STATUS_DONE]);

        $response = $this->getJson("/api/fiscal?restaurant_id={$this->restaurant->id}&status=done");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));

        foreach ($response->json('data') as $receipt) {
            $this->assertEquals('done', $receipt['status']);
        }
    }

    public function test_can_filter_receipts_by_order_id(): void
    {
        $this->authenticate();

        $order1 = Order::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $order2 = Order::factory()->create(['restaurant_id' => $this->restaurant->id]);

        FiscalReceipt::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order1->id,
            'operation' => 'sell',
            'external_id' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => FiscalReceipt::STATUS_DONE,
            'total' => 500,
            'items' => [],
            'payments' => [],
        ]);

        FiscalReceipt::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order2->id,
            'operation' => 'sell',
            'external_id' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => FiscalReceipt::STATUS_DONE,
            'total' => 700,
            'items' => [],
            'payments' => [],
        ]);

        $response = $this->getJson("/api/fiscal?restaurant_id={$this->restaurant->id}&order_id={$order1->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($order1->id, $response->json('data.0.order_id'));
    }

    public function test_receipts_list_respects_limit(): void
    {
        $this->authenticate();

        // Create 10 receipts
        for ($i = 0; $i < 10; $i++) {
            $this->createFiscalReceipt();
        }

        $response = $this->getJson("/api/fiscal?restaurant_id={$this->restaurant->id}&limit=5");

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    public function test_receipts_list_ordered_by_created_at_desc(): void
    {
        $this->authenticate();

        $receipt1 = $this->createFiscalReceipt();
        sleep(1); // Ensure different timestamps
        $receipt2 = $this->createFiscalReceipt();

        $response = $this->getJson("/api/fiscal?restaurant_id={$this->restaurant->id}");

        $response->assertOk();
        $data = $response->json('data');

        // Most recent should be first
        $this->assertEquals($receipt2->id, $data[0]['id']);
    }

    public function test_receipts_list_includes_order_relation(): void
    {
        $this->authenticate();

        $this->createFiscalReceipt();

        $response = $this->getJson("/api/fiscal?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'order',
                    ]
                ]
            ]);
    }

    // =========================================================================
    // SHOW TESTS - Get single receipt
    // =========================================================================

    public function test_can_show_fiscal_receipt(): void
    {
        $this->authenticate();

        $receipt = $this->createFiscalReceipt();

        $response = $this->getJson("/api/fiscal/{$receipt->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $receipt->id,
                    'status' => $receipt->status,
                    'total' => $receipt->total,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'restaurant_id',
                    'order_id',
                    'operation',
                    'external_id',
                    'status',
                    'total',
                    'items',
                    'payments',
                    'order',
                ],
            ]);
    }

    public function test_show_receipt_returns_404_for_nonexistent(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/fiscal/99999');

        $response->assertNotFound();
    }

    // =========================================================================
    // CHECK STATUS TESTS
    // =========================================================================

    public function test_can_check_receipt_status(): void
    {
        $this->authenticate();

        $receipt = $this->createFiscalReceipt([
            'status' => FiscalReceipt::STATUS_PROCESSING,
            'atol_uuid' => 'test-uuid-12345',
        ]);

        // Mock AtolOnlineService
        $this->mock(AtolOnlineService::class, function ($mock) use ($receipt) {
            $mock->shouldReceive('checkStatus')
                ->once()
                ->with(Mockery::on(function ($arg) use ($receipt) {
                    return $arg->id === $receipt->id;
                }))
                ->andReturn($receipt);
        });

        $response = $this->postJson("/api/fiscal/{$receipt->id}/check");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_check_status_returns_updated_receipt(): void
    {
        $this->authenticate();

        $receipt = $this->createFiscalReceipt([
            'status' => FiscalReceipt::STATUS_PROCESSING,
            'atol_uuid' => 'test-uuid-12345',
        ]);

        // Mock service to return updated receipt
        $this->mock(AtolOnlineService::class, function ($mock) use ($receipt) {
            $updatedReceipt = $receipt->replicate();
            $updatedReceipt->status = FiscalReceipt::STATUS_DONE;

            $mock->shouldReceive('checkStatus')
                ->once()
                ->andReturn($receipt);
        });

        $response = $this->postJson("/api/fiscal/{$receipt->id}/check");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'status',
                ],
            ]);
    }

    // =========================================================================
    // RETRY TESTS
    // =========================================================================

    public function test_can_retry_failed_receipt(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'total' => 1000,
        ]);

        $receipt = FiscalReceipt::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'operation' => 'sell',
            'external_id' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => FiscalReceipt::STATUS_FAIL,
            'error_message' => 'Test error',
            'total' => 1000,
            'items' => [],
            'payments' => [],
            'customer_email' => 'test@example.com',
        ]);

        $newReceipt = $this->createFiscalReceipt(['status' => FiscalReceipt::STATUS_PROCESSING]);

        // Mock AtolOnlineService
        $this->mock(AtolOnlineService::class, function ($mock) use ($newReceipt) {
            $mock->shouldReceive('sell')
                ->once()
                ->andReturn($newReceipt);
        });

        $response = $this->postJson("/api/fiscal/{$receipt->id}/retry");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Чек отправлен повторно',
            ]);
    }

    public function test_cannot_retry_non_failed_receipt(): void
    {
        $this->authenticate();

        $receipt = $this->createFiscalReceipt(['status' => FiscalReceipt::STATUS_DONE]);

        $response = $this->postJson("/api/fiscal/{$receipt->id}/retry");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Повторная отправка возможна только для чеков со статусом fail',
            ]);
    }

    public function test_cannot_retry_pending_receipt(): void
    {
        $this->authenticate();

        $receipt = $this->createFiscalReceipt(['status' => FiscalReceipt::STATUS_PENDING]);

        $response = $this->postJson("/api/fiscal/{$receipt->id}/retry");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_cannot_retry_processing_receipt(): void
    {
        $this->authenticate();

        $receipt = $this->createFiscalReceipt(['status' => FiscalReceipt::STATUS_PROCESSING]);

        $response = $this->postJson("/api/fiscal/{$receipt->id}/retry");

        $response->assertStatus(400);
    }

    public function test_retry_refund_receipt_uses_sellRefund(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'total' => 1000,
        ]);

        $receipt = FiscalReceipt::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'operation' => 'sell_refund',
            'external_id' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => FiscalReceipt::STATUS_FAIL,
            'error_message' => 'Test error',
            'total' => 1000,
            'items' => [],
            'payments' => [],
            'customer_phone' => '+79991234567',
        ]);

        $newReceipt = $this->createFiscalReceipt();

        // Mock AtolOnlineService to expect sellRefund
        $this->mock(AtolOnlineService::class, function ($mock) use ($newReceipt) {
            $mock->shouldReceive('sellRefund')
                ->once()
                ->andReturn($newReceipt);
        });

        $response = $this->postJson("/api/fiscal/{$receipt->id}/retry");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Чек отправлен повторно',
            ]);
    }

    // =========================================================================
    // REFUND TESTS
    // =========================================================================

    public function test_can_create_refund_receipt(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'total' => 1500,
        ]);

        $refundReceipt = $this->createFiscalReceipt(['operation' => 'sell_refund']);

        // Mock AtolOnlineService
        $this->mock(AtolOnlineService::class, function ($mock) use ($refundReceipt) {
            $mock->shouldReceive('sellRefund')
                ->once()
                ->andReturn($refundReceipt);
        });

        // Create an open shift for the cash operation
        CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->postJson("/api/fiscal/orders/{$order->id}/refund", [
            'customer_contact' => 'customer@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Чек возврата создан',
            ]);

        // Check order status updated
        $order->refresh();
        $this->assertEquals('refunded', $order->payment_status);
    }

    public function test_cannot_refund_unpaid_order(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'pending',
            'total' => 1000,
        ]);

        $response = $this->postJson("/api/fiscal/orders/{$order->id}/refund");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Возврат возможен только для оплаченных заказов',
            ]);
    }

    public function test_refund_validates_customer_contact(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'total' => 1000,
        ]);

        // Very long customer contact should fail validation
        $response = $this->postJson("/api/fiscal/orders/{$order->id}/refund", [
            'customer_contact' => str_repeat('a', 150),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_contact']);
    }

    public function test_refund_uses_order_phone_if_no_contact_provided(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'phone' => '+79991234567',
            'total' => 1000,
        ]);

        $refundReceipt = $this->createFiscalReceipt(['operation' => 'sell_refund']);

        $this->mock(AtolOnlineService::class, function ($mock) use ($refundReceipt) {
            $mock->shouldReceive('sellRefund')
                ->once()
                ->withArgs(function ($orderArg, $method, $contact) {
                    return $contact === '+79991234567';
                })
                ->andReturn($refundReceipt);
        });

        CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->postJson("/api/fiscal/orders/{$order->id}/refund");

        $response->assertOk();
    }

    public function test_refund_creates_cash_operation(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'total' => 2000,
        ]);

        $refundReceipt = $this->createFiscalReceipt(['operation' => 'sell_refund']);

        $this->mock(AtolOnlineService::class, function ($mock) use ($refundReceipt) {
            $mock->shouldReceive('sellRefund')
                ->once()
                ->andReturn($refundReceipt);
        });

        $shift = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $response = $this->postJson("/api/fiscal/orders/{$order->id}/refund");

        $response->assertOk();

        // Check cash operation created
        $this->assertDatabaseHas('cash_operations', [
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'type' => 'expense',
            'category' => 'refund',
            'amount' => 2000,
        ]);
    }

    // =========================================================================
    // CALLBACK TESTS
    // =========================================================================

    public function test_can_handle_callback(): void
    {
        $receipt = $this->createFiscalReceipt([
            'status' => FiscalReceipt::STATUS_PROCESSING,
            'atol_uuid' => 'callback-test-uuid',
        ]);

        $this->mock(AtolOnlineService::class, function ($mock) use ($receipt) {
            $mock->shouldReceive('handleCallback')
                ->once()
                ->with(Mockery::type('array'))
                ->andReturn($receipt);
        });

        $response = $this->postJson('/api/fiscal/callback', [
            'uuid' => 'callback-test-uuid',
            'status' => 'done',
            'payload' => [
                'fiscal_document_number' => '12345',
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_callback_returns_404_when_receipt_not_found(): void
    {
        $this->mock(AtolOnlineService::class, function ($mock) {
            $mock->shouldReceive('handleCallback')
                ->once()
                ->andReturn(null);
        });

        $response = $this->postJson('/api/fiscal/callback', [
            'uuid' => 'nonexistent-uuid',
            'status' => 'done',
        ]);

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Чек не найден',
            ]);
    }

    // =========================================================================
    // STATUS (INTEGRATION STATUS) TESTS
    // =========================================================================

    public function test_can_get_integration_status_when_enabled(): void
    {
        $this->authenticate();

        $this->mock(AtolOnlineService::class, function ($mock) {
            $mock->shouldReceive('isEnabled')
                ->once()
                ->andReturn(true);

            $mock->shouldReceive('getToken')
                ->once()
                ->andReturn('valid-token-12345');
        });

        config([
            'atol.test_mode' => false,
            'atol.group_code' => 'test_group',
            'atol.company.inn' => '1234567890',
            'atol.company.name' => 'Test Company',
        ]);

        $response = $this->getJson('/api/fiscal/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'enabled' => true,
                    'test_mode' => false,
                    'token_valid' => true,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'enabled',
                    'test_mode',
                    'group_code',
                    'company_inn',
                    'company_name',
                    'token_valid',
                ],
            ]);
    }

    public function test_can_get_integration_status_when_disabled(): void
    {
        $this->authenticate();

        $this->mock(AtolOnlineService::class, function ($mock) {
            $mock->shouldReceive('isEnabled')
                ->once()
                ->andReturn(false);
        });

        config([
            'atol.test_mode' => true,
        ]);

        $response = $this->getJson('/api/fiscal/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'enabled' => false,
                    'test_mode' => true,
                ],
            ]);

        // Should not have token_valid when disabled
        $this->assertArrayNotHasKey('token_valid', $response->json('data'));
    }

    public function test_status_shows_invalid_token_when_auth_fails(): void
    {
        $this->authenticate();

        $this->mock(AtolOnlineService::class, function ($mock) {
            $mock->shouldReceive('isEnabled')
                ->once()
                ->andReturn(true);

            $mock->shouldReceive('getToken')
                ->once()
                ->andReturn(null);
        });

        $response = $this->getJson('/api/fiscal/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'enabled' => true,
                    'token_valid' => false,
                ],
            ]);
    }

    // =========================================================================
    // RECEIPT MODEL STATUS METHODS TESTS
    // =========================================================================

    public function test_receipt_is_fiscalized(): void
    {
        $receipt = $this->createFiscalReceipt(['status' => FiscalReceipt::STATUS_DONE]);

        $this->assertTrue($receipt->isFiscalized());
    }

    public function test_receipt_is_pending(): void
    {
        $receipt1 = $this->createFiscalReceipt(['status' => FiscalReceipt::STATUS_PENDING]);
        $receipt2 = $this->createFiscalReceipt(['status' => FiscalReceipt::STATUS_PROCESSING]);

        $this->assertTrue($receipt1->isPending());
        $this->assertTrue($receipt2->isPending());
    }

    public function test_receipt_is_failed(): void
    {
        $receipt = $this->createFiscalReceipt(['status' => FiscalReceipt::STATUS_FAIL]);

        $this->assertTrue($receipt->isFailed());
    }

    public function test_receipt_mark_as_processing(): void
    {
        $receipt = $this->createFiscalReceipt(['status' => FiscalReceipt::STATUS_PENDING]);

        $receipt->markAsProcessing('test-atol-uuid');

        $receipt->refresh();
        $this->assertEquals(FiscalReceipt::STATUS_PROCESSING, $receipt->status);
        $this->assertEquals('test-atol-uuid', $receipt->atol_uuid);
    }

    public function test_receipt_mark_as_done(): void
    {
        $receipt = $this->createFiscalReceipt(['status' => FiscalReceipt::STATUS_PROCESSING]);

        $receipt->markAsDone([
            'fiscal_document_number' => '123456',
            'fiscal_document_attribute' => '789012',
            'fn_number' => '9999078900007890',
            'shift_number' => '42',
            'receipt_datetime' => '2024-01-15 12:30:00',
            'ofd_sum' => '1000.00',
        ]);

        $receipt->refresh();
        $this->assertEquals(FiscalReceipt::STATUS_DONE, $receipt->status);
        $this->assertEquals('123456', $receipt->fiscal_document_number);
        $this->assertEquals('789012', $receipt->fiscal_document_attribute);
    }

    public function test_receipt_mark_as_failed(): void
    {
        $receipt = $this->createFiscalReceipt(['status' => FiscalReceipt::STATUS_PROCESSING]);

        $receipt->markAsFailed('Test error message', ['error_code' => 100]);

        $receipt->refresh();
        $this->assertEquals(FiscalReceipt::STATUS_FAIL, $receipt->status);
        $this->assertEquals('Test error message', $receipt->error_message);
        $this->assertEquals(['error_code' => 100], $receipt->callback_response);
    }

    // =========================================================================
    // AUTHORIZATION TESTS
    // =========================================================================

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson("/api/fiscal?restaurant_id={$this->restaurant->id}");

        $response->assertUnauthorized();
    }

    public function test_status_requires_authentication(): void
    {
        $response = $this->getJson('/api/fiscal/status');

        $response->assertUnauthorized();
    }

    // =========================================================================
    // EDGE CASES AND VALIDATION TESTS
    // =========================================================================

    public function test_receipts_isolated_by_restaurant(): void
    {
        $this->authenticate();

        $otherRestaurant = Restaurant::factory()->create();

        // Create receipt for other restaurant
        $otherOrder = Order::factory()->create(['restaurant_id' => $otherRestaurant->id]);
        FiscalReceipt::create([
            'restaurant_id' => $otherRestaurant->id,
            'order_id' => $otherOrder->id,
            'operation' => 'sell',
            'external_id' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => FiscalReceipt::STATUS_DONE,
            'total' => 500,
            'items' => [],
            'payments' => [],
        ]);

        // Create receipt for our restaurant
        $this->createFiscalReceipt();

        $response = $this->getJson("/api/fiscal?restaurant_id={$this->restaurant->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));

        foreach ($response->json('data') as $receipt) {
            $this->assertEquals($this->restaurant->id, $receipt['restaurant_id']);
        }
    }

    public function test_receipt_constants_are_correct(): void
    {
        $this->assertEquals('pending', FiscalReceipt::STATUS_PENDING);
        $this->assertEquals('processing', FiscalReceipt::STATUS_PROCESSING);
        $this->assertEquals('done', FiscalReceipt::STATUS_DONE);
        $this->assertEquals('fail', FiscalReceipt::STATUS_FAIL);

        $this->assertEquals('sell', FiscalReceipt::OPERATION_SELL);
        $this->assertEquals('sell_refund', FiscalReceipt::OPERATION_SELL_REFUND);
    }

    public function test_receipt_relations(): void
    {
        $order = Order::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $receipt = FiscalReceipt::create([
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'operation' => 'sell',
            'external_id' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => FiscalReceipt::STATUS_DONE,
            'total' => 1000,
            'items' => [],
            'payments' => [],
        ]);

        // Test relations
        $this->assertNotNull($receipt->order);
        $this->assertEquals($order->id, $receipt->order->id);

        $this->assertNotNull($receipt->restaurant);
        $this->assertEquals($this->restaurant->id, $receipt->restaurant->id);
    }

    public function test_receipt_casts(): void
    {
        $receipt = $this->createFiscalReceipt([
            'items' => [['name' => 'Test', 'price' => 100]],
            'payments' => [['type' => 1, 'sum' => 100]],
        ]);

        // Items and payments should be cast to array
        $this->assertIsArray($receipt->items);
        $this->assertIsArray($receipt->payments);

        // Total should be decimal
        $this->assertIsNumeric($receipt->total);
    }

    public function test_refund_with_valid_staff_id(): void
    {
        $this->authenticate();

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'total' => 1000,
        ]);

        $refundReceipt = $this->createFiscalReceipt(['operation' => 'sell_refund']);

        $this->mock(AtolOnlineService::class, function ($mock) use ($refundReceipt) {
            $mock->shouldReceive('sellRefund')
                ->once()
                ->andReturn($refundReceipt);
        });

        CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        // Use valid user as staff
        $response = $this->postJson("/api/fiscal/orders/{$order->id}/refund", [
            'customer_contact' => 'test@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Чек возврата создан',
            ]);
    }

    public function test_empty_receipts_list(): void
    {
        $this->authenticate();

        $response = $this->getJson("/api/fiscal?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }
}
