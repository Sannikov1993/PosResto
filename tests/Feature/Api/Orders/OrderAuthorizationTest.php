<?php

namespace Tests\Feature\Api\Orders;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Table;
use App\Models\Zone;
use App\Models\Dish;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected User $user;
    protected Table $table;
    protected Zone $zone;
    protected Dish $dish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'role' => 'cashier',
        ]);
        $this->zone = Zone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $this->table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
        ]);
        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $this->dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
        ]);
    }

    protected function authenticate(User $user = null): void
    {
        $user = $user ?? $this->user;
        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    // ===== AUTHORIZE - RESTAURANT ID VALIDATION =====

    public function test_cannot_create_order_for_other_restaurant(): void
    {
        $this->authenticate();

        $otherRestaurant = Restaurant::factory()->create();
        $otherZone = Zone::factory()->create(['restaurant_id' => $otherRestaurant->id]);
        $otherTable = Table::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'zone_id' => $otherZone->id,
        ]);

        $response = $this->postJson('/api/orders', [
            'restaurant_id' => $otherRestaurant->id,
            'type' => 'dine_in',
            'table_id' => $otherTable->id,
            'items' => [
                ['dish_id' => $this->dish->id, 'quantity' => 1],
            ],
        ]);

        $this->assertTrue(
            $response->status() === 403 || $response->status() === 422,
            'User should not be able to create orders for another restaurant. Got status: ' . $response->status()
        );
    }

    public function test_can_create_order_for_own_restaurant(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/orders', [
            'type' => 'dine_in',
            'table_id' => $this->table->id,
            'items' => [
                ['dish_id' => $this->dish->id, 'quantity' => 1],
            ],
        ]);

        $this->assertTrue(
            in_array($response->status(), [200, 201]),
            'User should be able to create orders for own restaurant. Got: ' . $response->status()
        );
    }

    // ===== TABLE_ID CONDITIONAL VALIDATION =====

    public function test_dine_in_requires_table_id(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/orders', [
            'type' => 'dine_in',
            'items' => [
                ['dish_id' => $this->dish->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['table_id']);
    }

    public function test_delivery_does_not_require_table_id(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/orders', [
            'type' => 'delivery',
            'delivery_address' => 'Test address, 123',
            'phone' => '+79001234567',
            'items' => [
                ['dish_id' => $this->dish->id, 'quantity' => 1],
            ],
        ]);

        $this->assertNotEquals(422, $response->status(),
            'Delivery order should not require table_id');
    }

    public function test_delivery_prohibits_table_id(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/orders', [
            'type' => 'delivery',
            'table_id' => $this->table->id,
            'delivery_address' => 'Test address, 123',
            'items' => [
                ['dish_id' => $this->dish->id, 'quantity' => 1],
            ],
        ]);

        // table_id should be prohibited for delivery type
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['table_id']);
    }

    public function test_pickup_prohibits_table_id(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/orders', [
            'type' => 'pickup',
            'table_id' => $this->table->id,
            'items' => [
                ['dish_id' => $this->dish->id, 'quantity' => 1],
            ],
        ]);

        // table_id should be prohibited for pickup type
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['table_id']);
    }

    // ===== ITEMS VALIDATION =====

    public function test_order_requires_at_least_one_item(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/orders', [
            'type' => 'pickup',
            'items' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_order_requires_items_field(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/orders', [
            'type' => 'pickup',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    // ===== AUTHENTICATION =====

    public function test_unauthenticated_cannot_create_order(): void
    {
        $response = $this->postJson('/api/orders', [
            'type' => 'pickup',
            'items' => [
                ['dish_id' => $this->dish->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_user_without_restaurant_cannot_create_order(): void
    {
        $noRestaurantUser = User::factory()->create([
            'restaurant_id' => null,
            'is_active' => true,
            'role' => 'cashier',
        ]);

        $this->authenticate($noRestaurantUser);

        $response = $this->postJson('/api/orders', [
            'type' => 'pickup',
            'items' => [
                ['dish_id' => $this->dish->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(403);
    }
}
