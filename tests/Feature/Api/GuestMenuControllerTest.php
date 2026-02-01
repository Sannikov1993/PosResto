<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Table;
use App\Models\Zone;
use App\Models\Restaurant;
use App\Models\Category;
use App\Models\Dish;
use App\Models\TableQrCode;
use App\Models\WaiterCall;
use App\Models\Review;
use App\Models\GuestMenuSetting;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GuestMenuControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected Zone $zone;
    protected Table $table;
    protected TableQrCode $qrCode;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $this->zone = Zone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
        $this->table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
        ]);
        $this->qrCode = TableQrCode::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'code' => 'TESTCODE',
            'is_active' => true,
        ]);
        $this->category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);
    }

    // =====================================================
    // PUBLIC MENU DISPLAY TESTS (No Auth Required)
    // =====================================================

    public function test_can_get_menu_by_valid_qr_code(): void
    {
        Dish::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'is_available' => true,
        ]);

        $response = $this->getJson("/api/guest/menu/{$this->qrCode->code}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'table' => ['id', 'number', 'zone'],
                    'restaurant' => ['name', 'logo', 'color', 'welcome', 'wifi_name', 'wifi_password'],
                    'settings' => ['show_prices', 'allow_waiter_call', 'allow_reviews'],
                    'categories',
                ]
            ]);
    }

    public function test_menu_returns_correct_table_info(): void
    {
        $response = $this->getJson("/api/guest/menu/{$this->qrCode->code}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'table' => [
                        'id' => $this->table->id,
                        'number' => $this->table->number,
                        'zone' => $this->zone->name,
                    ],
                ],
            ]);
    }

    public function test_menu_returns_404_for_invalid_qr_code(): void
    {
        $response = $this->getJson('/api/guest/menu/INVALID_CODE');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Недействительный QR-код',
            ]);
    }

    public function test_menu_returns_404_for_inactive_qr_code(): void
    {
        $this->qrCode->update(['is_active' => false]);

        $response = $this->getJson("/api/guest/menu/{$this->qrCode->code}");

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Недействительный QR-код',
            ]);
    }

    public function test_menu_records_scan_count(): void
    {
        $initialCount = $this->qrCode->scan_count ?? 0;

        $this->getJson("/api/guest/menu/{$this->qrCode->code}");

        $this->qrCode->refresh();
        $this->assertEquals($initialCount + 1, $this->qrCode->scan_count);
        $this->assertNotNull($this->qrCode->last_scanned_at);
    }

    public function test_menu_only_returns_active_categories(): void
    {
        $activeCategory = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
        ]);

        Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/guest/menu/{$this->qrCode->code}");

        $response->assertOk();
        $categories = $response->json('data.categories');

        foreach ($categories as $category) {
            $this->assertTrue($category['is_active']);
        }
    }

    public function test_menu_only_returns_available_dishes(): void
    {
        Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'is_available' => true,
            'name' => 'Available Dish',
        ]);

        Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'is_available' => false,
            'name' => 'Unavailable Dish',
        ]);

        $response = $this->getJson("/api/guest/menu/{$this->qrCode->code}");

        $response->assertOk();
        $categories = $response->json('data.categories');

        $allDishes = collect($categories)->pluck('dishes')->flatten(1);
        $this->assertTrue($allDishes->every(fn($dish) => $dish['is_available'] === true));
    }

    // =====================================================
    // RESTAURANT INFO TESTS
    // =====================================================

    public function test_menu_returns_restaurant_settings(): void
    {
        GuestMenuSetting::set('restaurant_name', 'Test Restaurant', $this->restaurant->id);
        GuestMenuSetting::set('primary_color', '#ff0000', $this->restaurant->id);
        GuestMenuSetting::set('welcome_text', 'Welcome to our restaurant!', $this->restaurant->id);
        GuestMenuSetting::set('wifi_name', 'Restaurant_WiFi', $this->restaurant->id);
        GuestMenuSetting::set('wifi_password', 'password123', $this->restaurant->id);

        $response = $this->getJson("/api/guest/menu/{$this->qrCode->code}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'restaurant' => [
                        'name' => 'Test Restaurant',
                        'color' => '#ff0000',
                        'welcome' => 'Welcome to our restaurant!',
                        'wifi_name' => 'Restaurant_WiFi',
                        'wifi_password' => 'password123',
                    ],
                ],
            ]);
    }

    public function test_menu_returns_default_settings_when_not_configured(): void
    {
        // Create a second restaurant that won't have pre-seeded settings
        $restaurant2 = Restaurant::factory()->create();
        $zone2 = Zone::factory()->create(['restaurant_id' => $restaurant2->id]);
        $table2 = Table::factory()->create([
            'restaurant_id' => $restaurant2->id,
            'zone_id' => $zone2->id,
        ]);
        $qrCode2 = TableQrCode::create([
            'restaurant_id' => $restaurant2->id,
            'table_id' => $table2->id,
            'code' => 'TESTCODE2',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/guest/menu/{$qrCode2->code}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'restaurant' => [
                        'name' => 'Ресторан',
                        'color' => '#f97316',
                        'welcome' => 'Добро пожаловать!',
                    ],
                    'settings' => [
                        'show_prices' => true,
                        'allow_waiter_call' => true,
                        'allow_reviews' => true,
                    ],
                ],
            ]);
    }

    public function test_menu_respects_show_prices_setting(): void
    {
        GuestMenuSetting::set('show_prices', 'false', $this->restaurant->id);

        $response = $this->getJson("/api/guest/menu/{$this->qrCode->code}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'settings' => [
                        'show_prices' => false,
                    ],
                ],
            ]);
    }

    // =====================================================
    // DISH ENDPOINT TESTS
    // =====================================================

    public function test_can_get_single_available_dish(): void
    {
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'is_available' => true,
            'name' => 'Test Dish',
        ]);

        $response = $this->getJson("/api/guest/dish/{$dish->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $dish->id,
                    'name' => 'Test Dish',
                ],
            ]);
    }

    public function test_cannot_get_unavailable_dish(): void
    {
        $dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'is_available' => false,
        ]);

        $response = $this->getJson("/api/guest/dish/{$dish->id}");

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Блюдо не найдено',
            ]);
    }

    public function test_cannot_get_nonexistent_dish(): void
    {
        $response = $this->getJson('/api/guest/dish/99999');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Блюдо не найдено',
            ]);
    }

    // =====================================================
    // CALL WAITER FUNCTIONALITY TESTS
    // =====================================================

    public function test_can_call_waiter(): void
    {
        $response = $this->postJson('/api/guest/call', [
            'code' => $this->qrCode->code,
            'type' => 'waiter',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Официант уже идёт к вам!',
            ]);

        $this->assertDatabaseHas('waiter_calls', [
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'type' => 'waiter',
            'status' => 'pending',
        ]);
    }

    public function test_can_request_bill(): void
    {
        $response = $this->postJson('/api/guest/call', [
            'code' => $this->qrCode->code,
            'type' => 'bill',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('waiter_calls', [
            'table_id' => $this->table->id,
            'type' => 'bill',
        ]);
    }

    public function test_can_request_help(): void
    {
        $response = $this->postJson('/api/guest/call', [
            'code' => $this->qrCode->code,
            'type' => 'help',
            'message' => 'Need assistance with menu',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('waiter_calls', [
            'table_id' => $this->table->id,
            'type' => 'help',
            'message' => 'Need assistance with menu',
        ]);
    }

    public function test_call_waiter_validates_type(): void
    {
        $response = $this->postJson('/api/guest/call', [
            'code' => $this->qrCode->code,
            'type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_call_waiter_requires_code(): void
    {
        $response = $this->postJson('/api/guest/call', [
            'type' => 'waiter',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_call_waiter_fails_with_invalid_code(): void
    {
        $response = $this->postJson('/api/guest/call', [
            'code' => 'INVALID_CODE',
            'type' => 'waiter',
        ]);

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Недействительный QR-код',
            ]);
    }

    public function test_cannot_create_duplicate_pending_call(): void
    {
        WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'type' => 'waiter',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/guest/call', [
            'code' => $this->qrCode->code,
            'type' => 'waiter',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'Вызов уже отправлен, пожалуйста подождите',
            ]);
    }

    public function test_cannot_create_duplicate_accepted_call(): void
    {
        WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'type' => 'waiter',
            'status' => 'accepted',
        ]);

        $response = $this->postJson('/api/guest/call', [
            'code' => $this->qrCode->code,
            'type' => 'waiter',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'Вызов уже отправлен, пожалуйста подождите',
            ]);
    }

    public function test_can_create_different_type_call_when_pending_exists(): void
    {
        WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'type' => 'waiter',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/guest/call', [
            'code' => $this->qrCode->code,
            'type' => 'bill',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_can_create_new_call_after_completed(): void
    {
        WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'type' => 'waiter',
            'status' => 'completed',
        ]);

        $response = $this->postJson('/api/guest/call', [
            'code' => $this->qrCode->code,
            'type' => 'waiter',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_can_cancel_call(): void
    {
        $call = WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'type' => 'waiter',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/guest/call/cancel', [
            'code' => $this->qrCode->code,
            'call_id' => $call->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Вызов отменён',
            ]);

        $this->assertDatabaseHas('waiter_calls', [
            'id' => $call->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancel_call_fails_with_invalid_code(): void
    {
        $call = WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'type' => 'waiter',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/guest/call/cancel', [
            'code' => 'INVALID_CODE',
            'call_id' => $call->id,
        ]);

        $response->assertNotFound();
    }

    // =====================================================
    // ADMIN: ACTIVE CALLS MANAGEMENT
    // =====================================================

    public function test_can_get_active_calls(): void
    {
        WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'type' => 'waiter',
            'status' => 'pending',
        ]);

        WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'type' => 'bill',
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/guest/calls?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_active_calls_excludes_completed(): void
    {
        WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'type' => 'waiter',
            'status' => 'completed',
        ]);

        WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'type' => 'bill',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/guest/calls?restaurant_id={$this->restaurant->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_accept_call(): void
    {
        $call = WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'type' => 'waiter',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/guest/calls/{$call->id}/accept", [
                'user_id' => $this->user->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Вызов принят',
            ]);

        $this->assertDatabaseHas('waiter_calls', [
            'id' => $call->id,
            'status' => 'accepted',
            'accepted_by' => $this->user->id,
        ]);
    }

    public function test_cannot_accept_already_accepted_call(): void
    {
        $call = WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'type' => 'waiter',
            'status' => 'accepted',
            'accepted_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/guest/calls/{$call->id}/accept");

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'Вызов уже обработан',
            ]);
    }

    public function test_can_complete_call(): void
    {
        $call = WaiterCall::create([
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'type' => 'waiter',
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/guest/calls/{$call->id}/complete");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Вызов выполнен',
            ]);

        $this->assertDatabaseHas('waiter_calls', [
            'id' => $call->id,
            'status' => 'completed',
        ]);
    }

    // =====================================================
    // REVIEW SUBMISSION TESTS
    // =====================================================

    public function test_can_submit_review_with_qr_code(): void
    {
        $response = $this->postJson('/api/guest/review', [
            'code' => $this->qrCode->code,
            'rating' => 5,
            'comment' => 'Great service!',
            'guest_name' => 'John Doe',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Спасибо за ваш отзыв!',
            ]);

        $this->assertDatabaseHas('reviews', [
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'rating' => 5,
            'comment' => 'Great service!',
            'guest_name' => 'John Doe',
            'source' => 'qr',
        ]);
    }

    public function test_can_submit_review_with_order_number(): void
    {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'order_number' => 'ORD-12345',
        ]);

        $response = $this->postJson('/api/guest/review', [
            'order_number' => 'ORD-12345',
            'rating' => 4,
            'food_rating' => 5,
            'service_rating' => 4,
            'atmosphere_rating' => 4,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('reviews', [
            'restaurant_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'rating' => 4,
            'food_rating' => 5,
            'service_rating' => 4,
            'atmosphere_rating' => 4,
        ]);
    }

    public function test_submit_review_fails_without_code_or_order(): void
    {
        $response = $this->postJson('/api/guest/review', [
            'rating' => 5,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Не удалось определить ресторан. Отсканируйте QR код или укажите номер заказа.',
            ]);
    }

    public function test_submit_review_validates_rating(): void
    {
        $response = $this->postJson('/api/guest/review', [
            'code' => $this->qrCode->code,
            'rating' => 6,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_submit_review_rating_required(): void
    {
        $response = $this->postJson('/api/guest/review', [
            'code' => $this->qrCode->code,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_can_submit_review_with_all_ratings(): void
    {
        $response = $this->postJson('/api/guest/review', [
            'code' => $this->qrCode->code,
            'rating' => 4,
            'food_rating' => 5,
            'service_rating' => 4,
            'atmosphere_rating' => 3,
            'comment' => 'Good food but a bit noisy',
            'guest_name' => 'Jane Smith',
            'guest_phone' => '+79001234567',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('reviews', [
            'rating' => 4,
            'food_rating' => 5,
            'service_rating' => 4,
            'atmosphere_rating' => 3,
            'guest_name' => 'Jane Smith',
            'guest_phone' => '+79001234567',
        ]);
    }

    // =====================================================
    // ADMIN: REVIEW MANAGEMENT
    // =====================================================

    public function test_can_get_reviews(): void
    {
        Review::create([
            'restaurant_id' => $this->restaurant->id,
            'rating' => 5,
            'comment' => 'Excellent!',
        ]);

        Review::create([
            'restaurant_id' => $this->restaurant->id,
            'rating' => 4,
            'comment' => 'Good.',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/guest/reviews?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_can_filter_reviews_by_published(): void
    {
        Review::create([
            'restaurant_id' => $this->restaurant->id,
            'rating' => 5,
            'is_published' => true,
        ]);

        Review::create([
            'restaurant_id' => $this->restaurant->id,
            'rating' => 4,
            'is_published' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/guest/reviews?restaurant_id={$this->restaurant->id}&published=true");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_can_filter_reviews_by_rating(): void
    {
        Review::create([
            'restaurant_id' => $this->restaurant->id,
            'rating' => 5,
        ]);

        Review::create([
            'restaurant_id' => $this->restaurant->id,
            'rating' => 3,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/guest/reviews?restaurant_id={$this->restaurant->id}&rating=5");

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_can_get_review_stats(): void
    {
        Review::create([
            'restaurant_id' => $this->restaurant->id,
            'rating' => 5,
            'food_rating' => 5,
        ]);

        Review::create([
            'restaurant_id' => $this->restaurant->id,
            'rating' => 4,
            'food_rating' => 4,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/guest/reviews/stats?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total',
                    'average',
                    'distribution',
                    'food_avg',
                    'service_avg',
                    'atmosphere_avg',
                ]
            ]);

        $this->assertEquals(2, $response->json('data.total'));
        $this->assertEquals(4.5, $response->json('data.average'));
    }

    public function test_can_toggle_review_publish_status(): void
    {
        $review = Review::create([
            'restaurant_id' => $this->restaurant->id,
            'rating' => 5,
            'is_published' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/guest/reviews/{$review->id}/toggle");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Отзыв опубликован',
            ]);

        $this->assertTrue($review->fresh()->is_published);
    }

    public function test_can_respond_to_review(): void
    {
        $review = Review::create([
            'restaurant_id' => $this->restaurant->id,
            'rating' => 5,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/guest/reviews/{$review->id}/respond", [
                'response' => 'Thank you for your feedback!',
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Ответ сохранён',
            ]);

        $this->assertEquals('Thank you for your feedback!', $review->fresh()->admin_response);
    }

    public function test_respond_to_review_validates_response(): void
    {
        $review = Review::create([
            'restaurant_id' => $this->restaurant->id,
            'rating' => 5,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/guest/reviews/{$review->id}/respond", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['response']);
    }

    // =====================================================
    // QR CODE MANAGEMENT TESTS
    // =====================================================

    public function test_can_get_qr_codes(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/guest/qr-codes?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_generate_qr_code_for_table(): void
    {
        $newTable = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/guest/qr-codes', [
                'table_id' => $newTable->id,
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'QR-код создан',
            ]);

        $this->assertDatabaseHas('table_qr_codes', [
            'table_id' => $newTable->id,
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    public function test_generate_qr_returns_existing_if_exists(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/guest/qr-codes', [
                'table_id' => $this->table->id,
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'QR-код уже существует',
            ]);
    }

    public function test_can_generate_all_qr_codes(): void
    {
        $table1 = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
        ]);

        $table2 = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/guest/qr-codes/generate-all?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Создано QR-кодов: 2',
            ]);

        $this->assertDatabaseHas('table_qr_codes', ['table_id' => $table1->id]);
        $this->assertDatabaseHas('table_qr_codes', ['table_id' => $table2->id]);
    }

    public function test_can_regenerate_qr_code(): void
    {
        $oldCode = $this->qrCode->code;

        $response = $this->actingAs($this->user)
            ->postJson("/api/guest/qr-codes/{$this->qrCode->id}/regenerate");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'QR-код обновлён',
            ]);

        $this->qrCode->refresh();
        $this->assertNotEquals($oldCode, $this->qrCode->code);
        $this->assertEquals(0, $this->qrCode->scan_count);
        $this->assertNull($this->qrCode->last_scanned_at);
    }

    public function test_can_toggle_qr_code_status(): void
    {
        $this->assertTrue($this->qrCode->is_active);

        $response = $this->actingAs($this->user)
            ->postJson("/api/guest/qr-codes/{$this->qrCode->id}/toggle");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'QR-код деактивирован',
            ]);

        $this->assertFalse($this->qrCode->fresh()->is_active);
    }

    // =====================================================
    // SETTINGS MANAGEMENT TESTS
    // =====================================================

    public function test_can_get_guest_menu_settings(): void
    {
        GuestMenuSetting::set('restaurant_name', 'My Restaurant', $this->restaurant->id);
        GuestMenuSetting::set('primary_color', '#123456', $this->restaurant->id);

        $response = $this->actingAs($this->user)
            ->getJson("/api/guest/settings?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'restaurant_name' => 'My Restaurant',
                    'primary_color' => '#123456',
                ],
            ]);
    }

    public function test_can_update_guest_menu_settings(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("/api/guest/settings?restaurant_id={$this->restaurant->id}", [
                'settings' => [
                    'restaurant_name' => 'Updated Restaurant',
                    'welcome_text' => 'Welcome!',
                    'show_prices' => 'false',
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Настройки сохранены',
            ]);

        $this->assertDatabaseHas('guest_menu_settings', [
            'restaurant_id' => $this->restaurant->id,
            'key' => 'restaurant_name',
            'value' => 'Updated Restaurant',
        ]);

        $this->assertDatabaseHas('guest_menu_settings', [
            'restaurant_id' => $this->restaurant->id,
            'key' => 'welcome_text',
            'value' => 'Welcome!',
        ]);
    }

    public function test_update_settings_validates_settings_array(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("/api/guest/settings?restaurant_id={$this->restaurant->id}", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['settings']);
    }
}
