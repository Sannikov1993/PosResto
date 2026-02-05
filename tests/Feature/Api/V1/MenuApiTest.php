<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Dish;
use App\Models\StopList;

class MenuApiTest extends ApiTestCase
{
    protected Category $category;
    protected Dish $dish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Пицца',
            'is_active' => true,
        ]);

        $this->dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Маргарита',
            'price' => 450,
            'is_available' => true,
        ]);
    }

    /** @test */
    public function it_returns_categories_list(): void
    {
        $response = $this->apiGet('/menu/categories');

        $this->assertApiSuccess($response);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'name', 'slug'],
            ],
        ]);
    }

    /** @test */
    public function it_returns_single_category(): void
    {
        $response = $this->apiGet("/menu/categories/{$this->category->id}");

        $this->assertApiSuccess($response);
        $response->assertJsonPath('data.id', $this->category->id);
        $response->assertJsonPath('data.name', 'Пицца');
    }

    /** @test */
    public function it_returns_404_for_missing_category(): void
    {
        $response = $this->apiGet('/menu/categories/99999');

        $this->assertApiError($response, 404, 'NOT_FOUND');
    }

    /** @test */
    public function it_returns_dishes_list(): void
    {
        $response = $this->apiGet('/menu/dishes');

        $this->assertApiSuccess($response);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'name', 'price', 'flags'],
            ],
        ]);
    }

    /** @test */
    public function it_filters_dishes_by_category(): void
    {
        // Create another category with dishes
        $otherCategory = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $otherCategory->id,
        ]);

        $response = $this->apiGet("/menu/dishes?category_id={$this->category->id}");

        $this->assertApiSuccess($response);
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.category.id', $this->category->id);
    }

    /** @test */
    public function it_filters_available_dishes(): void
    {
        Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'is_available' => false,
        ]);

        $response = $this->apiGet('/menu/dishes?is_available=true');

        $this->assertApiSuccess($response);
        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function it_returns_single_dish(): void
    {
        $response = $this->apiGet("/menu/dishes/{$this->dish->id}");

        $this->assertApiSuccess($response);
        $response->assertJsonPath('data.id', $this->dish->id);
        $response->assertJsonPath('data.name', 'Маргарита');
        $response->assertJsonPath('data.price', '450.00');
    }

    /** @test */
    public function it_returns_stop_list(): void
    {
        // Add dish to stop list
        StopList::create([
            'restaurant_id' => $this->restaurant->id,
            'dish_id' => $this->dish->id,
            'reason' => 'Закончились томаты',
            'stopped_at' => now(),
        ]);

        $response = $this->apiGet('/menu/stop-list');

        $this->assertApiSuccess($response);
        $response->assertJsonFragment([
            'dish_id' => $this->dish->id,
            'dish_name' => 'Маргарита',
        ]);
    }

    /** @test */
    public function it_returns_full_menu(): void
    {
        $response = $this->apiGet('/menu/full');

        $this->assertApiSuccess($response);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'category',
                    'dishes',
                ],
            ],
            'meta',
        ]);
    }

    /** @test */
    public function it_requires_menu_read_scope(): void
    {
        $limited = $this->createClientWithScopes(['orders:read']);

        $response = $this->withHeaders($limited['headers'])
            ->getJson('/api/v1/menu/dishes');

        $this->assertApiError($response, 403, 'INSUFFICIENT_SCOPE');
    }
}
