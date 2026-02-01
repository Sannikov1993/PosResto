<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MenuControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected Category $category;
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
        $this->category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    protected function authenticate(): void
    {
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    // ===== INDEX TESTS =====

    public function test_can_get_full_menu(): void
    {
        $this->authenticate();

        Dish::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->getJson("/api/menu?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'dishes']
                ]
            ])
            ->assertJson(['success' => true]);
    }

    // ===== CATEGORIES TESTS =====

    public function test_can_list_categories(): void
    {
        $this->authenticate();

        Category::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->getJson("/api/menu/categories?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertGreaterThanOrEqual(4, count($response->json('data')));
    }

    public function test_can_create_category(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/menu/categories', [
            'name' => 'Новая категория',
            'description' => 'Описание категории',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Новая категория',
        ]);
    }

    public function test_create_category_validates_required_fields(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/menu/categories', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_category(): void
    {
        $this->authenticate();

        $response = $this->putJson("/api/menu/categories/{$this->category->id}", [
            'name' => 'Обновлённая категория',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('categories', [
            'id' => $this->category->id,
            'name' => 'Обновлённая категория',
        ]);
    }

    public function test_can_delete_category(): void
    {
        $this->authenticate();

        $categoryToDelete = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->deleteJson("/api/menu/categories/{$categoryToDelete->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('categories', [
            'id' => $categoryToDelete->id,
        ]);
    }

    // ===== DISHES TESTS =====

    public function test_can_list_dishes(): void
    {
        $this->authenticate();

        Dish::factory()->count(5)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->getJson("/api/menu/dishes?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_can_filter_dishes_by_category(): void
    {
        $this->authenticate();

        $otherCategory = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        Dish::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        Dish::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $otherCategory->id,
        ]);

        $response = $this->getJson("/api/menu/dishes?restaurant_id={$this->restaurant->id}&category_id={$this->category->id}");

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_create_dish(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/menu/dishes', [
            'name' => 'Новое блюдо',
            'description' => 'Описание блюда',
            'price' => 500,
            'category_id' => $this->category->id,
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('dishes', [
            'name' => 'Новое блюдо',
            'price' => 500,
        ]);
    }

    public function test_create_dish_validates_required_fields(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/menu/dishes', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_show_dish(): void
    {
        $this->authenticate();

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->getJson("/api/menu/dishes/{$dish->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['id' => $dish->id]
            ]);
    }

    public function test_can_update_dish(): void
    {
        $this->authenticate();

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'price' => 300,
        ]);

        $response = $this->putJson("/api/menu/dishes/{$dish->id}", [
            'name' => 'Обновлённое блюдо',
            'price' => 600,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('dishes', [
            'id' => $dish->id,
            'name' => 'Обновлённое блюдо',
            'price' => 600,
        ]);
    }

    public function test_can_delete_dish(): void
    {
        $this->authenticate();

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->deleteJson("/api/menu/dishes/{$dish->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('dishes', [
            'id' => $dish->id,
        ]);
    }

    public function test_can_toggle_dish_availability(): void
    {
        $this->authenticate();

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'is_available' => true,
        ]);

        $response = $this->patchJson("/api/menu/dishes/{$dish->id}/toggle");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('dishes', [
            'id' => $dish->id,
            'is_available' => false,
        ]);
    }
}
