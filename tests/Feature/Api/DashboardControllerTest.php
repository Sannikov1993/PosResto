<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Table;
use App\Models\Zone;
use App\Models\Reservation;
use Carbon\Carbon;
use App\Helpers\TimeHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant;
    protected Role $adminRole;
    protected User $user;
    protected string $token;
    protected Category $category;
    protected Dish $dish;
    protected Dish $dish2;
    protected Table $table;
    protected Zone $zone;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        // Create admin role with permissions
        $this->adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Administrator',
            'is_system' => true,
            'is_active' => true,
            'max_discount_percent' => 50,
            'max_refund_amount' => 10000,
            'max_cancel_amount' => 50000,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
            'can_access_kitchen' => true,
            'can_access_delivery' => true,
        ]);

        // Create permissions
        $permissions = ['reports.view', 'reports.analytics', 'settings.edit', 'finance.view'];
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

        // Create admin user
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        // Create category
        $this->category = Category::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Main Dishes',
        ]);

        // Create dishes
        $this->dish = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Steak',
            'price' => 1500,
        ]);

        $this->dish2 = Dish::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Pasta',
            'price' => 800,
        ]);

        // Create zone and table
        $this->zone = Zone::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
        ]);

        // Create customer
        $this->customer = Customer::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Customer',
            'phone' => '+79991234567',
            'email' => 'test@example.com',
            'total_orders' => 0,
            'total_spent' => 0,
            'is_blacklisted' => false,
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

    /**
     * Build URL with restaurant_id parameter for public endpoints
     */
    protected function publicUrl(string $path, array $params = []): string
    {
        $params['restaurant_id'] = $this->restaurant->id;
        return $path . '?' . http_build_query($params);
    }

    /**
     * Create orders with specified parameters
     */
    protected function createOrders(
        int $count,
        string $status = 'completed',
        string $paymentStatus = 'paid',
        ?Carbon $date = null,
        ?string $type = null,
        ?string $paymentMethod = null
    ): void {
        $date = $date ?? TimeHelper::today($this->restaurant->id);

        for ($i = 0; $i < $count; $i++) {
            $orderType = $type ?? fake()->randomElement(['dine_in', 'delivery', 'pickup']);
            $method = $paymentMethod ?? fake()->randomElement(['cash', 'card']);
            $total = fake()->numberBetween(500, 3000);

            // Use now() minus some minutes to ensure order time is always in the past
            // This prevents issues when tests run at times where addHours would create future timestamps
            $now = TimeHelper::now($this->restaurant->id);
            $orderTime = $now->copy()->subMinutes(($i + 1) * 10);

            // If date is explicitly set to a different day (e.g., yesterday), use that date
            if ($date->toDateString() !== $now->toDateString()) {
                $orderTime = $date->copy()->addHours(12); // Use noon for past dates
            }

            $order = Order::factory()->create([
                'restaurant_id' => $this->restaurant->id,
                'table_id' => $this->table->id,
                'customer_id' => $this->customer->id,
                'user_id' => $this->user->id,
                'type' => $orderType,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'payment_method' => $method,
                'total' => $total,
                'subtotal' => $total,
                'created_at' => $orderTime,
                'updated_at' => $orderTime,
            ]);

            // Add items to the order
            $itemsCount = rand(1, 3);
            for ($j = 0; $j < $itemsCount; $j++) {
                $dish = rand(0, 1) ? $this->dish : $this->dish2;
                $quantity = rand(1, 2);
                $itemTotal = $dish->price * $quantity;

                OrderItem::factory()->served()->create([
                    'order_id' => $order->id,
                    'dish_id' => $dish->id,
                    'name' => $dish->name,
                    'quantity' => $quantity,
                    'price' => $dish->price,
                    'total' => $itemTotal,
                ]);
            }
        }
    }

    /**
     * Create reservation for testing
     */
    protected function createReservation(array $attributes = []): Reservation
    {
        $defaults = [
            'restaurant_id' => $this->restaurant->id,
            'table_id' => $this->table->id,
            'guest_name' => 'Test Guest',
            'guest_phone' => '+79001234567',
            'date' => TimeHelper::today($this->restaurant->id)->format('Y-m-d'),
            'time_from' => '18:00',
            'time_to' => '20:00',
            'guests_count' => 2,
            'status' => 'pending',
            'deposit' => 0,
            'deposit_status' => Reservation::DEPOSIT_PENDING,
        ];

        return Reservation::create(array_merge($defaults, $attributes));
    }

    // ============================================
    // INDEX (MAIN DASHBOARD) TESTS
    // ============================================

    public function test_can_get_main_dashboard_stats(): void
    {
        // Create today's orders
        $this->createOrders(5, 'completed', 'paid');
        $this->createOrders(2, 'new', 'pending');
        $this->createOrders(1, 'cooking', 'pending');
        $this->createOrders(1, 'ready', 'pending');
        $this->createOrders(1, 'cancelled', 'pending');

        $this->authenticate();

        // Dashboard is public endpoint, need to pass restaurant_id
        $response = $this->getJson($this->publicUrl('/api/dashboard'));

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'new',
                    'cooking',
                    'ready',
                    'completed',
                    'cancelled',
                    'total_orders',
                    'revenue_today',
                    'avg_check',
                ],
            ]);
    }

    public function test_dashboard_returns_correct_status_counts(): void
    {
        // Create specific counts for each status
        $this->createOrders(3, 'new', 'pending');
        $this->createOrders(2, 'cooking', 'pending');
        $this->createOrders(1, 'ready', 'pending');
        $this->createOrders(5, 'completed', 'paid');
        $this->createOrders(2, 'cancelled', 'pending');

        $this->authenticate();

        // Dashboard is public endpoint, need to pass restaurant_id
        $response = $this->getJson($this->publicUrl('/api/dashboard'));

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals(3, $data['new']);
        $this->assertEquals(2, $data['cooking']);
        $this->assertEquals(1, $data['ready']);
        $this->assertEquals(5, $data['completed']);
        $this->assertEquals(2, $data['cancelled']);
        $this->assertEquals(13, $data['total_orders']);
    }

    public function test_dashboard_calculates_revenue_from_completed_orders(): void
    {
        // Create completed orders with known totals
        for ($i = 0; $i < 3; $i++) {
            Order::factory()->completed()->create([
                'restaurant_id' => $this->restaurant->id,
                'total' => 1000,
                'created_at' => TimeHelper::today($this->restaurant->id)->addHours(10 + $i),
            ]);
        }

        // Create non-completed order (should not be counted in revenue)
        Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status' => 'new',
            'total' => 5000,
            'created_at' => TimeHelper::today($this->restaurant->id)->addHours(15),
        ]);

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard'));

        $response->assertOk();
        $this->assertEquals(3000, $response->json('data.revenue_today'));
    }

    public function test_dashboard_returns_empty_stats_when_no_orders(): void
    {
        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard'));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'new' => 0,
                    'cooking' => 0,
                    'ready' => 0,
                    'completed' => 0,
                    'cancelled' => 0,
                    'total_orders' => 0,
                    'revenue_today' => 0,
                    'avg_check' => 0,
                ],
            ]);
    }

    public function test_dashboard_only_counts_todays_orders(): void
    {
        // Create yesterday's orders
        $this->createOrders(5, 'completed', 'paid', TimeHelper::yesterday($this->restaurant->id));

        // Create today's orders
        $this->createOrders(2, 'completed', 'paid', TimeHelper::today($this->restaurant->id));

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard'));

        $response->assertOk();
        // Only today's orders should be counted
        $this->assertEquals(2, $response->json('data.total_orders'));
    }

    public function test_dashboard_with_restaurant_id_parameter(): void
    {
        $this->createOrders(3, 'completed', 'paid');

        $this->authenticate();

        $response = $this->getJson("/api/dashboard?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    // ============================================
    // STATS ENDPOINT TESTS
    // ============================================

    public function test_can_get_detailed_stats_for_today(): void
    {
        $this->createOrders(5, 'completed', 'paid', TimeHelper::today($this->restaurant->id), 'dine_in', 'cash');
        $this->createOrders(3, 'completed', 'paid', TimeHelper::today($this->restaurant->id), 'delivery', 'card');

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/stats', ['period' => 'today']));

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period',
                    'start_date',
                    'end_date',
                    'total_orders',
                    'completed_orders',
                    'cancelled_orders',
                    'revenue',
                    'todayRevenue',
                    'ordersToday',
                    'avgCheck',
                    'avg_check',
                    'by_type' => [
                        'dine_in',
                        'delivery',
                        'pickup',
                    ],
                    'by_payment' => [
                        'cash',
                        'card',
                    ],
                ],
            ]);
    }

    public function test_can_get_stats_for_yesterday(): void
    {
        $this->createOrders(4, 'completed', 'paid', TimeHelper::yesterday($this->restaurant->id));

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/stats', ['period' => 'yesterday']));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'period' => 'yesterday',
                ],
            ]);

        $this->assertEquals(4, $response->json('data.total_orders'));
    }

    public function test_can_get_stats_for_week(): void
    {
        // Create orders throughout the week
        for ($i = 0; $i < 7; $i++) {
            $this->createOrders(2, 'completed', 'paid', TimeHelper::today($this->restaurant->id)->subDays($i));
        }

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/stats', ['period' => 'week']));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'period' => 'week',
                ],
            ]);
    }

    public function test_can_get_stats_for_month(): void
    {
        // Create orders throughout the month
        for ($i = 0; $i < 10; $i++) {
            $this->createOrders(1, 'completed', 'paid', TimeHelper::today($this->restaurant->id)->subDays($i * 3));
        }

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/stats', ['period' => 'month']));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'period' => 'month',
                ],
            ]);
    }

    public function test_can_get_stats_for_year(): void
    {
        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/stats', ['period' => 'year']));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'period' => 'year',
                ],
            ]);
    }

    public function test_stats_with_invalid_period_uses_default(): void
    {
        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/stats', ['period' => 'invalid']));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'period' => 'invalid', // Uses passed value but defaults to today's date range
                ],
            ]);
    }

    public function test_stats_counts_by_order_type(): void
    {
        $this->createOrders(3, 'completed', 'paid', TimeHelper::today($this->restaurant->id), 'dine_in');
        $this->createOrders(2, 'completed', 'paid', TimeHelper::today($this->restaurant->id), 'delivery');
        $this->createOrders(1, 'completed', 'paid', TimeHelper::today($this->restaurant->id), 'pickup');

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/stats', ['period' => 'today']));

        $response->assertOk();

        $byType = $response->json('data.by_type');
        $this->assertEquals(3, $byType['dine_in']);
        $this->assertEquals(2, $byType['delivery']);
        $this->assertEquals(1, $byType['pickup']);
    }

    public function test_stats_sums_by_payment_method(): void
    {
        // Create orders with specific payment methods (use past times to ensure they're in range)
        $now = TimeHelper::now($this->restaurant->id);
        for ($i = 0; $i < 3; $i++) {
            Order::factory()->completed()->create([
                'restaurant_id' => $this->restaurant->id,
                'payment_method' => 'cash',
                'total' => 1000,
                'created_at' => $now->copy()->subMinutes(30 + $i * 10),
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            Order::factory()->completed()->create([
                'restaurant_id' => $this->restaurant->id,
                'payment_method' => 'card',
                'total' => 1500,
                'created_at' => $now->copy()->subMinutes(60 + $i * 10),
            ]);
        }

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/stats', ['period' => 'today']));

        $response->assertOk();

        $byPayment = $response->json('data.by_payment');
        $this->assertEquals(3000, $byPayment['cash']);
        $this->assertEquals(3000, $byPayment['card']);
    }

    // ============================================
    // SALES CHART DATA TESTS
    // ============================================

    public function test_can_get_sales_data_for_week(): void
    {
        // Create orders for past 7 days
        for ($i = 0; $i < 7; $i++) {
            $this->createOrders(2, 'completed', 'paid', TimeHelper::today($this->restaurant->id)->subDays($i));
        }

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/sales', ['period' => 'week']));

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['date', 'day', 'orders', 'revenue'],
                ],
            ]);

        // Should return 7 days of data
        $this->assertCount(7, $response->json('data'));
    }

    public function test_can_get_sales_data_for_month(): void
    {
        // Create some orders
        for ($i = 0; $i < 10; $i++) {
            $this->createOrders(1, 'completed', 'paid', TimeHelper::today($this->restaurant->id)->subDays($i * 3));
        }

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/sales', ['period' => 'month']));

        $response->assertOk()
            ->assertJson(['success' => true]);

        // Should return 30 days of data
        $this->assertCount(30, $response->json('data'));
    }

    public function test_sales_data_returns_correct_daily_totals(): void
    {
        // Create specific orders for today
        for ($i = 0; $i < 3; $i++) {
            Order::factory()->completed()->create([
                'restaurant_id' => $this->restaurant->id,
                'total' => 1000,
                'created_at' => TimeHelper::today($this->restaurant->id)->addHours(10 + $i),
            ]);
        }

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/sales', ['period' => 'week']));

        $response->assertOk();

        // Find today's data (should be the last item)
        $salesData = $response->json('data');
        $todayData = end($salesData);

        $this->assertEquals(3, $todayData['orders']);
        $this->assertEquals(3000, $todayData['revenue']);
    }

    public function test_sales_default_period_is_week(): void
    {
        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/sales'));

        $response->assertOk();
        $this->assertCount(7, $response->json('data'));
    }

    // ============================================
    // POPULAR DISHES TESTS
    // ============================================

    public function test_can_get_popular_dishes(): void
    {
        // Create orders with items
        for ($i = 0; $i < 10; $i++) {
            $order = Order::factory()->completed()->create([
                'restaurant_id' => $this->restaurant->id,
                'created_at' => TimeHelper::today($this->restaurant->id)->subDays(rand(0, 29)),
            ]);

            // Add more of dish1 to make it popular
            OrderItem::factory()->served()->create([
                'order_id' => $order->id,
                'dish_id' => $this->dish->id,
                'name' => $this->dish->name,
                'quantity' => rand(2, 5),
                'price' => $this->dish->price,
                'total' => $this->dish->price * 3,
            ]);

            OrderItem::factory()->served()->create([
                'order_id' => $order->id,
                'dish_id' => $this->dish2->id,
                'name' => $this->dish2->name,
                'quantity' => 1,
                'price' => $this->dish2->price,
                'total' => $this->dish2->price,
            ]);
        }

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/popular-dishes'));

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'dish_id',
                        'name',
                        'total_quantity',
                        'total_revenue',
                        'order_count',
                    ],
                ],
            ]);
    }

    public function test_popular_dishes_with_custom_period(): void
    {
        // Create orders for different periods
        for ($i = 0; $i < 5; $i++) {
            $order = Order::factory()->completed()->create([
                'restaurant_id' => $this->restaurant->id,
                'created_at' => TimeHelper::today($this->restaurant->id)->subDays(5),
            ]);

            OrderItem::factory()->served()->create([
                'order_id' => $order->id,
                'dish_id' => $this->dish->id,
                'name' => $this->dish->name,
                'quantity' => 2,
                'price' => $this->dish->price,
                'total' => $this->dish->price * 2,
            ]);
        }

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/popular-dishes', ['period' => 'week']));

        $response->assertOk();
    }

    public function test_popular_dishes_with_custom_limit(): void
    {
        // Create orders with multiple dishes
        for ($i = 0; $i < 10; $i++) {
            $order = Order::factory()->completed()->create([
                'restaurant_id' => $this->restaurant->id,
                'created_at' => TimeHelper::today($this->restaurant->id)->subDays(rand(0, 29)),
            ]);

            OrderItem::factory()->served()->create([
                'order_id' => $order->id,
                'dish_id' => $this->dish->id,
                'name' => $this->dish->name,
                'quantity' => 1,
            ]);
        }

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/popular-dishes', ['limit' => 5]));

        $response->assertOk();
        $this->assertLessThanOrEqual(5, count($response->json('data')));
    }

    public function test_popular_dishes_returns_empty_when_no_orders(): void
    {
        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/popular-dishes'));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_popular_dishes_sorted_by_quantity(): void
    {
        // Create orders with known quantities
        $order1 = Order::factory()->completed()->create([
            'restaurant_id' => $this->restaurant->id,
            'created_at' => TimeHelper::today($this->restaurant->id),
        ]);

        OrderItem::factory()->served()->create([
            'order_id' => $order1->id,
            'dish_id' => $this->dish->id,
            'name' => $this->dish->name,
            'quantity' => 10, // More of this
        ]);

        OrderItem::factory()->served()->create([
            'order_id' => $order1->id,
            'dish_id' => $this->dish2->id,
            'name' => $this->dish2->name,
            'quantity' => 2, // Less of this
        ]);

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/popular-dishes'));

        $response->assertOk();

        $data = $response->json('data');
        if (count($data) >= 2) {
            // First dish should have more quantity
            $this->assertGreaterThanOrEqual(
                $data[1]['total_quantity'],
                $data[0]['total_quantity']
            );
        }
    }

    // ============================================
    // BRIEF STATS TESTS
    // ============================================

    public function test_can_get_brief_stats(): void
    {
        // Create yesterday's orders
        $this->createOrders(3, 'completed', 'paid', TimeHelper::yesterday($this->restaurant->id));

        // Create today's orders
        $this->createOrders(5, 'completed', 'paid', TimeHelper::today($this->restaurant->id));

        // Create today's reservations
        $this->createReservation(['status' => 'pending']);
        $this->createReservation(['status' => 'confirmed']);
        $this->createReservation(['status' => 'seated']);

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/stats/brief'));

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'yesterday' => [
                        'orders_count',
                        'total',
                    ],
                    'today' => [
                        'orders_count',
                        'total',
                        'reservations_count',
                        'pending_reservations',
                    ],
                ],
            ]);
    }

    public function test_brief_stats_returns_correct_yesterday_data(): void
    {
        // Create 3 paid orders yesterday with 1000 each
        for ($i = 0; $i < 3; $i++) {
            Order::factory()->completed()->create([
                'restaurant_id' => $this->restaurant->id,
                'total' => 1000,
                'created_at' => TimeHelper::yesterday($this->restaurant->id)->addHours(10 + $i),
            ]);
        }

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/stats/brief'));

        $response->assertOk();

        $yesterday = $response->json('data.yesterday');
        $this->assertEquals(3, $yesterday['orders_count']);
        $this->assertEquals(3000, $yesterday['total']);
    }

    public function test_brief_stats_returns_correct_today_data(): void
    {
        // Create today's orders
        for ($i = 0; $i < 2; $i++) {
            Order::factory()->completed()->create([
                'restaurant_id' => $this->restaurant->id,
                'total' => 1500,
                'created_at' => TimeHelper::today($this->restaurant->id)->addHours(10 + $i),
            ]);
        }

        // Create today's reservations
        $this->createReservation(['status' => 'pending']);
        $this->createReservation(['status' => 'confirmed']);
        $this->createReservation(['status' => 'seated', 'time_from' => '16:00', 'time_to' => '18:00']);

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/stats/brief'));

        $response->assertOk();

        $today = $response->json('data.today');
        $this->assertEquals(2, $today['orders_count']);
        $this->assertEquals(3000, $today['total']);
        $this->assertEquals(3, $today['reservations_count']);
        $this->assertEquals(2, $today['pending_reservations']); // pending + confirmed
    }

    public function test_brief_stats_empty_when_no_data(): void
    {
        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/stats/brief'));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'yesterday' => [
                        'orders_count' => 0,
                        'total' => 0,
                    ],
                    'today' => [
                        'orders_count' => 0,
                        'total' => 0,
                        'reservations_count' => 0,
                        'pending_reservations' => 0,
                    ],
                ],
            ]);
    }

    // ============================================
    // SALES REPORT TESTS
    // ============================================

    public function test_can_get_sales_report(): void
    {
        // Create orders across different days
        for ($i = 0; $i < 15; $i++) {
            $this->createOrders(1, 'completed', 'paid', TimeHelper::today($this->restaurant->id)->subDays($i));
        }

        $this->authenticate();

        $response = $this->getJson('/api/reports/sales');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period' => ['start', 'end'],
                    'summary' => ['total_orders', 'total_revenue', 'avg_check'],
                    'by_day',
                    'by_type' => ['dine_in', 'delivery', 'pickup'],
                    'by_payment' => ['cash', 'card'],
                ],
            ]);
    }

    public function test_sales_report_with_custom_date_range(): void
    {
        $startDate = TimeHelper::today($this->restaurant->id)->subDays(10)->format('Y-m-d');
        $endDate = TimeHelper::today($this->restaurant->id)->format('Y-m-d');

        // Create orders within range
        for ($i = 0; $i < 5; $i++) {
            $this->createOrders(1, 'completed', 'paid', TimeHelper::today($this->restaurant->id)->subDays($i));
        }

        $this->authenticate();

        $response = $this->getJson("/api/reports/sales?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();

        $period = $response->json('data.period');
        $this->assertEquals($startDate, $period['start']);
        $this->assertEquals($endDate, $period['end']);
    }

    public function test_sales_report_groups_by_day(): void
    {
        // Create orders for 3 days
        for ($i = 0; $i < 3; $i++) {
            Order::factory()->completed()->create([
                'restaurant_id' => $this->restaurant->id,
                'total' => 1000,
                'created_at' => TimeHelper::today($this->restaurant->id)->subDays($i)->addHours(12),
            ]);
        }

        $this->authenticate();

        $response = $this->getJson('/api/reports/sales');

        $response->assertOk();

        $byDay = $response->json('data.by_day');
        $this->assertGreaterThanOrEqual(1, count($byDay));
    }

    public function test_sales_report_groups_by_order_type(): void
    {
        $this->createOrders(3, 'completed', 'paid', TimeHelper::today($this->restaurant->id), 'dine_in');
        $this->createOrders(2, 'completed', 'paid', TimeHelper::today($this->restaurant->id), 'delivery');
        $this->createOrders(1, 'completed', 'paid', TimeHelper::today($this->restaurant->id), 'pickup');

        $this->authenticate();

        $response = $this->getJson('/api/reports/sales');

        $response->assertOk();

        $byType = $response->json('data.by_type');
        $this->assertEquals(3, $byType['dine_in']['orders']);
        $this->assertEquals(2, $byType['delivery']['orders']);
        $this->assertEquals(1, $byType['pickup']['orders']);
    }

    // ============================================
    // DISHES REPORT TESTS
    // ============================================

    public function test_can_get_dishes_report(): void
    {
        // Create orders with items
        for ($i = 0; $i < 5; $i++) {
            $order = Order::factory()->completed()->create([
                'restaurant_id' => $this->restaurant->id,
                'created_at' => TimeHelper::today($this->restaurant->id)->subDays(rand(0, 10)),
            ]);

            OrderItem::factory()->served()->create([
                'order_id' => $order->id,
                'dish_id' => $this->dish->id,
                'name' => $this->dish->name,
                'quantity' => rand(1, 3),
                'price' => $this->dish->price,
                'total' => $this->dish->price * 2,
            ]);
        }

        $this->authenticate();

        $response = $this->getJson('/api/reports/dishes');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period' => ['start', 'end'],
                    'total_revenue',
                    'total_dishes',
                    'dishes' => [
                        '*' => [
                            'dish_id',
                            'name',
                            'quantity',
                            'revenue',
                            'avg_price',
                            'order_count',
                            'percent',
                        ],
                    ],
                ],
            ]);
    }

    public function test_dishes_report_calculates_percentage(): void
    {
        // Create orders with known values
        $order = Order::factory()->completed()->create([
            'restaurant_id' => $this->restaurant->id,
            'created_at' => TimeHelper::today($this->restaurant->id),
        ]);

        OrderItem::factory()->served()->create([
            'order_id' => $order->id,
            'dish_id' => $this->dish->id,
            'name' => $this->dish->name,
            'quantity' => 1,
            'price' => 1000,
            'total' => 1000, // 50%
        ]);

        OrderItem::factory()->served()->create([
            'order_id' => $order->id,
            'dish_id' => $this->dish2->id,
            'name' => $this->dish2->name,
            'quantity' => 1,
            'price' => 1000,
            'total' => 1000, // 50%
        ]);

        $this->authenticate();

        $response = $this->getJson('/api/reports/dishes');

        $response->assertOk();

        $dishes = $response->json('data.dishes');
        $totalPercent = array_sum(array_column($dishes, 'percent'));
        // Total percent should be approximately 100
        $this->assertGreaterThanOrEqual(99, $totalPercent);
        $this->assertLessThanOrEqual(101, $totalPercent);
    }

    public function test_dishes_report_with_date_range(): void
    {
        $startDate = TimeHelper::today($this->restaurant->id)->subDays(7)->format('Y-m-d');
        $endDate = TimeHelper::today($this->restaurant->id)->format('Y-m-d');

        $this->authenticate();

        $response = $this->getJson("/api/reports/dishes?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();

        $period = $response->json('data.period');
        $this->assertEquals($startDate, $period['start']);
        $this->assertEquals($endDate, $period['end']);
    }

    // ============================================
    // HOURLY REPORT TESTS
    // ============================================

    public function test_can_get_hourly_report(): void
    {
        // Create orders at different hours
        $today = TimeHelper::today($this->restaurant->id);
        $hours = [10, 12, 13, 14, 19, 20, 21];

        foreach ($hours as $hour) {
            Order::factory()->completed()->create([
                'restaurant_id' => $this->restaurant->id,
                'total' => 1000,
                'created_at' => $today->copy()->setHour($hour)->setMinute(30),
            ]);
        }

        $this->authenticate();

        $response = $this->getJson('/api/reports/hourly');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['hour', 'orders', 'revenue'],
                ],
            ]);

        // Should return 24 hours
        $this->assertCount(24, $response->json('data'));
    }

    public function test_hourly_report_with_custom_date(): void
    {
        $yesterday = TimeHelper::yesterday($this->restaurant->id)->format('Y-m-d');

        // Create orders for yesterday
        Order::factory()->completed()->create([
            'restaurant_id' => $this->restaurant->id,
            'total' => 1500,
            'created_at' => TimeHelper::yesterday($this->restaurant->id)->setHour(14)->setMinute(30),
        ]);

        $this->authenticate();

        $response = $this->getJson("/api/reports/hourly?date={$yesterday}");

        $response->assertOk();
        $this->assertCount(24, $response->json('data'));
    }

    public function test_hourly_report_returns_zero_for_empty_hours(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/reports/hourly');

        $response->assertOk();

        $data = $response->json('data');
        foreach ($data as $hourData) {
            $this->assertEquals(0, $hourData['orders']);
            $this->assertEquals(0, $hourData['revenue']);
        }
    }

    // ============================================
    // RESTAURANT ENDPOINTS TESTS
    // ============================================

    public function test_can_get_restaurant_data(): void
    {
        $this->authenticate();

        $response = $this->getJson("/api/restaurants/{$this->restaurant->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->restaurant->id,
                ],
            ]);
    }

    public function test_get_nonexistent_restaurant_returns_404(): void
    {
        $this->authenticate();

        $response = $this->getJson('/api/restaurants/99999');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_update_restaurant_data(): void
    {
        $this->authenticate();

        $response = $this->putJson("/api/restaurants/{$this->restaurant->id}", [
            'name' => 'Updated Restaurant Name',
            'address' => 'New Address 123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Настройки обновлены',
            ]);

        $this->assertDatabaseHas('restaurants', [
            'id' => $this->restaurant->id,
            'name' => 'Updated Restaurant Name',
            'address' => 'New Address 123',
        ]);
    }

    public function test_update_restaurant_validates_fields(): void
    {
        $this->authenticate();

        $response = $this->putJson("/api/restaurants/{$this->restaurant->id}", [
            'email' => 'invalid-email',
            'tax_rate' => 150, // Invalid, max is 100
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'tax_rate']);
    }

    public function test_update_nonexistent_restaurant_returns_404(): void
    {
        $this->authenticate();

        $response = $this->putJson('/api/restaurants/99999', [
            'name' => 'Test',
        ]);

        $response->assertNotFound();
    }

    public function test_can_update_restaurant_name_and_address(): void
    {
        $this->authenticate();

        $response = $this->putJson("/api/restaurants/{$this->restaurant->id}", [
            'name' => 'New Restaurant Name',
            'address' => 'New Address 456',
        ]);

        $response->assertOk();

        $this->restaurant->refresh();
        $this->assertEquals('New Restaurant Name', $this->restaurant->name);
        $this->assertEquals('New Address 456', $this->restaurant->address);
    }

    // ============================================
    // BACKOFFICE DASHBOARD TESTS
    // ============================================

    public function test_can_access_backoffice_dashboard(): void
    {
        $this->createOrders(5, 'completed', 'paid');

        $this->authenticate();

        $response = $this->getJson('/api/backoffice/dashboard');

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_can_access_backoffice_dashboard_stats(): void
    {
        $this->createOrders(5, 'completed', 'paid');

        $this->authenticate();

        $response = $this->getJson('/api/backoffice/dashboard/stats');

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    // ============================================
    // AUTHENTICATION TESTS
    // ============================================

    /**
     * Note: Dashboard endpoints (/api/dashboard/*) are public (no auth middleware)
     * They use getRestaurantId() which falls back to restaurant_id parameter or default
     */
    public function test_dashboard_is_accessible_without_authentication(): void
    {
        // Dashboard endpoints are public - they don't require authentication
        $response = $this->getJson('/api/dashboard');
        $response->assertOk();
    }

    public function test_stats_endpoint_is_accessible_without_authentication(): void
    {
        $response = $this->getJson('/api/dashboard/stats');
        $response->assertOk();
    }

    public function test_sales_endpoint_is_accessible_without_authentication(): void
    {
        $response = $this->getJson('/api/dashboard/sales');
        $response->assertOk();
    }

    public function test_popular_dishes_is_accessible_without_authentication(): void
    {
        $response = $this->getJson('/api/dashboard/popular-dishes');
        $response->assertOk();
    }

    public function test_brief_stats_is_accessible_without_authentication(): void
    {
        $response = $this->getJson('/api/dashboard/stats/brief');
        $response->assertOk();
    }

    public function test_reports_require_authentication(): void
    {
        // Reports endpoints DO require authentication
        $response = $this->getJson('/api/reports/sales');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/reports/dishes');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/reports/hourly');
        $response->assertUnauthorized();
    }

    // ============================================
    // PERMISSION TESTS
    // ============================================

    public function test_reports_require_proper_permission(): void
    {
        // Create user without reports permission
        $limitedRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'limited',
            'name' => 'Limited',
            'is_system' => false,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => false,
        ]);

        $limitedUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'limited',
            'role_id' => $limitedRole->id,
            'is_active' => true,
        ]);

        $limitedToken = $limitedUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $limitedToken)
            ->getJson('/api/reports/sales');

        // Should be forbidden (403) for users without proper permissions
        $response->assertStatus(403);
    }

    // ============================================
    // EDGE CASES AND ERROR HANDLING
    // ============================================

    public function test_dashboard_handles_no_orders_gracefully(): void
    {
        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard'));

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_stats_handles_zero_orders_without_division_error(): void
    {
        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/stats'));

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.avg_check'));
    }

    public function test_popular_dishes_handles_no_completed_orders(): void
    {
        // Create only non-completed orders
        $this->createOrders(3, 'new', 'pending');

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/popular-dishes'));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    public function test_sales_report_handles_empty_date_range(): void
    {
        $futureDate = TimeHelper::today($this->restaurant->id)->addDays(30)->format('Y-m-d');

        $this->authenticate();

        $response = $this->getJson("/api/reports/sales?start_date={$futureDate}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_orders' => 0,
                        'total_revenue' => 0,
                    ],
                ],
            ]);
    }

    // ============================================
    // MULTI-RESTAURANT ISOLATION TESTS
    // ============================================

    public function test_dashboard_only_shows_own_restaurant_data(): void
    {
        // Create another restaurant with orders
        $otherRestaurant = Restaurant::factory()->create();
        $otherOrder = Order::factory()->completed()->create([
            'restaurant_id' => $otherRestaurant->id,
            'total' => 5000,
            'created_at' => TimeHelper::today($this->restaurant->id)->addHours(12),
        ]);

        // Create order for our restaurant
        Order::factory()->completed()->create([
            'restaurant_id' => $this->restaurant->id,
            'total' => 1000,
            'created_at' => TimeHelper::today($this->restaurant->id)->addHours(12),
        ]);

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard'));

        $response->assertOk();

        // Should only see our restaurant's orders (1 order, 1000 revenue)
        $this->assertEquals(1, $response->json('data.total_orders'));
        $this->assertEquals(1000, $response->json('data.revenue_today'));
    }

    public function test_stats_isolates_restaurant_data(): void
    {
        // Create orders for different restaurants (use past times to ensure they're in range)
        $otherRestaurant = Restaurant::factory()->create();
        $now = TimeHelper::now($this->restaurant->id);

        Order::factory()->completed()->create([
            'restaurant_id' => $otherRestaurant->id,
            'total' => 10000,
            'created_at' => $now->copy()->subMinutes(30),
        ]);

        Order::factory()->completed()->create([
            'restaurant_id' => $this->restaurant->id,
            'total' => 500,
            'created_at' => $now->copy()->subMinutes(20),
        ]);

        $this->authenticate();

        $response = $this->getJson($this->publicUrl('/api/dashboard/stats'));

        $response->assertOk();
        $this->assertEquals(500, $response->json('data.revenue'));
    }
}
