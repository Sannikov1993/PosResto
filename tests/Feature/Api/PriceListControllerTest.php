<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Dish;
use App\Models\Category;
use App\Models\Restaurant;
use App\Models\PriceList;
use App\Models\PriceListItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PriceListControllerTest extends TestCase
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
        ]);
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $this->dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'price' => 500,
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // =====================================================
    // INDEX TESTS
    // =====================================================

    public function test_can_get_price_lists(): void
    {
        PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Основной прайс',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Выходной прайс',
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/price-lists?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'restaurant_id',
                        'name',
                        'description',
                        'is_default',
                        'is_active',
                        'sort_order',
                        'items_count',
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_price_lists_are_ordered_by_sort_order(): void
    {
        PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Третий',
            'sort_order' => 3,
        ]);

        PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Первый',
            'sort_order' => 1,
        ]);

        PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Второй',
            'sort_order' => 2,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/price-lists?restaurant_id={$this->restaurant->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('Первый', $data[0]['name']);
        $this->assertEquals('Второй', $data[1]['name']);
        $this->assertEquals('Третий', $data[2]['name']);
    }

    public function test_price_lists_include_items_count(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        // Add 3 items
        for ($i = 0; $i < 3; $i++) {
            $dish = Dish::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'category_id' => $this->category->id,
            ]);
            PriceListItem::create([
                'price_list_id' => $priceList->id,
                'dish_id' => $dish->id,
                'price' => 100 + $i * 50,
            ]);
        }

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/price-lists?restaurant_id={$this->restaurant->id}");

        $response->assertOk();
        $this->assertEquals(3, $response->json('data.0.items_count'));
    }

    public function test_price_lists_empty_when_no_lists(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/price-lists?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    // =====================================================
    // STORE TESTS
    // =====================================================

    public function test_can_create_price_list(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/price-lists', [
                'name' => 'Новый прайс-лист',
                'description' => 'Описание прайс-листа',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 5,
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'restaurant_id',
                    'name',
                    'description',
                    'is_default',
                    'is_active',
                    'sort_order',
                    'items_count',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Новый прайс-лист',
                    'description' => 'Описание прайс-листа',
                    'is_default' => false,
                    'is_active' => true,
                    'sort_order' => 5,
                ],
            ]);

        $this->assertDatabaseHas('price_lists', [
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Новый прайс-лист',
        ]);
    }

    public function test_can_create_price_list_with_minimal_data(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/price-lists', [
                'name' => 'Минимальный прайс',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Минимальный прайс',
                    'is_default' => false,
                    'is_active' => true,
                    'sort_order' => 0,
                ],
            ]);
    }

    public function test_create_default_price_list_removes_default_from_others(): void
    {
        $existingDefault = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Старый дефолт',
            'is_default' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/price-lists', [
                'name' => 'Новый дефолт',
                'is_default' => true,
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Новый дефолт',
                    'is_default' => true,
                ],
            ]);

        // Check old default is no longer default
        $this->assertDatabaseHas('price_lists', [
            'id' => $existingDefault->id,
            'is_default' => false,
        ]);
    }

    public function test_create_price_list_validates_name_required(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/price-lists', [
                'description' => 'Без имени',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_price_list_validates_name_max_length(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/price-lists', [
                'name' => str_repeat('a', 256),
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // =====================================================
    // SHOW TESTS
    // =====================================================

    public function test_can_show_price_list(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
            'description' => 'Описание',
            'is_default' => true,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/price-lists/{$priceList->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'restaurant_id',
                    'name',
                    'description',
                    'is_default',
                    'is_active',
                    'sort_order',
                    'items',
                    'items_count',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $priceList->id,
                    'name' => 'Тестовый прайс',
                ],
            ]);
    }

    public function test_show_price_list_includes_items_with_dishes(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Прайс с позициями',
        ]);

        PriceListItem::create([
            'price_list_id' => $priceList->id,
            'dish_id' => $this->dish->id,
            'price' => 750,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/price-lists/{$priceList->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'price_list_id',
                            'dish_id',
                            'price',
                            'dish' => [
                                'id',
                                'name',
                            ],
                        ]
                    ],
                ]
            ]);

        $this->assertEquals(750, $response->json('data.items.0.price'));
        $this->assertEquals($this->dish->id, $response->json('data.items.0.dish.id'));
    }

    public function test_show_price_list_returns_404_for_nonexistent(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/price-lists/999999');

        $response->assertNotFound();
    }

    // =====================================================
    // UPDATE TESTS
    // =====================================================

    public function test_can_update_price_list(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Старое название',
            'description' => 'Старое описание',
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/price-lists/{$priceList->id}", [
                'name' => 'Новое название',
                'description' => 'Новое описание',
                'sort_order' => 10,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Новое название',
                    'description' => 'Новое описание',
                    'sort_order' => 10,
                ],
            ]);

        $this->assertDatabaseHas('price_lists', [
            'id' => $priceList->id,
            'name' => 'Новое название',
            'description' => 'Новое описание',
        ]);
    }

    public function test_update_price_list_partial_update(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Исходное название',
            'description' => 'Исходное описание',
            'sort_order' => 5,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/price-lists/{$priceList->id}", [
                'sort_order' => 15,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('price_lists', [
            'id' => $priceList->id,
            'name' => 'Исходное название',
            'description' => 'Исходное описание',
            'sort_order' => 15,
        ]);
    }

    public function test_update_to_default_removes_default_from_others(): void
    {
        $existingDefault = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Текущий дефолт',
            'is_default' => true,
        ]);

        $newDefault = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Будет дефолт',
            'is_default' => false,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/price-lists/{$newDefault->id}", [
                'is_default' => true,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_default' => true,
                ],
            ]);

        $this->assertDatabaseHas('price_lists', [
            'id' => $existingDefault->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('price_lists', [
            'id' => $newDefault->id,
            'is_default' => true,
        ]);
    }

    // =====================================================
    // DESTROY TESTS
    // =====================================================

    public function test_can_delete_price_list(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Для удаления',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/price-lists/{$priceList->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Прайс-лист удалён',
            ]);

        // Soft delete - still in DB but deleted_at is set
        $this->assertSoftDeleted('price_lists', [
            'id' => $priceList->id,
        ]);
    }

    public function test_deleted_price_list_not_shown_in_index(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Удалённый',
        ]);

        $priceList->delete();

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/price-lists?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    // =====================================================
    // TOGGLE TESTS
    // =====================================================

    public function test_can_toggle_price_list_from_active_to_inactive(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Активный прайс',
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList->id}/toggle");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Прайс-лист деактивирован',
                'data' => [
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('price_lists', [
            'id' => $priceList->id,
            'is_active' => false,
        ]);
    }

    public function test_can_toggle_price_list_from_inactive_to_active(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Неактивный прайс',
            'is_active' => false,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList->id}/toggle");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Прайс-лист активирован',
                'data' => [
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('price_lists', [
            'id' => $priceList->id,
            'is_active' => true,
        ]);
    }

    // =====================================================
    // SET DEFAULT TESTS
    // =====================================================

    public function test_can_set_price_list_as_default(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Станет дефолтом',
            'is_default' => false,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList->id}/default");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Прайс-лист установлен по умолчанию',
                'data' => [
                    'is_default' => true,
                ],
            ]);

        $this->assertDatabaseHas('price_lists', [
            'id' => $priceList->id,
            'is_default' => true,
        ]);
    }

    public function test_set_default_removes_default_from_others(): void
    {
        $oldDefault = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Старый дефолт',
            'is_default' => true,
        ]);

        $newDefault = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Новый дефолт',
            'is_default' => false,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$newDefault->id}/default");

        $response->assertOk();

        $this->assertDatabaseHas('price_lists', [
            'id' => $oldDefault->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('price_lists', [
            'id' => $newDefault->id,
            'is_default' => true,
        ]);
    }

    public function test_only_one_default_price_list_per_restaurant(): void
    {
        $priceList1 = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Прайс 1',
            'is_default' => true,
        ]);

        $priceList2 = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Прайс 2',
            'is_default' => false,
        ]);

        $priceList3 = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Прайс 3',
            'is_default' => false,
        ]);

        // Set priceList2 as default
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList2->id}/default");

        // Set priceList3 as default
        $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList3->id}/default");

        // Only priceList3 should be default
        $defaultCount = PriceList::where('restaurant_id', $this->restaurant->id)
            ->where('is_default', true)
            ->count();

        $this->assertEquals(1, $defaultCount);
        $this->assertDatabaseHas('price_lists', [
            'id' => $priceList3->id,
            'is_default' => true,
        ]);
    }

    // =====================================================
    // ITEMS TESTS
    // =====================================================

    public function test_can_get_price_list_items(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Прайс с позициями',
        ]);

        $dish2 = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        PriceListItem::create([
            'price_list_id' => $priceList->id,
            'dish_id' => $this->dish->id,
            'price' => 600,
        ]);

        PriceListItem::create([
            'price_list_id' => $priceList->id,
            'dish_id' => $dish2->id,
            'price' => 800,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/price-lists/{$priceList->id}/items");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'price_list_id',
                        'dish_id',
                        'price',
                        'dish' => [
                            'id',
                            'name',
                        ],
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_get_items_returns_empty_array_when_no_items(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Пустой прайс',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/price-lists/{$priceList->id}/items");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    // =====================================================
    // SAVE ITEMS TESTS (PRICE OVERRIDES)
    // =====================================================

    public function test_can_save_price_list_items(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Прайс для сохранения',
        ]);

        $dish2 = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList->id}/items", [
                'items' => [
                    ['dish_id' => $this->dish->id, 'price' => 750],
                    ['dish_id' => $dish2->id, 'price' => 900],
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Позиции сохранены',
            ]);

        $this->assertDatabaseHas('price_list_items', [
            'price_list_id' => $priceList->id,
            'dish_id' => $this->dish->id,
            'price' => 750,
        ]);

        $this->assertDatabaseHas('price_list_items', [
            'price_list_id' => $priceList->id,
            'dish_id' => $dish2->id,
            'price' => 900,
        ]);
    }

    public function test_save_items_updates_existing_prices(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Прайс для обновления',
        ]);

        // Create initial item
        PriceListItem::create([
            'price_list_id' => $priceList->id,
            'dish_id' => $this->dish->id,
            'price' => 500,
        ]);

        // Update price
        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList->id}/items", [
                'items' => [
                    ['dish_id' => $this->dish->id, 'price' => 650],
                ],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('price_list_items', [
            'price_list_id' => $priceList->id,
            'dish_id' => $this->dish->id,
            'price' => 650,
        ]);

        // Only one record should exist
        $this->assertEquals(1, PriceListItem::where('price_list_id', $priceList->id)->count());
    }

    public function test_save_items_validates_items_required(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList->id}/items", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_save_items_validates_dish_id_required(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList->id}/items", [
                'items' => [
                    ['price' => 500],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.dish_id']);
    }

    public function test_save_items_validates_dish_exists(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList->id}/items", [
                'items' => [
                    ['dish_id' => 999999, 'price' => 500],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.dish_id']);
    }

    public function test_save_items_validates_price_required(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList->id}/items", [
                'items' => [
                    ['dish_id' => $this->dish->id],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.price']);
    }

    public function test_save_items_validates_price_is_numeric(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList->id}/items", [
                'items' => [
                    ['dish_id' => $this->dish->id, 'price' => 'не число'],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.price']);
    }

    public function test_save_items_validates_price_minimum(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList->id}/items", [
                'items' => [
                    ['dish_id' => $this->dish->id, 'price' => -10],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.price']);
    }

    public function test_save_items_allows_zero_price(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList->id}/items", [
                'items' => [
                    ['dish_id' => $this->dish->id, 'price' => 0],
                ],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('price_list_items', [
            'price_list_id' => $priceList->id,
            'dish_id' => $this->dish->id,
            'price' => 0,
        ]);
    }

    public function test_save_items_allows_decimal_price(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList->id}/items", [
                'items' => [
                    ['dish_id' => $this->dish->id, 'price' => 199.99],
                ],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('price_list_items', [
            'price_list_id' => $priceList->id,
            'dish_id' => $this->dish->id,
            'price' => 199.99,
        ]);
    }

    // =====================================================
    // REMOVE ITEM TESTS
    // =====================================================

    public function test_can_remove_item_from_price_list(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Прайс для удаления',
        ]);

        PriceListItem::create([
            'price_list_id' => $priceList->id,
            'dish_id' => $this->dish->id,
            'price' => 500,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/price-lists/{$priceList->id}/items/{$this->dish->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Позиция удалена из прайс-листа',
            ]);

        $this->assertDatabaseMissing('price_list_items', [
            'price_list_id' => $priceList->id,
            'dish_id' => $this->dish->id,
        ]);
    }

    public function test_remove_item_returns_success_even_if_not_exists(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Прайс без позиций',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/price-lists/{$priceList->id}/items/{$this->dish->id}");

        // The controller doesn't check if item exists, just deletes where clause matches
        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_remove_item_does_not_affect_other_items(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Прайс с позициями',
        ]);

        $dish2 = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        PriceListItem::create([
            'price_list_id' => $priceList->id,
            'dish_id' => $this->dish->id,
            'price' => 500,
        ]);

        PriceListItem::create([
            'price_list_id' => $priceList->id,
            'dish_id' => $dish2->id,
            'price' => 600,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/price-lists/{$priceList->id}/items/{$this->dish->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('price_list_items', [
            'price_list_id' => $priceList->id,
            'dish_id' => $this->dish->id,
        ]);

        $this->assertDatabaseHas('price_list_items', [
            'price_list_id' => $priceList->id,
            'dish_id' => $dish2->id,
            'price' => 600,
        ]);
    }

    // =====================================================
    // RESTAURANT ISOLATION TESTS
    // =====================================================

    public function test_price_lists_are_scoped_to_restaurant(): void
    {
        $otherRestaurant = Restaurant::factory()->create();

        PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Мой прайс',
        ]);

        PriceList::create([
            'restaurant_id' => $otherRestaurant->id,
            'name' => 'Чужой прайс',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/price-lists?restaurant_id={$this->restaurant->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Мой прайс', $response->json('data.0.name'));
    }

    public function test_setting_default_does_not_affect_other_restaurants(): void
    {
        $otherRestaurant = Restaurant::factory()->create();

        $myDefault = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Мой дефолт',
            'is_default' => true,
        ]);

        $otherDefault = PriceList::create([
            'restaurant_id' => $otherRestaurant->id,
            'name' => 'Чужой дефолт',
            'is_default' => true,
        ]);

        $myNew = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Новый дефолт',
            'is_default' => false,
        ]);

        $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$myNew->id}/default");

        // Other restaurant's default should not be affected
        $this->assertDatabaseHas('price_lists', [
            'id' => $otherDefault->id,
            'is_default' => true,
        ]);

        // My old default should be removed
        $this->assertDatabaseHas('price_lists', [
            'id' => $myDefault->id,
            'is_default' => false,
        ]);

        // My new default should be set
        $this->assertDatabaseHas('price_lists', [
            'id' => $myNew->id,
            'is_default' => true,
        ]);
    }

    // =====================================================
    // AUTHENTICATION TESTS
    // =====================================================

    public function test_unauthenticated_user_cannot_access_price_lists(): void
    {
        $response = $this->getJson("/api/price-lists?restaurant_id={$this->restaurant->id}");

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_create_price_list(): void
    {
        $response = $this->postJson('/api/price-lists', [
            'name' => 'Тестовый прайс',
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_update_price_list(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->putJson("/api/price-lists/{$priceList->id}", [
            'name' => 'Обновлённое название',
        ]);

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_delete_price_list(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->deleteJson("/api/price-lists/{$priceList->id}");

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_toggle_price_list(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->postJson("/api/price-lists/{$priceList->id}/toggle");

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_set_default_price_list(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->postJson("/api/price-lists/{$priceList->id}/default");

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_get_price_list_items(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->getJson("/api/price-lists/{$priceList->id}/items");

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_save_price_list_items(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->postJson("/api/price-lists/{$priceList->id}/items", [
            'items' => [
                ['dish_id' => $this->dish->id, 'price' => 500],
            ],
        ]);

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_remove_price_list_item(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $response = $this->deleteJson("/api/price-lists/{$priceList->id}/items/{$this->dish->id}");

        $response->assertUnauthorized();
    }

    // =====================================================
    // EDGE CASES
    // =====================================================

    public function test_can_create_multiple_price_lists_with_same_name(): void
    {
        $response1 = $this->withHeaders($this->authHeaders())
            ->postJson('/api/price-lists', [
                'name' => 'Одинаковое название',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response1->assertStatus(201);

        $response2 = $this->withHeaders($this->authHeaders())
            ->postJson('/api/price-lists', [
                'name' => 'Одинаковое название',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response2->assertStatus(201);

        $count = PriceList::where('restaurant_id', $this->restaurant->id)
            ->where('name', 'Одинаковое название')
            ->count();

        $this->assertEquals(2, $count);
    }

    public function test_price_list_items_cascade_delete_with_dish(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Прайс для каскада',
        ]);

        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
        ]);

        PriceListItem::create([
            'price_list_id' => $priceList->id,
            'dish_id' => $dish->id,
            'price' => 500,
        ]);

        // Force delete the dish
        $dish->forceDelete();

        // Item should be deleted due to cascade
        $this->assertDatabaseMissing('price_list_items', [
            'price_list_id' => $priceList->id,
            'dish_id' => $dish->id,
        ]);
    }

    public function test_save_items_returns_updated_items_list(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Прайс для проверки',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/price-lists/{$priceList->id}/items", [
                'items' => [
                    ['dish_id' => $this->dish->id, 'price' => 750],
                ],
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'price_list_id',
                        'dish_id',
                        'price',
                        'dish',
                    ]
                ]
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(750, $response->json('data.0.price'));
    }

    // =====================================================
    // MODEL TESTS
    // =====================================================

    public function test_price_list_active_scope(): void
    {
        PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Активный',
            'is_active' => true,
        ]);

        PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Неактивный',
            'is_active' => false,
        ]);

        $activeLists = PriceList::where('restaurant_id', $this->restaurant->id)
            ->active()
            ->get();

        $this->assertCount(1, $activeLists);
        $this->assertEquals('Активный', $activeLists->first()->name);
    }

    public function test_price_list_ordered_scope(): void
    {
        PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Третий',
            'sort_order' => 30,
        ]);

        PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Первый',
            'sort_order' => 10,
        ]);

        PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Второй',
            'sort_order' => 20,
        ]);

        $orderedLists = PriceList::where('restaurant_id', $this->restaurant->id)
            ->ordered()
            ->get();

        $this->assertEquals('Первый', $orderedLists[0]->name);
        $this->assertEquals('Второй', $orderedLists[1]->name);
        $this->assertEquals('Третий', $orderedLists[2]->name);
    }

    public function test_price_list_has_dishes_relationship(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Прайс с блюдами',
        ]);

        PriceListItem::create([
            'price_list_id' => $priceList->id,
            'dish_id' => $this->dish->id,
            'price' => 600,
        ]);

        $priceList->refresh();

        $dishes = $priceList->dishes;

        $this->assertCount(1, $dishes);
        $this->assertEquals($this->dish->id, $dishes->first()->id);
        $this->assertEquals(600, $dishes->first()->pivot->price);
    }

    public function test_price_list_item_belongs_to_price_list(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $item = PriceListItem::create([
            'price_list_id' => $priceList->id,
            'dish_id' => $this->dish->id,
            'price' => 500,
        ]);

        $this->assertEquals($priceList->id, $item->priceList->id);
    }

    public function test_price_list_item_belongs_to_dish(): void
    {
        $priceList = PriceList::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый прайс',
        ]);

        $item = PriceListItem::create([
            'price_list_id' => $priceList->id,
            'dish_id' => $this->dish->id,
            'price' => 500,
        ]);

        $this->assertEquals($this->dish->id, $item->dish->id);
    }
}
