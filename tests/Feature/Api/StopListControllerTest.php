<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Dish;
use App\Models\Category;
use App\Models\Restaurant;
use App\Models\StopList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class StopListControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected Category $category;
    protected Dish $dish;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);
        $this->category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $this->dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'is_available' => true,
        ]);
    }

    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    // ===== INDEX TESTS (Get Stop List) =====

    public function test_can_get_stop_list(): void
    {
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => 'Закончились ингредиенты',
            'stopped_at' => now(),
            'stopped_by' => $this->user->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/stop-list?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'dish_id',
                        'dish' => ['id', 'name', 'price'],
                        'reason',
                        'stopped_at',
                        'resume_at',
                        'stopped_by',
                    ]
                ],
                'count',
            ])
            ->assertJson([
                'success' => true,
                'count' => 1,
            ]);
    }

    public function test_get_stop_list_returns_empty_when_no_items(): void
    {
        $this->authenticate();
        $response = $this->getJson("/api/stop-list?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
                'count' => 0,
            ]);
    }

    public function test_get_stop_list_excludes_expired_items(): void
    {
        // Active item (no resume_at)
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => 'Active',
            'stopped_at' => now(),
        ]);

        // Expired item (resume_at in the past)
        $expiredDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $expiredDish->id,
            'reason' => 'Expired',
            'stopped_at' => now()->subHours(2),
            'resume_at' => now()->subHour(),
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/stop-list?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 1,
            ]);
    }

    public function test_get_stop_list_includes_scheduled_items_not_yet_resumed(): void
    {
        // Scheduled item with future resume_at
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => 'Scheduled',
            'stopped_at' => now(),
            'resume_at' => now()->addHours(2),
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/stop-list?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 1,
            ]);
    }

    public function test_stop_list_is_scoped_to_restaurant(): void
    {
        $otherRestaurant = Restaurant::factory()->create();
        $otherDish = Dish::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'category_id' => Category::factory()->create([
                'restaurant_id' => $otherRestaurant->id,
            ])->id,
        ]);

        // Stop list entry for other restaurant
        StopList::create([
            'restaurant_id' => $otherRestaurant->id,
            'dish_id' => $otherDish->id,
            'reason' => 'Other restaurant',
            'stopped_at' => now(),
        ]);

        // Stop list entry for current restaurant
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => 'Current restaurant',
            'stopped_at' => now(),
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/stop-list?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'count' => 1,
            ]);

        // Verify only our dish is returned
        $data = $response->json('data');
        $this->assertEquals($this->dish->id, $data[0]['dish_id']);
    }

    // ===== STORE TESTS (Add Dish to Stop List) =====

    public function test_can_add_dish_to_stop_list(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/stop-list', [
            'dish_id' => $this->dish->id,
            'reason' => 'Закончились ингредиенты',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'dish_id',
                    'dish' => ['id', 'name', 'price'],
                    'reason',
                    'stopped_at',
                    'resume_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'dish_id' => $this->dish->id,
                    'reason' => 'Закончились ингредиенты',
                ],
            ]);

        $this->assertDatabaseHas('stop_list', [
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => 'Закончились ингредиенты',
        ]);
    }

    public function test_can_add_dish_to_stop_list_with_scheduled_resume(): void
    {
        $resumeAt = now()->addHours(2)->format('Y-m-d H:i:s');

        $this->authenticate();
        $response = $this->postJson('/api/stop-list', [
            'dish_id' => $this->dish->id,
            'reason' => 'Временно недоступно',
            'resume_at' => $resumeAt,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('stop_list', [
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => 'Временно недоступно',
        ]);
    }

    public function test_can_add_dish_to_stop_list_without_reason(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/stop-list', [
            'dish_id' => $this->dish->id,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('stop_list', [
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => null,
        ]);
    }

    public function test_cannot_add_dish_already_in_stop_list(): void
    {
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => 'Already stopped',
            'stopped_at' => now(),
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/stop-list', [
            'dish_id' => $this->dish->id,
            'reason' => 'Try to add again',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Блюдо уже в стоп-листе',
            ]);
    }

    public function test_can_re_add_dish_after_stop_list_expired(): void
    {
        // Create expired entry
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => 'Old reason',
            'stopped_at' => now()->subHours(3),
            'resume_at' => now()->subHour(),
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/stop-list', [
            'dish_id' => $this->dish->id,
            'reason' => 'New reason',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'reason' => 'New reason',
                ],
            ]);
    }

    public function test_cannot_add_dish_from_other_restaurant_to_stop_list(): void
    {
        $otherRestaurant = Restaurant::factory()->create();
        $otherDish = Dish::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
            'category_id' => Category::factory()->create([
                'restaurant_id' => $otherRestaurant->id,
            ])->id,
        ]);

        $this->authenticate();
        $response = $this->postJson('/api/stop-list', [
            'dish_id' => $otherDish->id,
            'reason' => 'Not my dish',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Блюдо не принадлежит данному ресторану',
            ]);
    }

    public function test_add_to_stop_list_validates_required_dish_id(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/stop-list', [
            'reason' => 'No dish specified',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['dish_id']);
    }

    public function test_add_to_stop_list_validates_dish_exists(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/stop-list', [
            'dish_id' => 999999,
            'reason' => 'Nonexistent dish',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['dish_id']);
    }

    public function test_add_to_stop_list_validates_reason_max_length(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/stop-list', [
            'dish_id' => $this->dish->id,
            'reason' => str_repeat('a', 256),
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    // ===== UPDATE TESTS =====

    public function test_can_update_stop_list_entry(): void
    {
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => 'Original reason',
            'stopped_at' => now(),
        ]);

        $this->authenticate();
        $response = $this->putJson("/api/stop-list/{$this->dish->id}", [
            'reason' => 'Updated reason',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Запись обновлена',
                'data' => [
                    'reason' => 'Updated reason',
                ],
            ]);

        $this->assertDatabaseHas('stop_list', [
            'dish_id' => $this->dish->id,
            'reason' => 'Updated reason',
        ]);
    }

    public function test_can_update_stop_list_resume_time(): void
    {
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => 'Test',
            'stopped_at' => now(),
            'resume_at' => null,
        ]);

        $newResumeAt = now()->addHours(4)->format('Y-m-d H:i:s');

        $this->authenticate();
        $response = $this->putJson("/api/stop-list/{$this->dish->id}", [
            'resume_at' => $newResumeAt,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_cannot_update_nonexistent_stop_list_entry(): void
    {
        $this->authenticate();
        $response = $this->putJson("/api/stop-list/{$this->dish->id}", [
            'reason' => 'Update nonexistent',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Блюдо не найдено в стоп-листе',
            ]);
    }

    // ===== DESTROY TESTS (Remove Dish from Stop List) =====

    public function test_can_remove_dish_from_stop_list(): void
    {
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => 'To be removed',
            'stopped_at' => now(),
        ]);

        $this->authenticate();
        $response = $this->deleteJson("/api/stop-list/{$this->dish->id}?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('stop_list', [
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
        ]);
    }

    public function test_cannot_remove_dish_not_in_stop_list(): void
    {
        $this->authenticate();
        $response = $this->deleteJson("/api/stop-list/{$this->dish->id}?restaurant_id={$this->restaurant->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Блюдо не найдено в стоп-листе',
            ]);
    }

    public function test_cannot_remove_expired_stop_list_entry(): void
    {
        // Create expired entry
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => 'Expired',
            'stopped_at' => now()->subHours(2),
            'resume_at' => now()->subHour(),
        ]);

        $this->authenticate();
        $response = $this->deleteJson("/api/stop-list/{$this->dish->id}?restaurant_id={$this->restaurant->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Блюдо не найдено в стоп-листе',
            ]);
    }

    // ===== DISH IDS TESTS (Quick Check Endpoint) =====

    public function test_can_get_stopped_dish_ids(): void
    {
        $dishes = Dish::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        foreach ($dishes as $dish) {
            StopList::create([
                'restaurant_id' => $this->restaurant->id,
                'dish_id' => $dish->id,
                'stopped_at' => now(),
            ]);
        }

        $this->authenticate();
        $response = $this->getJson("/api/stop-list/dish-ids?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_stopped_dish_ids_excludes_expired(): void
    {
        // Active stop
        $activeDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $activeDish->id,
            'stopped_at' => now(),
        ]);

        // Expired stop
        $expiredDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $expiredDish->id,
            'stopped_at' => now()->subHours(2),
            'resume_at' => now()->subHour(),
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/stop-list/dish-ids?restaurant_id={$this->restaurant->id}");

        $response->assertOk();
        $ids = $response->json('data');
        $this->assertCount(1, $ids);
        $this->assertContains($activeDish->id, $ids);
        $this->assertNotContains($expiredDish->id, $ids);
    }

    // ===== SEARCH DISHES TESTS =====

    public function test_can_search_dishes_for_stop_list(): void
    {
        Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Борщ украинский',
            'is_available' => true,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/stop-list/search-dishes?restaurant_id={$this->restaurant->id}&q=Борщ");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'price'],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_search_dishes_excludes_already_stopped(): void
    {
        $stoppedDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Стейк из говядины',
            'is_available' => true,
        ]);

        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $stoppedDish->id,
            'stopped_at' => now(),
        ]);

        $availableDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Стейк из свинины',
            'is_available' => true,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/stop-list/search-dishes?restaurant_id={$this->restaurant->id}&q=Стейк");

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($availableDish->id, $ids);
        $this->assertNotContains($stoppedDish->id, $ids);
    }

    public function test_search_dishes_excludes_unavailable(): void
    {
        $unavailableDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Особый борщ',
            'is_available' => false,
        ]);

        $availableDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Обычный борщ',
            'is_available' => true,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/stop-list/search-dishes?restaurant_id={$this->restaurant->id}&q=борщ");

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($availableDish->id, $ids);
        $this->assertNotContains($unavailableDish->id, $ids);
    }

    public function test_search_dishes_by_sku(): void
    {
        Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Пицца Маргарита',
            'sku' => 'PIZZA-001',
            'is_available' => true,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/stop-list/search-dishes?restaurant_id={$this->restaurant->id}&q=PIZZA-001");

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_search_dishes_returns_limited_results(): void
    {
        Dish::factory()->count(25)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Тестовое блюдо',
            'is_available' => true,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/stop-list/search-dishes?restaurant_id={$this->restaurant->id}&q=Тестовое");

        $response->assertOk();
        $this->assertLessThanOrEqual(20, count($response->json('data')));
    }

    // ===== STOP LIST MODEL TESTS =====

    public function test_stop_list_is_active_check(): void
    {
        // Active without resume_at
        $activeEntry = StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'stopped_at' => now(),
        ]);
        $this->assertTrue($activeEntry->isActive());

        // Clean up for next test
        $activeEntry->delete();

        // Active with future resume_at
        $scheduledEntry = StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'stopped_at' => now(),
            'resume_at' => now()->addHour(),
        ]);
        $this->assertTrue($scheduledEntry->isActive());

        // Clean up
        $scheduledEntry->delete();

        // Expired with past resume_at
        $expiredEntry = StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'stopped_at' => now()->subHours(2),
            'resume_at' => now()->subHour(),
        ]);
        $this->assertFalse($expiredEntry->isActive());
        $this->assertTrue($expiredEntry->isExpired());
    }

    public function test_stop_list_static_is_dish_stopped(): void
    {
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'stopped_at' => now(),
        ]);

        $this->assertTrue(
            StopList::isDishStopped($this->dish->id, $this->restaurant->id)
        );

        $otherDish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        $this->assertFalse(
            StopList::isDishStopped($otherDish->id, $this->restaurant->id)
        );
    }

    public function test_stop_list_get_stopped_dish_ids(): void
    {
        $dishes = Dish::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        foreach ($dishes as $dish) {
            StopList::create([
                'restaurant_id' => $this->restaurant->id,
                'dish_id' => $dish->id,
                'stopped_at' => now(),
            ]);
        }

        $stoppedIds = StopList::getStoppedDishIds($this->restaurant->id);
        $this->assertCount(3, $stoppedIds);
        foreach ($dishes as $dish) {
            $this->assertContains($dish->id, $stoppedIds);
        }
    }

    // ===== DISH MODEL INTEGRATION TESTS =====

    public function test_dish_is_in_stop_list_method(): void
    {
        $this->assertFalse($this->dish->isInStopList());

        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'stopped_at' => now(),
        ]);

        // Refresh the dish to clear any cached relationships
        $this->dish->refresh();
        $this->assertTrue($this->dish->isInStopList());
    }

    public function test_dish_add_to_stop_list_method(): void
    {
        $this->dish->addToStopList('Test reason', now()->addHour());

        $this->assertDatabaseHas('stop_list', [
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => 'Test reason',
        ]);
    }

    public function test_dish_remove_from_stop_list_method(): void
    {
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'stopped_at' => now(),
        ]);

        $this->dish->removeFromStopList();

        $this->assertDatabaseMissing('stop_list', [
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
        ]);
    }

    // ===== AUTHENTICATION TESTS =====

    public function test_unauthenticated_user_cannot_access_stop_list(): void
    {
        $response = $this->getJson("/api/stop-list?restaurant_id={$this->restaurant->id}");

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_add_to_stop_list(): void
    {
        $response = $this->postJson('/api/stop-list', [
            'dish_id' => $this->dish->id,
            'reason' => 'Test',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_remove_from_stop_list(): void
    {
        $response = $this->deleteJson("/api/stop-list/{$this->dish->id}?restaurant_id={$this->restaurant->id}");

        $response->assertUnauthorized();
    }

    // ===== STOP LIST EXPIRING SOON SCOPE TEST =====

    public function test_stop_list_expiring_soon_scope(): void
    {
        // Entry expiring in 30 minutes
        $expiringSoon = StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'stopped_at' => now(),
            'resume_at' => now()->addMinutes(30),
        ]);

        // Entry expiring in 2 hours
        $dish2 = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);
        $expiringLater = StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $dish2->id,
            'stopped_at' => now(),
            'resume_at' => now()->addHours(2),
        ]);

        // Indefinite entry
        $dish3 = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);
        $indefinite = StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $dish3->id,
            'stopped_at' => now(),
            'resume_at' => null,
        ]);

        $expiringSoonItems = StopList::where('restaurant_id', $this->restaurant->id)
            ->expiringSoon(60)
            ->get();

        $this->assertCount(1, $expiringSoonItems);
        $this->assertEquals($this->dish->id, $expiringSoonItems->first()->dish_id);
    }

    // ===== INDEFINITE SCOPE TEST =====

    public function test_stop_list_indefinite_scope(): void
    {
        // Indefinite entry
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'stopped_at' => now(),
            'resume_at' => null,
        ]);

        // Scheduled entry
        $dish2 = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $dish2->id,
            'stopped_at' => now(),
            'resume_at' => now()->addHour(),
        ]);

        $indefiniteItems = StopList::where('restaurant_id', $this->restaurant->id)
            ->indefinite()
            ->get();

        $this->assertCount(1, $indefiniteItems);
        $this->assertEquals($this->dish->id, $indefiniteItems->first()->dish_id);
    }

    // ===== STOP LIST ENTRY WITH CATEGORY INFO =====

    public function test_stop_list_includes_category_info(): void
    {
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'stopped_at' => now(),
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/stop-list?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'dish' => [
                            'category' => ['id', 'name'],
                        ],
                    ],
                ],
            ]);
    }

    // ===== STOP LIST ENTRY INCLUDES STOPPED BY USER =====

    public function test_stop_list_includes_stopped_by_user(): void
    {
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'stopped_at' => now(),
            'stopped_by' => $this->user->id,
        ]);

        $this->authenticate();
        $response = $this->getJson("/api/stop-list?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'stopped_by' => ['id', 'name'],
                    ],
                ],
            ]);

        $this->assertEquals($this->user->id, $response->json('data.0.stopped_by.id'));
    }

    // ===== BULK OPERATIONS (multiple dishes) =====

    public function test_can_add_multiple_dishes_to_stop_list_sequentially(): void
    {
        $dishes = Dish::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'is_available' => true,
        ]);

        $this->authenticate();
        foreach ($dishes as $dish) {
            $response = $this->postJson('/api/stop-list', [
                'dish_id' => $dish->id,
                'reason' => 'Bulk add test',
                'restaurant_id' => $this->restaurant->id,
            ]);

            $response->assertStatus(201);
        }

        $response = $this->getJson("/api/stop-list?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['count' => 3]);
    }

    public function test_can_remove_multiple_dishes_from_stop_list_sequentially(): void
    {
        $dishes = Dish::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        foreach ($dishes as $dish) {
            StopList::create([
                'restaurant_id' => $this->restaurant->id,
                'dish_id' => $dish->id,
                'stopped_at' => now(),
            ]);
        }

        $this->authenticate();
        foreach ($dishes as $dish) {
            $response = $this->deleteJson("/api/stop-list/{$dish->id}?restaurant_id={$this->restaurant->id}");

            $response->assertOk();
        }

        $response = $this->getJson("/api/stop-list?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['count' => 0]);
    }
}
