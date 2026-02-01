<?php

namespace Tests\Feature\Api\Finance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\LegalEntity;
use App\Models\Category;
use App\Models\CashRegister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class LegalEntityControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'role' => 'super_admin', // Bypass permission checks for testing
        ]);
    }

    protected string $token;

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

    public function test_can_list_legal_entities(): void
    {
        LegalEntity::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();

        $response = $this->getJson('/api/legal-entities');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'type', 'inn', 'is_default', 'is_active']
                ]
            ])
            ->assertJson(['success' => true]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_returns_entities_for_current_restaurant_only(): void
    {
        LegalEntity::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $otherRestaurant = Restaurant::factory()->create();
        LegalEntity::factory()->count(3)->create([
            'restaurant_id' => $otherRestaurant->id,
        ]);

        $this->authenticate();

        $response = $this->getJson('/api/legal-entities');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_includes_counts(): void
    {
        $entity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        Category::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entity->id,
        ]);

        CashRegister::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entity->id,
            'is_active' => true,
        ]);

        $this->authenticate();

        $response = $this->getJson('/api/legal-entities');

        $response->assertOk();
        $data = $response->json('data.0');
        $this->assertEquals(2, $data['categories_count']);
        $this->assertEquals(3, $data['cash_registers_count']);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/legal-entities');

        $response->assertUnauthorized();
    }

    // =========================================================================
    // STORE TESTS
    // =========================================================================

    public function test_can_create_llc_legal_entity(): void
    {
        $data = [
            'name' => 'ООО "Тестовая компания"',
            'short_name' => 'ООО',
            'type' => 'llc',
            'inn' => '1234567890',
            'kpp' => '123456789',
            'taxation_system' => 'osn',
        ];

        $this->authenticate();

        $response = $this->postJson('/api/legal-entities', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Юридическое лицо создано',
            ]);

        $this->assertDatabaseHas('legal_entities', [
            'name' => 'ООО "Тестовая компания"',
            'type' => 'llc',
            'inn' => '1234567890',
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_can_create_ie_legal_entity(): void
    {
        $data = [
            'name' => 'ИП Иванов Иван Иванович',
            'short_name' => 'ИП',
            'type' => 'ie',
            'inn' => '123456789012',
            'taxation_system' => 'usn_income',
        ];

        $this->authenticate();

        $response = $this->postJson('/api/legal-entities', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('legal_entities', [
            'name' => 'ИП Иванов Иван Иванович',
            'type' => 'ie',
        ]);
    }

    public function test_first_entity_becomes_default(): void
    {
        $data = [
            'name' => 'ООО "Первая компания"',
            'type' => 'llc',
            'inn' => '1234567890',
        ];

        $this->authenticate();

        $response = $this->postJson('/api/legal-entities', $data);

        $response->assertStatus(201);

        $entity = LegalEntity::where('restaurant_id', $this->restaurant->id)->first();
        $this->assertTrue($entity->is_default);
    }

    public function test_second_entity_not_default(): void
    {
        LegalEntity::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $data = [
            'name' => 'ООО "Вторая компания"',
            'type' => 'llc',
            'inn' => '1234567890',
        ];

        $this->authenticate();

        $response = $this->postJson('/api/legal-entities', $data);

        $response->assertStatus(201);

        $newEntity = LegalEntity::where('name', 'ООО "Вторая компания"')->first();
        $this->assertFalse($newEntity->is_default);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/legal-entities', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'type', 'inn']);
    }

    public function test_create_validates_type(): void
    {
        $data = [
            'name' => 'Test',
            'type' => 'invalid_type',
            'inn' => '1234567890',
        ];

        $this->authenticate();

        $response = $this->postJson('/api/legal-entities', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_validates_taxation_system(): void
    {
        $data = [
            'name' => 'Test',
            'type' => 'llc',
            'inn' => '1234567890',
            'taxation_system' => 'invalid_tax',
        ];

        $this->authenticate();

        $response = $this->postJson('/api/legal-entities', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['taxation_system']);
    }

    public function test_can_create_entity_with_alcohol_license(): void
    {
        $data = [
            'name' => 'ООО "Ресторан"',
            'type' => 'llc',
            'inn' => '1234567890',
            'has_alcohol_license' => true,
            'alcohol_license_number' => '12АП1234567',
            'alcohol_license_expires_at' => '2027-12-31',
        ];

        $this->authenticate();

        $response = $this->postJson('/api/legal-entities', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('legal_entities', [
            'has_alcohol_license' => true,
            'alcohol_license_number' => '12АП1234567',
        ]);
    }

    // =========================================================================
    // SHOW TESTS
    // =========================================================================

    public function test_can_show_legal_entity(): void
    {
        $entity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        CashRegister::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entity->id,
        ]);

        $this->authenticate();

        $response = $this->getJson("/api/legal-entities/{$entity->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $entity->id,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'type',
                    'inn',
                    'cash_registers',
                    'categories_count',
                ],
            ]);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/legal-entities/99999');

        $response->assertNotFound();
    }

    // =========================================================================
    // UPDATE TESTS
    // =========================================================================

    public function test_can_update_legal_entity(): void
    {
        $entity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Old Name',
        ]);

        $this->authenticate();

        $response = $this->putJson("/api/legal-entities/{$entity->id}", [
                'name' => 'New Name',
                'vat_rate' => 20,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Юридическое лицо обновлено',
            ]);

        $this->assertDatabaseHas('legal_entities', [
            'id' => $entity->id,
            'name' => 'New Name',
            'vat_rate' => 20,
        ]);
    }

    public function test_update_makes_default_and_removes_from_others(): void
    {
        $entity1 = LegalEntity::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $entity2 = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_default' => false,
        ]);

        $this->authenticate();

        $response = $this->putJson("/api/legal-entities/{$entity2->id}", [
                'is_default' => true,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('legal_entities', [
            'id' => $entity1->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('legal_entities', [
            'id' => $entity2->id,
            'is_default' => true,
        ]);
    }

    public function test_update_validates_type(): void
    {
        $entity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();

        $response = $this->putJson("/api/legal-entities/{$entity->id}", [
                'type' => 'invalid',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    // =========================================================================
    // DESTROY TESTS
    // =========================================================================

    public function test_can_delete_legal_entity(): void
    {
        $entity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();

        $response = $this->deleteJson("/api/legal-entities/{$entity->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Юридическое лицо удалено',
            ]);

        $this->assertSoftDeleted('legal_entities', [
            'id' => $entity->id,
        ]);
    }

    public function test_cannot_delete_entity_with_categories(): void
    {
        $entity = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'legal_entity_id' => $entity->id,
        ]);

        $this->authenticate();

        $response = $this->deleteJson("/api/legal-entities/{$entity->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('legal_entities', [
            'id' => $entity->id,
            'deleted_at' => null,
        ]);
    }

    public function test_deleting_default_assigns_another(): void
    {
        $entity1 = LegalEntity::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $entity2 = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->authenticate();

        $response = $this->deleteJson("/api/legal-entities/{$entity1->id}");

        $response->assertOk();

        $entity2->refresh();
        $this->assertTrue($entity2->is_default);
    }

    // =========================================================================
    // MAKE DEFAULT TESTS
    // =========================================================================

    public function test_can_make_entity_default(): void
    {
        $entity1 = LegalEntity::factory()->default()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $entity2 = LegalEntity::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_default' => false,
        ]);

        $this->authenticate();

        $response = $this->postJson("/api/legal-entities/{$entity2->id}/default");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Юридическое лицо установлено по умолчанию',
            ]);

        $entity1->refresh();
        $entity2->refresh();

        $this->assertFalse($entity1->is_default);
        $this->assertTrue($entity2->is_default);
    }

    // =========================================================================
    // DICTIONARIES TESTS
    // =========================================================================

    public function test_can_get_dictionaries(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/legal-entities/dictionaries');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'types',
                    'taxation_systems',
                    'vat_rates',
                ],
            ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('llc', $data['types']);
        $this->assertArrayHasKey('ie', $data['types']);
        $this->assertArrayHasKey('osn', $data['taxation_systems']);
    }
}
