<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\Dish;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ModifierControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected User $user;
    protected Role $adminRole;
    protected string $token;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        // Create admin role with full permissions
        $this->adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Administrator',
            'is_system' => true,
            'is_active' => true,
            'max_discount_percent' => 100,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
        ]);

        // Create permissions
        $permissions = [
            'menu.view', 'menu.create', 'menu.edit', 'menu.delete',
        ];

        foreach ($permissions as $key) {
            $perm = Permission::firstOrCreate([
                'restaurant_id' => $this->restaurant->id,
                'key' => $key,
            ], [
                'name' => $key,
                'group' => explode('.', $key)[0],
            ]);
            $this->adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        // Create user with restaurant_id and is_active
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Create category for dishes
        $this->category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    /**
     * Authenticate the user with Sanctum token
     */
    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    /**
     * Helper to create a modifier
     */
    protected function createModifier(array $attributes = []): Modifier
    {
        return Modifier::create(array_merge([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Modifier',
            'type' => 'single',
            'is_required' => false,
            'min_selections' => 0,
            'max_selections' => 10,
            'sort_order' => 0,
            'is_active' => true,
            'is_global' => true,
        ], $attributes));
    }

    /**
     * Helper to create a modifier option
     */
    protected function createModifierOption(Modifier $modifier, array $attributes = []): ModifierOption
    {
        return ModifierOption::create(array_merge([
            'modifier_id' => $modifier->id,
            'name' => 'Test Option',
            'price' => 50,
            'is_default' => false,
            'sort_order' => 0,
            'is_active' => true,
        ], $attributes));
    }

    // =====================================================
    // INDEX - LIST MODIFIERS
    // =====================================================

    public function test_can_list_modifiers(): void
    {
        $this->createModifier(['name' => 'Modifier One']);
        $this->createModifier(['name' => 'Modifier Two']);

        $this->authenticate();
        $response = $this->getJson("/api/backoffice/modifiers?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonCount(2);

        $names = collect($response->json())->pluck('name')->toArray();
        $this->assertContains('Modifier One', $names);
        $this->assertContains('Modifier Two', $names);
    }

    public function test_list_modifiers_includes_options(): void
    {
        $modifier = $this->createModifier(['name' => 'With Options']);
        $this->createModifierOption($modifier, ['name' => 'Option A']);
        $this->createModifierOption($modifier, ['name' => 'Option B']);

        $this->authenticate();
        $response = $this->getJson("/api/backoffice/modifiers?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertCount(2, $data[0]['options']);
    }

    public function test_list_modifiers_filters_by_is_global(): void
    {
        $this->createModifier(['name' => 'Global Modifier', 'is_global' => true]);
        $this->createModifier(['name' => 'Local Modifier', 'is_global' => false]);

        $this->authenticate();
        $response = $this->getJson("/api/backoffice/modifiers?restaurant_id={$this->restaurant->id}&is_global=1");

        $response->assertOk();

        $names = collect($response->json())->pluck('name')->toArray();
        $this->assertContains('Global Modifier', $names);
        $this->assertNotContains('Local Modifier', $names);
    }

    public function test_list_modifiers_filters_by_active_only(): void
    {
        $this->createModifier(['name' => 'Active Modifier', 'is_active' => true]);
        $this->createModifier(['name' => 'Inactive Modifier', 'is_active' => false]);

        $this->authenticate();
        $response = $this->getJson("/api/backoffice/modifiers?restaurant_id={$this->restaurant->id}&active_only=1");

        $response->assertOk();

        $names = collect($response->json())->pluck('name')->toArray();
        $this->assertContains('Active Modifier', $names);
        $this->assertNotContains('Inactive Modifier', $names);
    }

    public function test_list_modifiers_orders_by_sort_order(): void
    {
        $this->createModifier(['name' => 'Third', 'sort_order' => 3]);
        $this->createModifier(['name' => 'First', 'sort_order' => 1]);
        $this->createModifier(['name' => 'Second', 'sort_order' => 2]);

        $this->authenticate();
        $response = $this->getJson("/api/backoffice/modifiers?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $names = collect($response->json())->pluck('name')->toArray();
        $this->assertEquals(['First', 'Second', 'Third'], $names);
    }

    public function test_list_modifiers_scoped_to_restaurant(): void
    {
        $otherRestaurant = Restaurant::factory()->create();

        $this->createModifier(['name' => 'My Modifier']);
        Modifier::create([
            'restaurant_id' => $otherRestaurant->id,
            'name' => 'Other Modifier',
            'type' => 'single',
            'is_active' => true,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/backoffice/modifiers?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $names = collect($response->json())->pluck('name')->toArray();
        $this->assertContains('My Modifier', $names);
        $this->assertNotContains('Other Modifier', $names);
    }

    // =====================================================
    // SHOW - GET SINGLE MODIFIER
    // =====================================================

    public function test_can_show_modifier(): void
    {
        $modifier = $this->createModifier(['name' => 'Show Me']);
        $this->createModifierOption($modifier, ['name' => 'Option One']);

        $this->authenticate();
        $response = $this->getJson("/api/backoffice/modifiers/{$modifier->id}");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Show Me'])
            ->assertJsonStructure([
                'id',
                'name',
                'type',
                'is_required',
                'min_selections',
                'max_selections',
                'sort_order',
                'is_active',
                'is_global',
                'options',
            ]);
    }

    public function test_show_modifier_includes_options_with_ingredients(): void
    {
        $modifier = $this->createModifier(['name' => 'With Ingredients']);
        $option = $this->createModifierOption($modifier, ['name' => 'Option']);

        $this->authenticate();
        $response = $this->getJson("/api/backoffice/modifiers/{$modifier->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'options' => [
                    '*' => ['id', 'name', 'price', 'ingredients']
                ]
            ]);
    }

    public function test_show_modifier_includes_dishes(): void
    {
        $modifier = $this->createModifier(['name' => 'With Dish']);
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);
        $modifier->dishes()->attach($dish->id);

        $this->authenticate();
        $response = $this->getJson("/api/backoffice/modifiers/{$modifier->id}");

        $response->assertOk()
            ->assertJsonStructure(['dishes']);
    }

    public function test_show_modifier_returns_404_for_nonexistent(): void
    {
        $this->authenticate();
        $response = $this->getJson('/api/backoffice/modifiers/99999');

        $response->assertNotFound();
    }

    // =====================================================
    // STORE - CREATE MODIFIER
    // =====================================================

    public function test_can_create_modifier(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers', [
            'name' => 'New Modifier',
            'type' => 'single',
            'is_required' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Modifier']);

        $this->assertDatabaseHas('modifiers', [
            'name' => 'New Modifier',
            'type' => 'single',
            'is_required' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_create_modifier_with_type_multiple(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers', [
            'name' => 'Multiple Choice',
            'type' => 'multiple',
            'min_selections' => 1,
            'max_selections' => 3,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('modifiers', [
            'name' => 'Multiple Choice',
            'type' => 'multiple',
            'min_selections' => 1,
            'max_selections' => 3,
        ]);
    }

    public function test_create_modifier_with_options(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers', [
            'name' => 'With Options',
            'type' => 'single',
            'restaurant_id' => $this->restaurant->id,
            'options' => [
                ['name' => 'Small', 'price' => 0],
                ['name' => 'Medium', 'price' => 50],
                ['name' => 'Large', 'price' => 100],
            ],
        ]);

        $response->assertStatus(201);

        $modifier = Modifier::where('name', 'With Options')->first();
        $this->assertNotNull($modifier);
        $this->assertCount(3, $modifier->options);

        $this->assertDatabaseHas('modifier_options', [
            'modifier_id' => $modifier->id,
            'name' => 'Small',
            'price' => 0,
        ]);
        $this->assertDatabaseHas('modifier_options', [
            'modifier_id' => $modifier->id,
            'name' => 'Medium',
            'price' => 50,
        ]);
        $this->assertDatabaseHas('modifier_options', [
            'modifier_id' => $modifier->id,
            'name' => 'Large',
            'price' => 100,
        ]);
    }

    public function test_create_modifier_with_default_option(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers', [
            'name' => 'With Default',
            'type' => 'single',
            'restaurant_id' => $this->restaurant->id,
            'options' => [
                ['name' => 'Option A', 'price' => 0, 'is_default' => true],
                ['name' => 'Option B', 'price' => 50, 'is_default' => false],
            ],
        ]);

        $response->assertStatus(201);

        $modifier = Modifier::where('name', 'With Default')->first();
        $this->assertNotNull($modifier);

        $this->assertDatabaseHas('modifier_options', [
            'modifier_id' => $modifier->id,
            'name' => 'Option A',
            'is_default' => true,
        ]);
    }

    public function test_create_modifier_validates_required_fields(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers', [
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_modifier_validates_type(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers', [
            'name' => 'Invalid Type',
            'type' => 'invalid',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_modifier_defaults(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers', [
            'name' => 'Defaults Test',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('modifiers', [
            'name' => 'Defaults Test',
            'type' => 'single',
            'is_required' => false,
            'min_selections' => 0,
            'max_selections' => 10,
            'is_active' => true,
            'is_global' => true,
        ]);
    }

    // =====================================================
    // UPDATE - MODIFY MODIFIER
    // =====================================================

    public function test_can_update_modifier(): void
    {
        $modifier = $this->createModifier(['name' => 'Original Name']);

        $this->authenticate();
        $response = $this->putJson("/api/backoffice/modifiers/{$modifier->id}", [
            'name' => 'Updated Name',
            'is_required' => true,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('modifiers', [
            'id' => $modifier->id,
            'name' => 'Updated Name',
            'is_required' => true,
        ]);
    }

    public function test_update_modifier_type(): void
    {
        $modifier = $this->createModifier(['type' => 'single']);

        $this->authenticate();
        $response = $this->putJson("/api/backoffice/modifiers/{$modifier->id}", [
            'type' => 'multiple',
            'min_selections' => 1,
            'max_selections' => 5,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('modifiers', [
            'id' => $modifier->id,
            'type' => 'multiple',
            'min_selections' => 1,
            'max_selections' => 5,
        ]);
    }

    public function test_update_modifier_with_new_options(): void
    {
        $modifier = $this->createModifier();

        $this->authenticate();
        $response = $this->putJson("/api/backoffice/modifiers/{$modifier->id}", [
            'options' => [
                ['name' => 'New Option 1', 'price' => 10],
                ['name' => 'New Option 2', 'price' => 20],
            ],
        ]);

        $response->assertOk();

        $modifier->refresh();
        $this->assertCount(2, $modifier->options);
    }

    public function test_update_modifier_updates_existing_options(): void
    {
        $modifier = $this->createModifier();
        $option = $this->createModifierOption($modifier, ['name' => 'Old Name', 'price' => 50]);

        $this->authenticate();
        $response = $this->putJson("/api/backoffice/modifiers/{$modifier->id}", [
            'options' => [
                ['id' => $option->id, 'name' => 'New Name', 'price' => 100],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('modifier_options', [
            'id' => $option->id,
            'name' => 'New Name',
            'price' => 100,
        ]);
    }

    public function test_update_modifier_removes_unincluded_options(): void
    {
        $modifier = $this->createModifier();
        $option1 = $this->createModifierOption($modifier, ['name' => 'Keep Me']);
        $option2 = $this->createModifierOption($modifier, ['name' => 'Delete Me']);

        $this->authenticate();
        $response = $this->putJson("/api/backoffice/modifiers/{$modifier->id}", [
            'options' => [
                ['id' => $option1->id, 'name' => 'Keep Me', 'price' => 50],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('modifier_options', ['id' => $option1->id]);
        $this->assertDatabaseMissing('modifier_options', ['id' => $option2->id]);
    }

    public function test_update_modifier_toggles_active_status(): void
    {
        $modifier = $this->createModifier(['is_active' => true]);

        $this->authenticate();
        $response = $this->putJson("/api/backoffice/modifiers/{$modifier->id}", [
            'is_active' => false,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('modifiers', [
            'id' => $modifier->id,
            'is_active' => false,
        ]);
    }

    public function test_update_modifier_validates_type(): void
    {
        $modifier = $this->createModifier();

        $this->authenticate();
        $response = $this->putJson("/api/backoffice/modifiers/{$modifier->id}", [
            'type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    // =====================================================
    // DESTROY - DELETE MODIFIER
    // =====================================================

    public function test_can_delete_modifier(): void
    {
        $modifier = $this->createModifier();

        $this->authenticate();
        $response = $this->deleteJson("/api/backoffice/modifiers/{$modifier->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Deleted']);

        $this->assertDatabaseMissing('modifiers', ['id' => $modifier->id]);
    }

    public function test_delete_modifier_cascades_to_options(): void
    {
        $modifier = $this->createModifier();
        $option = $this->createModifierOption($modifier);

        $this->authenticate();
        $response = $this->deleteJson("/api/backoffice/modifiers/{$modifier->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('modifiers', ['id' => $modifier->id]);
        $this->assertDatabaseMissing('modifier_options', ['id' => $option->id]);
    }

    public function test_delete_nonexistent_modifier_returns_404(): void
    {
        $this->authenticate();
        $response = $this->deleteJson('/api/backoffice/modifiers/99999');

        $response->assertNotFound();
    }

    // =====================================================
    // STORE OPTION - ADD OPTION TO MODIFIER
    // =====================================================

    public function test_can_add_option_to_modifier(): void
    {
        $modifier = $this->createModifier();

        $this->authenticate();
        $response = $this->postJson("/api/backoffice/modifiers/{$modifier->id}/options", [
            'name' => 'New Option',
            'price' => 75,
            'is_default' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Option']);

        $this->assertDatabaseHas('modifier_options', [
            'modifier_id' => $modifier->id,
            'name' => 'New Option',
            'price' => 75,
        ]);
    }

    public function test_add_option_with_default_price(): void
    {
        $modifier = $this->createModifier();

        $this->authenticate();
        $response = $this->postJson("/api/backoffice/modifiers/{$modifier->id}/options", [
            'name' => 'Free Option',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('modifier_options', [
            'modifier_id' => $modifier->id,
            'name' => 'Free Option',
            'price' => 0,
        ]);
    }

    public function test_add_option_with_sort_order(): void
    {
        $modifier = $this->createModifier();
        $this->createModifierOption($modifier, ['name' => 'First', 'sort_order' => 0]);

        $this->authenticate();
        $response = $this->postJson("/api/backoffice/modifiers/{$modifier->id}/options", [
            'name' => 'Second',
            'sort_order' => 1,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('modifier_options', [
            'modifier_id' => $modifier->id,
            'name' => 'Second',
            'sort_order' => 1,
        ]);
    }

    public function test_add_option_validates_required_name(): void
    {
        $modifier = $this->createModifier();

        $this->authenticate();
        $response = $this->postJson("/api/backoffice/modifiers/{$modifier->id}/options", [
            'price' => 50,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_add_option_to_nonexistent_modifier_returns_404(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers/99999/options', [
            'name' => 'Test Option',
        ]);

        $response->assertNotFound();
    }

    // =====================================================
    // UPDATE OPTION - MODIFY OPTION
    // =====================================================

    public function test_can_update_option(): void
    {
        $modifier = $this->createModifier();
        $option = $this->createModifierOption($modifier, ['name' => 'Old Name', 'price' => 50]);

        $this->authenticate();
        $response = $this->putJson("/api/backoffice/modifiers/options/{$option->id}", [
            'name' => 'New Name',
            'price' => 100,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'New Name']);

        $this->assertDatabaseHas('modifier_options', [
            'id' => $option->id,
            'name' => 'New Name',
            'price' => 100,
        ]);
    }

    public function test_update_option_default_status(): void
    {
        $modifier = $this->createModifier();
        $option = $this->createModifierOption($modifier, ['is_default' => false]);

        $this->authenticate();
        $response = $this->putJson("/api/backoffice/modifiers/options/{$option->id}", [
            'is_default' => true,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('modifier_options', [
            'id' => $option->id,
            'is_default' => true,
        ]);
    }

    public function test_update_option_active_status(): void
    {
        $modifier = $this->createModifier();
        $option = $this->createModifierOption($modifier, ['is_active' => true]);

        $this->authenticate();
        $response = $this->putJson("/api/backoffice/modifiers/options/{$option->id}", [
            'is_active' => false,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('modifier_options', [
            'id' => $option->id,
            'is_active' => false,
        ]);
    }

    public function test_update_nonexistent_option_returns_404(): void
    {
        $this->authenticate();
        $response = $this->putJson('/api/backoffice/modifiers/options/99999', [
            'name' => 'Updated',
        ]);

        $response->assertNotFound();
    }

    // =====================================================
    // DESTROY OPTION - DELETE OPTION
    // =====================================================

    public function test_can_delete_option(): void
    {
        $modifier = $this->createModifier();
        $option = $this->createModifierOption($modifier);

        $this->authenticate();
        $response = $this->deleteJson("/api/backoffice/modifiers/options/{$option->id}");

        $response->assertOk()
            ->assertJson(['message' => 'Deleted']);

        $this->assertDatabaseMissing('modifier_options', ['id' => $option->id]);
    }

    public function test_delete_nonexistent_option_returns_404(): void
    {
        $this->authenticate();
        $response = $this->deleteJson('/api/backoffice/modifiers/options/99999');

        $response->assertNotFound();
    }

    // =====================================================
    // ATTACH TO DISH - LINK MODIFIER TO DISH
    // =====================================================

    public function test_can_attach_modifier_to_dish(): void
    {
        $modifier = $this->createModifier();
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers/attach-dish', [
            'dish_id' => $dish->id,
            'modifier_id' => $modifier->id,
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Attached']);

        $this->assertTrue($modifier->dishes()->where('dish_id', $dish->id)->exists());
    }

    public function test_attach_modifier_with_sort_order(): void
    {
        $modifier = $this->createModifier();
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers/attach-dish', [
            'dish_id' => $dish->id,
            'modifier_id' => $modifier->id,
            'sort_order' => 5,
        ]);

        $response->assertOk();

        $pivotData = $modifier->dishes()->where('dish_id', $dish->id)->first()->pivot ?? null;
        // Check modifier is attached
        $this->assertTrue($modifier->dishes()->where('dish_id', $dish->id)->exists());
    }

    public function test_attach_modifier_is_idempotent(): void
    {
        $modifier = $this->createModifier();
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        $this->authenticate();

        // First attach
        $this->postJson('/api/backoffice/modifiers/attach-dish', [
            'dish_id' => $dish->id,
            'modifier_id' => $modifier->id,
        ]);

        // Second attach should not fail
        $response = $this->postJson('/api/backoffice/modifiers/attach-dish', [
            'dish_id' => $dish->id,
            'modifier_id' => $modifier->id,
        ]);

        $response->assertOk();

        // Should still only have one connection
        $this->assertEquals(1, $modifier->dishes()->where('dish_id', $dish->id)->count());
    }

    public function test_attach_validates_dish_exists(): void
    {
        $modifier = $this->createModifier();

        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers/attach-dish', [
            'dish_id' => 99999,
            'modifier_id' => $modifier->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['dish_id']);
    }

    public function test_attach_validates_modifier_exists(): void
    {
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers/attach-dish', [
            'dish_id' => $dish->id,
            'modifier_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['modifier_id']);
    }

    public function test_attach_validates_required_fields(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers/attach-dish', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['dish_id', 'modifier_id']);
    }

    // =====================================================
    // DETACH FROM DISH - UNLINK MODIFIER FROM DISH
    // =====================================================

    public function test_can_detach_modifier_from_dish(): void
    {
        $modifier = $this->createModifier();
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);
        $modifier->dishes()->attach($dish->id);

        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers/detach-dish', [
            'dish_id' => $dish->id,
            'modifier_id' => $modifier->id,
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Detached']);

        $this->assertFalse($modifier->dishes()->where('dish_id', $dish->id)->exists());
    }

    public function test_detach_nonexistent_link_does_not_fail(): void
    {
        $modifier = $this->createModifier();
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers/detach-dish', [
            'dish_id' => $dish->id,
            'modifier_id' => $modifier->id,
        ]);

        $response->assertOk();
    }

    public function test_detach_validates_required_fields(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers/detach-dish', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['dish_id', 'modifier_id']);
    }

    // =====================================================
    // DISH MODIFIERS - GET MODIFIERS FOR DISH
    // =====================================================

    public function test_can_get_dish_modifiers(): void
    {
        $modifier1 = $this->createModifier(['name' => 'Modifier 1']);
        $modifier2 = $this->createModifier(['name' => 'Modifier 2']);
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);
        $dish->modifiers()->attach([$modifier1->id, $modifier2->id]);

        $this->authenticate();
        $response = $this->getJson("/api/backoffice/menu/dishes/{$dish->id}/modifiers");

        $response->assertOk()
            ->assertJsonCount(2);

        $names = collect($response->json())->pluck('name')->toArray();
        $this->assertContains('Modifier 1', $names);
        $this->assertContains('Modifier 2', $names);
    }

    public function test_dish_modifiers_includes_options(): void
    {
        $modifier = $this->createModifier();
        $this->createModifierOption($modifier, ['name' => 'Option A']);
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);
        $dish->modifiers()->attach($modifier->id);

        $this->authenticate();
        $response = $this->getJson("/api/backoffice/menu/dishes/{$dish->id}/modifiers");

        $response->assertOk()
            ->assertJsonStructure([
                '*' => ['id', 'name', 'options']
            ]);
    }

    public function test_dish_with_no_modifiers_returns_empty(): void
    {
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/backoffice/menu/dishes/{$dish->id}/modifiers");

        $response->assertOk()
            ->assertJsonCount(0);
    }

    // =====================================================
    // SAVE DISH MODIFIERS - BULK UPDATE MODIFIERS FOR DISH
    // =====================================================

    public function test_can_save_dish_modifiers(): void
    {
        $modifier1 = $this->createModifier(['name' => 'Modifier 1']);
        $modifier2 = $this->createModifier(['name' => 'Modifier 2']);
        $modifier3 = $this->createModifier(['name' => 'Modifier 3']);
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        // Attach using pivot table directly to avoid the ambiguous sort_order issue
        DB::table('dish_modifier')->insert([
            'dish_id' => $dish->id,
            'modifier_id' => $modifier1->id,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/backoffice/menu/dishes/{$dish->id}/modifiers", [
            'modifier_ids' => [$modifier2->id, $modifier3->id],
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Saved']);

        // Should have modifier2 and modifier3, not modifier1
        $attachedIds = DB::table('dish_modifier')
            ->where('dish_id', $dish->id)
            ->pluck('modifier_id')
            ->toArray();
        $this->assertContains($modifier2->id, $attachedIds);
        $this->assertContains($modifier3->id, $attachedIds);
        $this->assertNotContains($modifier1->id, $attachedIds);
    }

    public function test_save_dish_modifiers_with_empty_array_removes_all(): void
    {
        $modifier = $this->createModifier();
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);
        $dish->modifiers()->attach($modifier->id);

        $this->authenticate();
        $response = $this->postJson("/api/backoffice/menu/dishes/{$dish->id}/modifiers", [
            'modifier_ids' => [],
        ]);

        $response->assertOk();

        $dish->refresh();
        $this->assertCount(0, $dish->modifiers);
    }

    public function test_save_dish_modifiers_validates_modifier_ids(): void
    {
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        $this->authenticate();
        $response = $this->postJson("/api/backoffice/menu/dishes/{$dish->id}/modifiers", [
            'modifier_ids' => [99999],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['modifier_ids.0']);
    }

    // =====================================================
    // AUTHENTICATION TESTS
    // =====================================================

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/backoffice/modifiers');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_create_returns_401(): void
    {
        $response = $this->postJson('/api/backoffice/modifiers', [
            'name' => 'Test',
        ]);

        $response->assertStatus(401);
    }

    // =====================================================
    // EDGE CASES
    // =====================================================

    public function test_modifier_name_max_length_validation(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers', [
            'name' => str_repeat('a', 101), // 101 characters
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_option_name_max_length_validation(): void
    {
        $modifier = $this->createModifier();

        $this->authenticate();
        $response = $this->postJson("/api/backoffice/modifiers/{$modifier->id}/options", [
            'name' => str_repeat('a', 101), // 101 characters
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_modifier_min_max_selections_validation(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/backoffice/modifiers', [
            'name' => 'Test',
            'min_selections' => -1,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['min_selections']);

        $response = $this->postJson('/api/backoffice/modifiers', [
            'name' => 'Test',
            'max_selections' => 0,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['max_selections']);
    }

    public function test_options_sort_order_preserved(): void
    {
        $modifier = $this->createModifier();
        $this->createModifierOption($modifier, ['name' => 'Third', 'sort_order' => 3]);
        $this->createModifierOption($modifier, ['name' => 'First', 'sort_order' => 1]);
        $this->createModifierOption($modifier, ['name' => 'Second', 'sort_order' => 2]);

        $this->authenticate();
        $response = $this->getJson("/api/backoffice/modifiers/{$modifier->id}");

        $response->assertOk();

        $optionNames = collect($response->json('options'))->pluck('name')->toArray();
        $this->assertEquals(['First', 'Second', 'Third'], $optionNames);
    }
}
