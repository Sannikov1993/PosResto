<?php

namespace Tests\Feature\Api\Finance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\LegalEntity;
use App\Models\CashRegister;
use App\Models\CashShift;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CashRegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected LegalEntity $legalEntity;
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
        $this->legalEntity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
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

    // =========================================================================
    // INDEX TESTS
    // =========================================================================

    public function test_can_list_cash_registers(): void
    {
        CashRegister::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
        ]);

        $this->authenticate();

        $response = $this->getJson('/api/cash-registers');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'serial_number',
                        'is_active',
                        'is_default',
                        'legal_entity',
                    ]
                ]
            ])
            ->assertJson(['success' => true]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_filter_by_legal_entity(): void
    {
        CashRegister::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
        ]);

        $otherEntity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        CashRegister::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $otherEntity->id,
        ]);

        $this->authenticate();

        $response = $this->getJson("/api/cash-registers?legal_entity_id={$this->legalEntity->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_registers_for_current_restaurant_only(): void
    {
        CashRegister::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
        ]);

        $otherRestaurant = Restaurant::factory()->create();
        $otherEntity = LegalEntity::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
        ]);
        CashRegister::factory()->count(3)->create([
            'restaurant_id' => $otherRestaurant->id,
            'legal_entity_id' => $otherEntity->id,
        ]);

        $this->authenticate();

        $response = $this->getJson('/api/cash-registers');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/cash-registers');

        $response->assertUnauthorized();
    }

    // =========================================================================
    // STORE TESTS
    // =========================================================================

    public function test_can_create_cash_register(): void
    {
        $data = [
            'legal_entity_id' => $this->legalEntity->id,
            'name' => 'Касса 1',
            'serial_number' => 'KKT-12345678',
            'registration_number' => '0001234567',
            'fn_number' => '9999078900007890',
            'fn_expires_at' => '2027-12-31',
            'ofd_name' => 'ОФД.ру',
            'ofd_inn' => '7709364346',
        ];

        $this->authenticate();

        $response = $this->postJson('/api/cash-registers', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Касса создана',
            ]);

        $this->assertDatabaseHas('cash_registers', [
            'name' => 'Касса 1',
            'serial_number' => 'KKT-12345678',
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
        ]);
    }

    public function test_first_register_becomes_default(): void
    {
        $data = [
            'legal_entity_id' => $this->legalEntity->id,
            'name' => 'Первая касса',
        ];

        $this->authenticate();

        $response = $this->postJson('/api/cash-registers', $data);

        $response->assertStatus(201);

        $register = CashRegister::where('legal_entity_id', $this->legalEntity->id)->first();
        $this->assertTrue($register->is_default);
    }

    public function test_second_register_not_default(): void
    {
        CashRegister::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
        ]);

        $data = [
            'legal_entity_id' => $this->legalEntity->id,
            'name' => 'Вторая касса',
        ];

        $this->authenticate();

        $response = $this->postJson('/api/cash-registers', $data);

        $response->assertStatus(201);

        $newRegister = CashRegister::where('name', 'Вторая касса')->first();
        $this->assertFalse($newRegister->is_default);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/cash-registers', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['legal_entity_id', 'name']);
    }

    public function test_create_validates_legal_entity_exists(): void
    {
        $data = [
            'legal_entity_id' => 99999,
            'name' => 'Test',
        ];

        $this->authenticate();

        $response = $this->postJson('/api/cash-registers', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['legal_entity_id']);
    }

    // =========================================================================
    // SHOW TESTS
    // =========================================================================

    public function test_can_show_cash_register(): void
    {
        $register = CashRegister::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
        ]);

        $this->authenticate();

        $response = $this->getJson("/api/cash-registers/{$register->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $register->id,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'serial_number',
                    'legal_entity',
                ],
            ]);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/cash-registers/99999');

        $response->assertNotFound();
    }

    // =========================================================================
    // UPDATE TESTS
    // =========================================================================

    public function test_can_update_cash_register(): void
    {
        $register = CashRegister::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
            'name' => 'Old Name',
        ]);

        $this->authenticate();

        $response = $this->putJson("/api/cash-registers/{$register->id}", [
                'name' => 'New Name',
                'ofd_name' => 'Такском',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Касса обновлена',
            ]);

        $this->assertDatabaseHas('cash_registers', [
            'id' => $register->id,
            'name' => 'New Name',
            'ofd_name' => 'Такском',
        ]);
    }

    public function test_update_makes_default_and_removes_from_others(): void
    {
        $register1 = CashRegister::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
        ]);

        $register2 = CashRegister::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
            'is_default' => false,
        ]);

        $this->authenticate();

        $response = $this->putJson("/api/cash-registers/{$register2->id}", [
                'is_default' => true,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('cash_registers', [
            'id' => $register1->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('cash_registers', [
            'id' => $register2->id,
            'is_default' => true,
        ]);
    }

    public function test_can_change_legal_entity(): void
    {
        $register = CashRegister::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
        ]);

        $newEntity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();

        $response = $this->putJson("/api/cash-registers/{$register->id}", [
                'legal_entity_id' => $newEntity->id,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('cash_registers', [
            'id' => $register->id,
            'legal_entity_id' => $newEntity->id,
        ]);
    }

    // =========================================================================
    // DESTROY TESTS
    // =========================================================================

    public function test_can_delete_cash_register(): void
    {
        $register = CashRegister::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
        ]);

        $this->authenticate();

        $response = $this->deleteJson("/api/cash-registers/{$register->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Касса удалена',
            ]);

        $this->assertDatabaseMissing('cash_registers', [
            'id' => $register->id,
        ]);
    }

    public function test_cannot_delete_register_with_open_shift(): void
    {
        $register = CashRegister::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
        ]);

        CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'cash_register_id' => $register->id,
            'status' => 'open',
        ]);

        $this->authenticate();

        $response = $this->deleteJson("/api/cash-registers/{$register->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('cash_registers', [
            'id' => $register->id,
        ]);
    }

    public function test_deleting_default_assigns_another(): void
    {
        $register1 = CashRegister::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
        ]);

        $register2 = CashRegister::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->authenticate();

        $response = $this->deleteJson("/api/cash-registers/{$register1->id}");

        $response->assertOk();

        $register2->refresh();
        $this->assertTrue($register2->is_default);
    }

    // =========================================================================
    // MAKE DEFAULT TESTS
    // =========================================================================

    public function test_can_make_register_default(): void
    {
        $register1 = CashRegister::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
        ]);

        $register2 = CashRegister::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $this->legalEntity->id,
            'is_default' => false,
        ]);

        $this->authenticate();

        $response = $this->postJson("/api/cash-registers/{$register2->id}/default");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Касса установлена по умолчанию',
            ]);

        $register1->refresh();
        $register2->refresh();

        $this->assertFalse($register1->is_default);
        $this->assertTrue($register2->is_default);
    }
}
