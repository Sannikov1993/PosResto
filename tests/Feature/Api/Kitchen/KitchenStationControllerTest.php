<?php

namespace Tests\Feature\Api\Kitchen;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\KitchenStation;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KitchenStationControllerTest extends TestCase
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

    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    // =====================================================
    // AUTHENTICATION TESTS
    // =====================================================

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/kitchen-stations');

        $response->assertUnauthorized();
    }

    public function test_active_requires_authentication(): void
    {
        $response = $this->getJson('/api/kitchen-stations/active');

        $response->assertUnauthorized();
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/kitchen-stations', [
            'name' => 'Test Station',
        ]);

        $response->assertUnauthorized();
    }

    public function test_show_requires_authentication(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->getJson("/api/kitchen-stations/{$station->id}");

        $response->assertUnauthorized();
    }

    public function test_update_requires_authentication(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->putJson("/api/kitchen-stations/{$station->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertUnauthorized();
    }

    public function test_destroy_requires_authentication(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->deleteJson("/api/kitchen-stations/{$station->id}");

        $response->assertUnauthorized();
    }

    public function test_toggle_requires_authentication(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->patchJson("/api/kitchen-stations/{$station->id}/toggle");

        $response->assertUnauthorized();
    }

    public function test_reorder_requires_authentication(): void
    {
        $response = $this->postJson('/api/kitchen-stations/reorder', [
            'stations' => [],
        ]);

        $response->assertUnauthorized();
    }

    public function test_bar_check_requires_authentication(): void
    {
        $response = $this->getJson('/api/bar/check');

        $response->assertUnauthorized();
    }

    public function test_bar_orders_requires_authentication(): void
    {
        $response = $this->getJson('/api/bar/orders');

        $response->assertUnauthorized();
    }

    public function test_bar_item_status_requires_authentication(): void
    {
        $response = $this->postJson('/api/bar/item-status', [
            'item_id' => 1,
            'status' => 'ready',
        ]);

        $response->assertUnauthorized();
    }

    // =====================================================
    // RESTAURANT ISOLATION TESTS
    // =====================================================

    public function test_index_only_returns_stations_from_users_restaurant(): void
    {
        // Create stations for user's restaurant
        KitchenStation::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Create stations for another restaurant
        $otherRestaurant = Restaurant::factory()->create();
        KitchenStation::factory()->count(3)->create([
            'restaurant_id' => $otherRestaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_active_only_returns_active_stations_from_users_restaurant(): void
    {
        // Create active stations for user's restaurant
        KitchenStation::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        // Create inactive station for user's restaurant
        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => false,
        ]);

        // Create stations for another restaurant
        $otherRestaurant = Restaurant::factory()->create();
        KitchenStation::factory()->count(2)->create([
            'restaurant_id' => $otherRestaurant->id,
            'is_active' => true,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations/active?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_store_creates_station_in_users_restaurant(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'New Station',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('kitchen_stations', [
            'name' => 'New Station',
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_bar_check_only_checks_users_restaurant(): void
    {
        // Create bar for another restaurant
        $otherRestaurant = Restaurant::factory()->create();
        KitchenStation::factory()->bar()->create([
            'restaurant_id' => $otherRestaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/bar/check?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'has_bar' => false,
            ]);
    }

    // =====================================================
    // INDEX (LIST ALL STATIONS) TESTS
    // =====================================================

    public function test_can_list_all_kitchen_stations(): void
    {
        KitchenStation::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'icon',
                        'color',
                        'description',
                        'notification_sound',
                        'sort_order',
                        'is_active',
                        'is_bar',
                        'dishes_count',
                    ],
                ],
            ])
            ->assertJson(['success' => true]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_includes_dishes_count(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        Dish::factory()->count(5)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $station->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $stationData = collect($response->json('data'))->firstWhere('id', $station->id);
        $this->assertEquals(5, $stationData['dishes_count']);
    }

    public function test_index_stations_are_ordered_by_sort_order(): void
    {
        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Third',
            'sort_order' => 3,
        ]);

        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'First',
            'sort_order' => 1,
        ]);

        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Second',
            'sort_order' => 2,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['First', 'Second', 'Third'], $names);
    }

    public function test_index_returns_empty_array_when_no_stations(): void
    {
        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    // =====================================================
    // ACTIVE (LIST ONLY ACTIVE STATIONS) TESTS
    // =====================================================

    public function test_can_list_only_active_stations(): void
    {
        KitchenStation::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        KitchenStation::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => false,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations/active?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(2, $response->json('data'));

        foreach ($response->json('data') as $station) {
            $this->assertTrue($station['is_active']);
        }
    }

    public function test_active_stations_are_ordered_by_sort_order(): void
    {
        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Z Station',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'A Station',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations/active?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['A Station', 'Z Station'], $names);
    }

    // =====================================================
    // STORE (CREATE STATION) TESTS
    // =====================================================

    public function test_can_create_kitchen_station_with_all_fields(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Горячий цех',
                'slug' => 'hot-station',
                'icon' => 'fire',
                'color' => '#FF5733',
                'description' => 'Основной горячий цех',
                'notification_sound' => 'kitchen',
                'sort_order' => 5,
                'is_active' => true,
                'is_bar' => false,
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Цех создан',
            ])
            ->assertJsonPath('data.name', 'Горячий цех')
            ->assertJsonPath('data.slug', 'hot-station')
            ->assertJsonPath('data.icon', 'fire')
            ->assertJsonPath('data.color', '#FF5733')
            ->assertJsonPath('data.notification_sound', 'kitchen');

        $this->assertDatabaseHas('kitchen_stations', [
            'name' => 'Горячий цех',
            'slug' => 'hot-station',
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_can_create_kitchen_station_with_minimal_fields(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Simple Station',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Цех создан',
            ]);

        $this->assertDatabaseHas('kitchen_stations', [
            'name' => 'Simple Station',
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_store_generates_slug_from_name_if_not_provided(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Test Station',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201);

        $station = KitchenStation::where('name', 'Test Station')->first();
        $this->assertNotNull($station);
        $this->assertNotEmpty($station->slug);
        $this->assertStringContainsString('test', strtolower($station->slug));
    }

    public function test_store_generates_unique_slug_when_duplicate_exists(): void
    {
        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'slug' => 'test-station',
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Test Station',
                'slug' => 'test-station',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201);

        $station = KitchenStation::where('name', 'Test Station')->first();
        $this->assertNotNull($station);
        $this->assertNotEquals('test-station', $station->slug);
        $this->assertStringStartsWith('test-station', $station->slug);
    }

    public function test_store_generates_fallback_slug_for_cyrillic_only_name(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Горячий цех',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201);

        $station = KitchenStation::where('name', 'Горячий цех')->first();
        $this->assertNotNull($station);
        $this->assertNotEmpty($station->slug);
    }

    public function test_store_sets_default_color_if_not_provided(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Test Station',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.color', '#6366F1');
    }

    public function test_store_sets_default_notification_sound_if_not_provided(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Test Station',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.notification_sound', 'bell');
    }

    public function test_store_sets_default_is_active_to_true(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Test Station',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_active', true);
    }

    public function test_store_sets_default_is_bar_to_false(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Test Station',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_bar', false);
    }

    public function test_can_create_bar_station(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Бар',
                'is_bar' => true,
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_bar', true);

        $this->assertDatabaseHas('kitchen_stations', [
            'name' => 'Бар',
            'is_bar' => true,
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_store_validates_required_name(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_name_max_length(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => str_repeat('a', 51),
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_slug_max_length(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Test',
                'slug' => str_repeat('a', 51),
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_validates_icon_max_length(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Test',
                'icon' => str_repeat('a', 21),
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['icon']);
    }

    public function test_store_validates_color_max_length(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Test',
                'color' => '#FFFFFFFF',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['color']);
    }

    public function test_store_validates_notification_sound_values(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Test',
                'notification_sound' => 'invalid_sound',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['notification_sound']);
    }

    public function test_store_accepts_valid_notification_sounds(): void
    {
        $validSounds = ['bell', 'chime', 'ding', 'kitchen', 'alert', 'gong'];

        $this->authenticate();
        foreach ($validSounds as $sound) {
            $response = $this->postJson('/api/kitchen-stations', [
                'name' => "Station with {$sound}",
                'notification_sound' => $sound,
                'restaurant_id' => $this->restaurant->id,
            ]);

            $response->assertStatus(201)
                ->assertJsonPath('data.notification_sound', $sound);
        }
    }

    public function test_store_validates_sort_order_is_integer(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Test',
                'sort_order' => 'not-a-number',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sort_order']);
    }

    public function test_store_validates_is_active_is_boolean(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Test',
                'is_active' => 'yes',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_store_validates_is_bar_is_boolean(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Test',
                'is_bar' => 'yes',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['is_bar']);
    }

    // =====================================================
    // SHOW (GET SINGLE STATION) TESTS
    // =====================================================

    public function test_can_show_kitchen_station(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Station',
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations/{$station->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $station->id,
                    'name' => 'Test Station',
                ],
            ]);
    }

    public function test_show_includes_dishes_count(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        Dish::factory()->count(7)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $station->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations/{$station->id}");

        $response->assertOk()
            ->assertJsonPath('data.dishes_count', 7);
    }

    public function test_show_returns_404_for_nonexistent_station(): void
    {
        $this->authenticate();
        $response = $this->getJson('/api/kitchen-stations/99999');

        $response->assertNotFound();
    }

    // =====================================================
    // UPDATE (MODIFY STATION) TESTS
    // =====================================================

    public function test_can_update_kitchen_station(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Old Name',
            'color' => '#000000',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-stations/{$station->id}", [
                'name' => 'New Name',
                'color' => '#FFFFFF',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Цех обновлён',
            ])
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.color', '#FFFFFF');

        $this->assertDatabaseHas('kitchen_stations', [
            'id' => $station->id,
            'name' => 'New Name',
            'color' => '#FFFFFF',
        ]);
    }

    public function test_can_update_single_field(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Original Name',
            'description' => 'Original Description',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-stations/{$station->id}", [
                'description' => 'New Description',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('kitchen_stations', [
            'id' => $station->id,
            'name' => 'Original Name',
            'description' => 'New Description',
        ]);
    }

    public function test_update_rejects_duplicate_slug_in_same_restaurant(): void
    {
        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'slug' => 'existing-slug',
        ]);

        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'slug' => 'my-slug',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-stations/{$station->id}", [
                'slug' => 'existing-slug',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Цех с таким slug уже существует',
            ]);
    }

    public function test_update_allows_same_slug_on_same_station(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'slug' => 'my-slug',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-stations/{$station->id}", [
                'slug' => 'my-slug',
                'name' => 'Updated Name',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('kitchen_stations', [
            'id' => $station->id,
            'slug' => 'my-slug',
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_allows_duplicate_slug_in_different_restaurant(): void
    {
        $otherRestaurant = Restaurant::factory()->create();
        KitchenStation::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'slug' => 'same-slug',
        ]);

        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'slug' => 'original-slug',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-stations/{$station->id}", [
                'slug' => 'same-slug',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('kitchen_stations', [
            'id' => $station->id,
            'slug' => 'same-slug',
        ]);
    }

    public function test_can_update_is_bar_flag(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_bar' => false,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-stations/{$station->id}", [
                'is_bar' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_bar', true);

        $this->assertDatabaseHas('kitchen_stations', [
            'id' => $station->id,
            'is_bar' => true,
        ]);
    }

    public function test_can_update_notification_sound(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'notification_sound' => 'bell',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-stations/{$station->id}", [
                'notification_sound' => 'gong',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.notification_sound', 'gong');
    }

    public function test_update_validates_notification_sound(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-stations/{$station->id}", [
                'notification_sound' => 'invalid',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['notification_sound']);
    }

    public function test_update_returns_404_for_nonexistent_station(): void
    {
        $this->authenticate();
        $response = $this->putJson('/api/kitchen-stations/99999', [
                'name' => 'New Name',
            ]);

        $response->assertNotFound();
    }

    // =====================================================
    // DESTROY (DELETE STATION) TESTS
    // =====================================================

    public function test_can_delete_kitchen_station(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->deleteJson("/api/kitchen-stations/{$station->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Цех удалён',
            ]);

        $this->assertDatabaseMissing('kitchen_stations', [
            'id' => $station->id,
        ]);
    }

    public function test_delete_nullifies_dish_kitchen_station_references(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $station->id,
        ]);

        $this->authenticate();
        $response = $this->deleteJson("/api/kitchen-stations/{$station->id}");

        $response->assertOk();

        $dish->refresh();
        $this->assertNull($dish->kitchen_station_id);
    }

    public function test_delete_returns_404_for_nonexistent_station(): void
    {
        $this->authenticate();
        $response = $this->deleteJson('/api/kitchen-stations/99999');

        $response->assertNotFound();
    }

    // =====================================================
    // TOGGLE (ACTIVATE/DEACTIVATE STATION) TESTS
    // =====================================================

    public function test_can_toggle_station_from_active_to_inactive(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        $this->authenticate();
        $response = $this->patchJson("/api/kitchen-stations/{$station->id}/toggle");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Цех деактивирован',
            ])
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('kitchen_stations', [
            'id' => $station->id,
            'is_active' => false,
        ]);
    }

    public function test_can_toggle_station_from_inactive_to_active(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => false,
        ]);

        $this->authenticate();
        $response = $this->patchJson("/api/kitchen-stations/{$station->id}/toggle");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Цех активирован',
            ])
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('kitchen_stations', [
            'id' => $station->id,
            'is_active' => true,
        ]);
    }

    public function test_toggle_returns_404_for_nonexistent_station(): void
    {
        $this->authenticate();
        $response = $this->patchJson('/api/kitchen-stations/99999/toggle');

        $response->assertNotFound();
    }

    // =====================================================
    // REORDER (CHANGE SORT ORDER) TESTS
    // =====================================================

    public function test_can_reorder_kitchen_stations(): void
    {
        $station1 = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'sort_order' => 1,
        ]);

        $station2 = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'sort_order' => 2,
        ]);

        $station3 = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'sort_order' => 3,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations/reorder', [
                'stations' => [
                    ['id' => $station1->id, 'sort_order' => 3],
                    ['id' => $station2->id, 'sort_order' => 1],
                    ['id' => $station3->id, 'sort_order' => 2],
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Порядок обновлён',
            ]);

        $this->assertDatabaseHas('kitchen_stations', ['id' => $station1->id, 'sort_order' => 3]);
        $this->assertDatabaseHas('kitchen_stations', ['id' => $station2->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('kitchen_stations', ['id' => $station3->id, 'sort_order' => 2]);
    }

    public function test_reorder_validates_stations_array_required(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations/reorder', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stations']);
    }

    public function test_reorder_validates_stations_is_array(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations/reorder', [
                'stations' => 'not-an-array',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stations']);
    }

    public function test_reorder_validates_station_id_required(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations/reorder', [
                'stations' => [
                    ['sort_order' => 1],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stations.0.id']);
    }

    public function test_reorder_validates_station_id_exists(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations/reorder', [
                'stations' => [
                    ['id' => 99999, 'sort_order' => 1],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stations.0.id']);
    }

    public function test_reorder_validates_sort_order_required(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations/reorder', [
                'stations' => [
                    ['id' => $station->id],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stations.0.sort_order']);
    }

    public function test_reorder_validates_sort_order_is_integer(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations/reorder', [
                'stations' => [
                    ['id' => $station->id, 'sort_order' => 'first'],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stations.0.sort_order']);
    }

    // =====================================================
    // BAR CHECK TESTS
    // =====================================================

    public function test_bar_check_returns_true_when_bar_exists(): void
    {
        $barStation = KitchenStation::factory()->bar()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/bar/check?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'has_bar' => true,
            ])
            ->assertJsonPath('data.id', $barStation->id);
    }

    public function test_bar_check_returns_false_when_no_bar(): void
    {
        // Create non-bar station
        KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_bar' => false,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/bar/check?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'has_bar' => false,
            ]);
    }

    public function test_bar_check_returns_false_when_bar_inactive(): void
    {
        KitchenStation::factory()->bar()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => false,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/bar/check?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'has_bar' => false,
            ]);
    }

    // =====================================================
    // BAR ORDERS TESTS
    // =====================================================

    public function test_bar_orders_returns_empty_when_no_bar(): void
    {
        $this->authenticate();
        $response = $this->getJson("/api/bar/orders?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'Бар не настроен',
                'data' => [],
            ]);
    }

    public function test_bar_orders_returns_bar_items(): void
    {
        $barStation = KitchenStation::factory()->bar()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $barDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $barStation->id,
        ]);

        $table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $table->id,
            'user_id' => $this->user->id,
            'status' => 'cooking',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $barDish->id,
            'status' => 'cooking',
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/bar/orders?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'order_id',
                        'order_number',
                        'table',
                        'waiter',
                        'dish_id',
                        'dish_name',
                        'quantity',
                        'status',
                        'cooking_started_at',
                        'notes',
                        'created_at',
                        'order_type',
                    ],
                ],
                'station',
                'counts' => ['new', 'in_progress', 'ready'],
            ]);
    }

    public function test_bar_orders_excludes_non_bar_items(): void
    {
        $barStation = KitchenStation::factory()->bar()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $kitchenStation = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_bar' => false,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $barDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $barStation->id,
        ]);

        $kitchenDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $kitchenStation->id,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'cooking',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $barDish->id,
            'status' => 'cooking',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $kitchenDish->id,
            'status' => 'cooking',
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/bar/orders?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $barItems = $response->json('data');
        $this->assertCount(1, $barItems);
        $this->assertEquals($barDish->id, $barItems[0]['dish_id']);
    }

    public function test_bar_orders_returns_correct_counts(): void
    {
        $barStation = KitchenStation::factory()->bar()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $barDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $barStation->id,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'cooking',
        ]);

        // New item (cooking, no cooking_started_at)
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $barDish->id,
            'status' => 'cooking',
            'cooking_started_at' => null,
        ]);

        // In progress item (cooking, has cooking_started_at)
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $barDish->id,
            'status' => 'cooking',
            'cooking_started_at' => now(),
        ]);

        // Ready item
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $barDish->id,
            'status' => 'ready',
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/bar/orders?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonPath('counts.new', 1)
            ->assertJsonPath('counts.in_progress', 1)
            ->assertJsonPath('counts.ready', 1);
    }

    // =====================================================
    // BAR ITEM STATUS UPDATE TESTS
    // =====================================================

    public function test_can_update_bar_item_to_start_cooking(): void
    {
        $barStation = KitchenStation::factory()->bar()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $barStation->id,
        ]);

        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'cooking',
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'dish_id' => $dish->id,
            'status' => 'cooking',
            'cooking_started_at' => null,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/bar/item-status', [
                'item_id' => $item->id,
                'status' => 'cooking',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Статус обновлён',
            ]);

        $item->refresh();
        $this->assertNotNull($item->cooking_started_at);
    }

    public function test_can_update_bar_item_to_ready(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'cooking',
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'status' => 'cooking',
            'cooking_started_at' => now(),
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/bar/item-status', [
                'item_id' => $item->id,
                'status' => 'ready',
            ]);

        $response->assertOk();

        $item->refresh();
        $this->assertEquals('ready', $item->status);
        $this->assertNotNull($item->cooking_finished_at);
    }

    public function test_bar_item_status_updates_order_status_when_all_items_ready(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'cooking',
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'status' => 'cooking',
            'cooking_started_at' => now(),
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/bar/item-status', [
                'item_id' => $item->id,
                'status' => 'ready',
            ]);

        $response->assertOk();

        $order->refresh();
        $this->assertEquals('ready', $order->status);
    }

    public function test_bar_item_status_does_not_update_order_when_items_still_cooking(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'cooking',
        ]);

        $item1 = OrderItem::factory()->create([
            'order_id' => $order->id,
            'status' => 'cooking',
            'cooking_started_at' => now(),
        ]);

        $item2 = OrderItem::factory()->create([
            'order_id' => $order->id,
            'status' => 'cooking',
            'cooking_started_at' => now(),
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/bar/item-status', [
                'item_id' => $item1->id,
                'status' => 'ready',
            ]);

        $response->assertOk();

        $order->refresh();
        $this->assertEquals('cooking', $order->status);
    }

    public function test_bar_item_status_validates_item_id_required(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/bar/item-status', [
                'status' => 'ready',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['item_id']);
    }

    public function test_bar_item_status_validates_item_id_exists(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/bar/item-status', [
                'item_id' => 99999,
                'status' => 'ready',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['item_id']);
    }

    public function test_bar_item_status_validates_status_required(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/bar/item-status', [
                'item_id' => $item->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_bar_item_status_validates_status_values(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/bar/item-status', [
                'item_id' => $item->id,
                'status' => 'invalid_status',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // =====================================================
    // STATION CONFIGURATION TESTS
    // =====================================================

    public function test_station_can_be_configured_with_custom_icon(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Grill Station',
                'icon' => 'drumstick',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.icon', 'drumstick');
    }

    public function test_station_can_be_configured_with_custom_color(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Cold Station',
                'color' => '#00BFFF',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.color', '#00BFFF');
    }

    public function test_station_can_be_configured_with_description(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Main Kitchen',
                'description' => 'Main cooking station for hot dishes',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.description', 'Main cooking station for hot dishes');
    }

    public function test_station_configuration_persists_after_update(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'icon' => 'fire',
            'color' => '#FF0000',
            'notification_sound' => 'bell',
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/kitchen-stations/{$station->id}", [
                'name' => 'Updated Station',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.icon', 'fire')
            ->assertJsonPath('data.color', '#FF0000')
            ->assertJsonPath('data.notification_sound', 'bell');
    }

    // =====================================================
    // CATEGORY ASSIGNMENT (DISH-STATION RELATIONSHIP) TESTS
    // =====================================================

    public function test_dishes_can_be_assigned_to_station(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $station->id,
        ]);

        $this->assertDatabaseHas('dishes', [
            'id' => $dish->id,
            'kitchen_station_id' => $station->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations/{$station->id}");

        $response->assertOk()
            ->assertJsonPath('data.dishes_count', 1);
    }

    public function test_station_dishes_count_updates_correctly(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Create 3 dishes for this station
        Dish::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $station->id,
        ]);

        // Create 2 dishes for another station
        $otherStation = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        Dish::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $otherStation->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations/{$station->id}");

        $response->assertOk()
            ->assertJsonPath('data.dishes_count', 3);
    }

    public function test_multiple_stations_can_have_dishes_from_same_category(): void
    {
        $station1 = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Hot Station',
        ]);

        $station2 = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Cold Station',
        ]);

        $category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Main Dishes',
        ]);

        Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $station1->id,
            'name' => 'Hot Dish',
        ]);

        Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $category->id,
            'kitchen_station_id' => $station2->id,
            'name' => 'Cold Dish',
        ]);

        $this->authenticate();
        $response1 = $this->getJson("/api/kitchen-stations/{$station1->id}");

        $response1->assertOk()
            ->assertJsonPath('data.dishes_count', 1);

        $response2 = $this->getJson("/api/kitchen-stations/{$station2->id}");

        $response2->assertOk()
            ->assertJsonPath('data.dishes_count', 1);
    }

    // =====================================================
    // EDGE CASES AND ERROR HANDLING TESTS
    // =====================================================

    public function test_empty_stations_array_for_reorder_fails_validation(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations/reorder', [
                'stations' => [],
            ]);

        // Empty array should fail validation - at least one station required
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stations']);
    }

    public function test_index_with_dishes_count_zero(): void
    {
        $station = KitchenStation::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/kitchen-stations?restaurant_id={$this->restaurant->id}");

        $response->assertOk();

        $stationData = collect($response->json('data'))->firstWhere('id', $station->id);
        $this->assertEquals(0, $stationData['dishes_count']);
    }

    public function test_can_create_station_with_zero_sort_order(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'First Station',
                'sort_order' => 0,
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.sort_order', 0);
    }

    public function test_can_create_station_with_negative_sort_order(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'Priority Station',
                'sort_order' => -10,
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.sort_order', -10);
    }

    public function test_station_with_null_icon_is_allowed(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'No Icon Station',
                'icon' => null,
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.icon', null);
    }

    public function test_station_with_null_description_is_allowed(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/kitchen-stations', [
                'name' => 'No Description Station',
                'description' => null,
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.description', null);
    }
}
