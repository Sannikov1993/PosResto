<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\GiftCertificate;
use App\Models\GiftCertificateUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class GiftCertificateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected User $user;
    protected Customer $customer;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Customer',
            'phone' => '+79001234567',
        ]);
    }

    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    // =====================================================
    // INDEX - LIST CERTIFICATES
    // =====================================================

    /** @test */
    public function it_can_list_certificates(): void
    {
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-TEST-0001',
            'amount' => 1000,
            'balance' => 1000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-TEST-0002',
            'amount' => 2000,
            'balance' => 2000,
            'payment_method' => 'card',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/gift-certificates?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'code', 'amount', 'balance', 'status']
                ],
                'meta' => ['total', 'current_page', 'last_page'],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function it_can_filter_certificates_by_status(): void
    {
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-ACTIVE-001',
            'amount' => 1000,
            'balance' => 1000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-USED-001',
            'amount' => 2000,
            'balance' => 0,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_USED,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/gift-certificates?restaurant_id={$this->restaurant->id}&status=active");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('GC-ACTIVE-001', $response->json('data.0.code'));
    }

    /** @test */
    public function it_can_search_certificates_by_code(): void
    {
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-FIND-1234',
            'amount' => 1000,
            'balance' => 1000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-OTHER-5678',
            'amount' => 2000,
            'balance' => 2000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/gift-certificates?restaurant_id={$this->restaurant->id}&search=FIND");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('GC-FIND-1234', $response->json('data.0.code'));
    }

    /** @test */
    public function it_can_search_certificates_by_buyer_name(): void
    {
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-TEST-0001',
            'amount' => 1000,
            'balance' => 1000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'buyer_name' => 'Ivan Petrov',
        ]);

        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-TEST-0002',
            'amount' => 2000,
            'balance' => 2000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'buyer_name' => 'Maria Sidorova',
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/gift-certificates?restaurant_id={$this->restaurant->id}&search=Petrov");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Ivan Petrov', $response->json('data.0.buyer_name'));
    }

    /** @test */
    public function it_can_search_certificates_by_recipient_phone(): void
    {
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-TEST-0001',
            'amount' => 1000,
            'balance' => 1000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'recipient_phone' => '+79991112233',
        ]);

        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-TEST-0002',
            'amount' => 2000,
            'balance' => 2000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'recipient_phone' => '+79994445566',
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/gift-certificates?restaurant_id={$this->restaurant->id}&search=9991112233");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('+79991112233', $response->json('data.0.recipient_phone'));
    }

    /** @test */
    public function it_paginates_certificates(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            GiftCertificate::create([
                'restaurant_id' => $this->restaurant->id,
                'code' => sprintf('GC-PAGE-%04d', $i),
                'amount' => 1000,
                'balance' => 1000,
                'payment_method' => 'cash',
                'status' => GiftCertificate::STATUS_ACTIVE,
            ]);
        }

        $this->authenticate();
        $response = $this->getJson("/api/gift-certificates?restaurant_id={$this->restaurant->id}&per_page=10");

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total'));
        $this->assertEquals(2, $response->json('meta.last_page'));
    }

    /** @test */
    public function certificates_are_ordered_by_created_at_desc(): void
    {
        // Create older certificate first with past timestamp using DB
        $older = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-OLDER-001',
            'amount' => 1000,
            'balance' => 1000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        // Use query builder to update created_at
        \DB::table('gift_certificates')
            ->where('id', $older->id)
            ->update(['created_at' => now()->subDay()]);

        // Create newer certificate with current timestamp
        $newer = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-NEWER-001',
            'amount' => 2000,
            'balance' => 2000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/gift-certificates?restaurant_id={$this->restaurant->id}");

        $response->assertOk();
        $this->assertEquals('GC-NEWER-001', $response->json('data.0.code'));
        $this->assertEquals('GC-OLDER-001', $response->json('data.1.code'));
    }

    // =====================================================
    // STORE - CREATE CERTIFICATE
    // =====================================================

    /** @test */
    public function it_can_create_certificate_with_required_fields(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates', [
            'amount' => 5000,
            'payment_method' => 'cash',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Сертификат создан',
            ]);

        $this->assertDatabaseHas('gift_certificates', [
            'restaurant_id' => $this->restaurant->id,
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
        ]);

        // Check that code was generated
        $data = $response->json('data');
        $this->assertNotNull($data['code']);
        $this->assertStringStartsWith('GC-', $data['code']);
    }

    /** @test */
    public function it_creates_certificate_with_active_status_by_default(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates', [
            'amount' => 5000,
            'payment_method' => 'card',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201);

        // Default behavior is to activate immediately
        $this->assertEquals(GiftCertificate::STATUS_ACTIVE, $response->json('data.status'));
    }

    /** @test */
    public function it_creates_certificate_with_pending_status_when_not_activated(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates', [
            'amount' => 5000,
            'payment_method' => 'card',
            'activate' => false,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(GiftCertificate::STATUS_PENDING, $response->json('data.status'));
    }

    /** @test */
    public function it_creates_certificate_with_all_optional_fields(): void
    {
        $expiresAt = now()->addMonths(6)->format('Y-m-d');

        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates', [
            'amount' => 10000,
            'payment_method' => 'online',
            'buyer_customer_id' => $this->customer->id,
            'buyer_name' => 'John Buyer',
            'buyer_phone' => '+79001112233',
            'recipient_name' => 'Jane Recipient',
            'recipient_phone' => '+79004445566',
            'expires_at' => $expiresAt,
            'notes' => 'Birthday gift',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('gift_certificates', [
            'amount' => 10000,
            'payment_method' => 'online',
            'buyer_customer_id' => $this->customer->id,
            'buyer_name' => 'John Buyer',
            'buyer_phone' => '+79001112233',
            'recipient_name' => 'Jane Recipient',
            'recipient_phone' => '+79004445566',
            'notes' => 'Birthday gift',
        ]);
    }

    /** @test */
    public function it_sets_sold_by_user_id_to_current_user(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates', [
            'amount' => 5000,
            'payment_method' => 'cash',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('gift_certificates', [
            'sold_by_user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function create_certificate_validates_amount_required(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates', [
            'payment_method' => 'cash',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function create_certificate_validates_amount_minimum(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates', [
            'amount' => 50, // Less than min 100
            'payment_method' => 'cash',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function create_certificate_validates_amount_maximum(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates', [
            'amount' => 150000, // More than max 100000
            'payment_method' => 'cash',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function create_certificate_validates_payment_method_required(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates', [
            'amount' => 5000,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);
    }

    /** @test */
    public function create_certificate_validates_payment_method_values(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates', [
            'amount' => 5000,
            'payment_method' => 'bitcoin', // Invalid
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);
    }

    /** @test */
    public function create_certificate_validates_expires_at_must_be_after_today(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates', [
            'amount' => 5000,
            'payment_method' => 'cash',
            'expires_at' => now()->subDay()->format('Y-m-d'), // Yesterday
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['expires_at']);
    }

    /** @test */
    public function create_certificate_validates_buyer_customer_exists(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates', [
            'amount' => 5000,
            'payment_method' => 'cash',
            'buyer_customer_id' => 99999, // Non-existent
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['buyer_customer_id']);
    }

    /** @test */
    public function it_generates_unique_certificate_code(): void
    {
        $this->authenticate();
        $response1 = $this->postJson('/api/gift-certificates', [
            'amount' => 5000,
            'payment_method' => 'cash',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response2 = $this->postJson('/api/gift-certificates', [
            'amount' => 3000,
            'payment_method' => 'cash',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response1->assertStatus(201);
        $response2->assertStatus(201);

        $code1 = $response1->json('data.code');
        $code2 = $response2->json('data.code');

        $this->assertNotEquals($code1, $code2);
    }

    // =====================================================
    // SHOW - GET SINGLE CERTIFICATE
    // =====================================================

    /** @test */
    public function it_can_show_certificate(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-SHOW-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'buyer_name' => 'Test Buyer',
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/gift-certificates/{$certificate->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $certificate->id,
                    'code' => 'GC-SHOW-1234',
                    'amount' => '5000.00',
                    'balance' => '5000.00',
                    'status' => 'active',
                    'buyer_name' => 'Test Buyer',
                ],
            ]);
    }

    /** @test */
    public function it_loads_certificate_relationships(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-REL-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'buyer_customer_id' => $this->customer->id,
            'sold_by_user_id' => $this->user->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/gift-certificates/{$certificate->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'buyer_customer',
                    'sold_by_user',
                    'usages',
                ],
            ]);
    }

    /** @test */
    public function show_returns_404_for_nonexistent_certificate(): void
    {
        $this->authenticate();
        $response = $this->getJson('/api/gift-certificates/99999');

        $response->assertNotFound();
    }

    // =====================================================
    // UPDATE - MODIFY CERTIFICATE
    // =====================================================

    /** @test */
    public function it_can_update_certificate(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-UPD-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/gift-certificates/{$certificate->id}", [
            'recipient_name' => 'Updated Recipient',
            'recipient_phone' => '+79009998877',
            'notes' => 'Updated notes',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Сертификат обновлён',
            ]);

        $this->assertDatabaseHas('gift_certificates', [
            'id' => $certificate->id,
            'recipient_name' => 'Updated Recipient',
            'recipient_phone' => '+79009998877',
            'notes' => 'Updated notes',
        ]);
    }

    /** @test */
    public function it_can_update_certificate_expiry_date(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-EXP-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $newExpiryDate = now()->addYear()->format('Y-m-d');

        $this->authenticate();
        $response = $this->putJson("/api/gift-certificates/{$certificate->id}", [
            'expires_at' => $newExpiryDate,
        ]);

        $response->assertOk();

        $certificate->refresh();
        $this->assertEquals($newExpiryDate, $certificate->expires_at->format('Y-m-d'));
    }

    /** @test */
    public function it_can_assign_recipient_customer(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-RCP-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $recipientCustomer = Customer::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Recipient Customer',
            'phone' => '+79007776655',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/gift-certificates/{$certificate->id}", [
            'recipient_customer_id' => $recipientCustomer->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('gift_certificates', [
            'id' => $certificate->id,
            'recipient_customer_id' => $recipientCustomer->id,
        ]);
    }

    // =====================================================
    // CHECK - VALIDATE CERTIFICATE BY CODE
    // =====================================================

    /** @test */
    public function it_can_check_valid_certificate_by_code(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-CHK-1234',
            'amount' => 5000,
            'balance' => 3000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'recipient_name' => 'Test Recipient',
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates/check', [
            'code' => 'GC-CHK-1234',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Сертификат действителен',
                'data' => [
                    'id' => $certificate->id,
                    'code' => 'GC-CHK-1234',
                    'amount' => 5000,
                    'balance' => 3000,
                    'status' => 'active',
                    'recipient_name' => 'Test Recipient',
                ],
            ]);
    }

    /** @test */
    public function check_is_case_insensitive(): void
    {
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-CASE-ABCD',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates/check', [
            'code' => 'gc-case-abcd', // lowercase
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function check_returns_404_for_unknown_code(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates/check', [
            'code' => 'GC-UNKNOWN-0000',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Сертификат не найден',
            ]);
    }

    /** @test */
    public function check_rejects_pending_certificate(): void
    {
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-PEND-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_PENDING,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates/check', [
            'code' => 'GC-PEND-1234',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Сертификат не активирован',
            ]);
    }

    /** @test */
    public function check_rejects_used_certificate(): void
    {
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-USED-1234',
            'amount' => 5000,
            'balance' => 0,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_USED,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates/check', [
            'code' => 'GC-USED-1234',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Сертификат полностью использован',
            ]);
    }

    /** @test */
    public function check_rejects_expired_certificate(): void
    {
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-EXPR-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'expires_at' => now()->subDays(5),
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates/check', [
            'code' => 'GC-EXPR-1234',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Срок действия сертификата истёк',
            ]);
    }

    /** @test */
    public function check_rejects_cancelled_certificate(): void
    {
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-CNCL-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_CANCELLED,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates/check', [
            'code' => 'GC-CNCL-1234',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Сертификат отменён',
            ]);
    }

    /** @test */
    public function check_validates_code_required(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates/check', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    /** @test */
    public function check_returns_certificate_balance_info(): void
    {
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-BAL-1234',
            'amount' => 10000,
            'balance' => 7500,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates/check', [
            'code' => 'GC-BAL-1234',
        ]);

        $response->assertOk();
        $this->assertEquals(10000, $response->json('data.amount'));
        $this->assertEquals(7500, $response->json('data.balance'));
    }

    // =====================================================
    // USE - REDEEM CERTIFICATE
    // =====================================================

    /** @test */
    public function it_can_use_certificate(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-USE-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/use", [
            'amount' => 2000,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'used_amount' => 2000,
                    'remaining_balance' => 3000,
                    'certificate_status' => 'active',
                ],
            ]);

        $certificate->refresh();
        $this->assertEquals(3000, $certificate->balance);
    }

    /** @test */
    public function it_records_usage_history(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-HST-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $order = Order::create([
            'restaurant_id' => $this->restaurant->id,
            'customer_id' => $this->customer->id,
            'type' => 'dine_in',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 3000,
            'subtotal' => 3000,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/use", [
            'amount' => 2000,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('gift_certificate_usages', [
            'gift_certificate_id' => $certificate->id,
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'amount' => 2000,
            'balance_before' => 5000,
            'balance_after' => 3000,
            'used_by_user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function using_full_balance_marks_certificate_as_used(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-FULL-1234',
            'amount' => 3000,
            'balance' => 3000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/use", [
            'amount' => 3000,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'used_amount' => 3000,
                    'remaining_balance' => 0,
                    'certificate_status' => 'used',
                ],
            ]);

        $certificate->refresh();
        $this->assertEquals(GiftCertificate::STATUS_USED, $certificate->status);
    }

    /** @test */
    public function using_more_than_balance_limits_to_balance(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-LMT-1234',
            'amount' => 5000,
            'balance' => 2000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/use", [
            'amount' => 5000, // More than balance
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'used_amount' => 2000, // Limited to balance
                    'remaining_balance' => 0,
                ],
            ]);
    }

    /** @test */
    public function use_fails_for_inactive_certificate(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-INACT-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_PENDING,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/use", [
            'amount' => 1000,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Сертификат не может быть использован',
            ]);
    }

    /** @test */
    public function use_fails_for_expired_certificate(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-EXP2-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'expires_at' => now()->subDays(10),
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/use", [
            'amount' => 1000,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Сертификат не может быть использован',
            ]);
    }

    /** @test */
    public function use_validates_amount_required(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-VAL-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/use", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function use_validates_amount_minimum(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-MIN-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/use", [
            'amount' => 0,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_can_use_certificate_multiple_times(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-MULTI-1234',
            'amount' => 10000,
            'balance' => 10000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();

        // First use
        $response1 = $this->postJson("/api/gift-certificates/{$certificate->id}/use", [
            'amount' => 3000,
        ]);

        $response1->assertOk();
        $this->assertEquals(7000, $response1->json('data.remaining_balance'));

        // Second use
        $response2 = $this->postJson("/api/gift-certificates/{$certificate->id}/use", [
            'amount' => 2000,
        ]);

        $response2->assertOk();
        $this->assertEquals(5000, $response2->json('data.remaining_balance'));

        // Check usage history
        $this->assertEquals(2, GiftCertificateUsage::where('gift_certificate_id', $certificate->id)->count());
    }

    // =====================================================
    // ACTIVATE - ACTIVATE PENDING CERTIFICATE
    // =====================================================

    /** @test */
    public function it_can_activate_pending_certificate(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-ACT-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_PENDING,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/activate");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Сертификат активирован',
            ]);

        $certificate->refresh();
        $this->assertEquals(GiftCertificate::STATUS_ACTIVE, $certificate->status);
        $this->assertNotNull($certificate->activated_at);
    }

    /** @test */
    public function activate_fails_for_already_active_certificate(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-ACT2-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/activate");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Можно активировать только сертификаты в статусе "Ожидает оплаты"',
            ]);
    }

    /** @test */
    public function activate_fails_for_used_certificate(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-ACT3-1234',
            'amount' => 5000,
            'balance' => 0,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_USED,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/activate");

        $response->assertStatus(400);
    }

    /** @test */
    public function activate_fails_for_cancelled_certificate(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-ACT4-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_CANCELLED,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/activate");

        $response->assertStatus(400);
    }

    // =====================================================
    // CANCEL - CANCEL CERTIFICATE
    // =====================================================

    /** @test */
    public function it_can_cancel_pending_certificate(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-CNC-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_PENDING,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/cancel");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Сертификат отменён',
            ]);

        $certificate->refresh();
        $this->assertEquals(GiftCertificate::STATUS_CANCELLED, $certificate->status);
    }

    /** @test */
    public function it_can_cancel_active_certificate(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-CNC2-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/cancel");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $certificate->refresh();
        $this->assertEquals(GiftCertificate::STATUS_CANCELLED, $certificate->status);
    }

    /** @test */
    public function cancel_fails_for_used_certificate(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-CNC3-1234',
            'amount' => 5000,
            'balance' => 0,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_USED,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/cancel");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Нельзя отменить использованный сертификат',
            ]);
    }

    // =====================================================
    // STATS - CERTIFICATE STATISTICS
    // =====================================================

    /** @test */
    public function it_returns_certificate_statistics(): void
    {
        // Create certificates with different statuses
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-STAT-001',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-STAT-002',
            'amount' => 3000,
            'balance' => 1000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-STAT-003',
            'amount' => 2000,
            'balance' => 0,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_USED,
        ]);

        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-STAT-004',
            'amount' => 1000,
            'balance' => 1000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_PENDING,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/gift-certificates/stats?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_count' => 4,
                    'active_count' => 2,
                    'total_sold' => 10000, // 5000 + 3000 + 2000 (active + used)
                    'total_balance' => 6000, // 5000 + 1000 (only active)
                ],
            ]);
    }

    /** @test */
    public function stats_includes_expiring_soon_count(): void
    {
        // Certificate expiring in 3 days
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-EXP-001',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'expires_at' => now()->addDays(3),
        ]);

        // Certificate expiring in 10 days (not soon)
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-EXP-002',
            'amount' => 3000,
            'balance' => 3000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'expires_at' => now()->addDays(10),
        ]);

        // Certificate without expiry
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-EXP-003',
            'amount' => 2000,
            'balance' => 2000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/gift-certificates/stats?restaurant_id={$this->restaurant->id}");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.expiring_soon'));
    }

    // =====================================================
    // DATA ISOLATION TESTS
    // =====================================================

    /** @test */
    public function certificates_are_scoped_to_restaurant(): void
    {
        $otherRestaurant = Restaurant::factory()->create();

        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-MY-0001',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        GiftCertificate::create([
            'restaurant_id' => $otherRestaurant->id,
            'code' => 'GC-OTHER-0001',
            'amount' => 3000,
            'balance' => 3000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/gift-certificates?restaurant_id={$this->restaurant->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('GC-MY-0001', $response->json('data.0.code'));
    }

    // =====================================================
    // EDGE CASES
    // =====================================================

    /** @test */
    public function check_auto_expires_certificate_on_check(): void
    {
        // Create an active certificate that should be expired
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-AUTOEXP-1234',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
            'expires_at' => now()->subDays(1), // Expired yesterday
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates/check', [
            'code' => 'GC-AUTOEXP-1234',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Срок действия сертификата истёк',
            ]);

        // Check that certificate status was updated
        $certificate->refresh();
        $this->assertEquals(GiftCertificate::STATUS_EXPIRED, $certificate->status);
    }

    /** @test */
    public function certificate_can_have_zero_balance_but_still_be_active(): void
    {
        // This shouldn't normally happen but let's test it
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-ZERO-1234',
            'amount' => 5000,
            'balance' => 0,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE, // Edge case
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/gift-certificates/{$certificate->id}/use", [
            'amount' => 1000,
        ]);

        // Should fail because balance is 0
        $response->assertStatus(400);
    }

    /** @test */
    public function it_trims_and_uppercases_code_on_check(): void
    {
        GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-TRIM-ABCD',
            'amount' => 5000,
            'balance' => 5000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/gift-certificates/check', [
            'code' => '  gc-trim-abcd  ', // with spaces and lowercase
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function show_returns_usage_history(): void
    {
        $certificate = GiftCertificate::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'GC-USAGE-1234',
            'amount' => 10000,
            'balance' => 10000,
            'payment_method' => 'cash',
            'status' => GiftCertificate::STATUS_ACTIVE,
        ]);

        $this->authenticate();

        // Use certificate twice
        $this->postJson("/api/gift-certificates/{$certificate->id}/use", ['amount' => 2000]);
        $this->postJson("/api/gift-certificates/{$certificate->id}/use", ['amount' => 3000]);

        $response = $this->getJson("/api/gift-certificates/{$certificate->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data.usages'));
    }

    /** @test */
    public function it_accepts_all_valid_payment_methods(): void
    {
        $paymentMethods = ['cash', 'card', 'online'];

        $this->authenticate();

        foreach ($paymentMethods as $method) {
            $response = $this->postJson('/api/gift-certificates', [
                'amount' => 1000,
                'payment_method' => $method,
                'restaurant_id' => $this->restaurant->id,
            ]);

            $response->assertStatus(201);
        }

        $this->assertDatabaseCount('gift_certificates', 3);
    }
}
