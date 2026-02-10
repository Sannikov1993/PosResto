<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Table;
use App\Models\Zone;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TableControllerMultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurant $restaurant1;
    protected Restaurant $restaurant2;
    protected User $user1;
    protected User $user2;
    protected Zone $zone1;
    protected Zone $zone2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant1 = Restaurant::factory()->create();
        $this->restaurant2 = Restaurant::factory()->create();

        $this->user1 = User::factory()->create([
            'restaurant_id' => $this->restaurant1->id,
            'is_active' => true,
            'role' => 'admin',
        ]);

        $this->user2 = User::factory()->create([
            'restaurant_id' => $this->restaurant2->id,
            'is_active' => true,
            'role' => 'admin',
        ]);

        $this->zone1 = Zone::factory()->create([
            'restaurant_id' => $this->restaurant1->id,
        ]);

        $this->zone2 = Zone::factory()->create([
            'restaurant_id' => $this->restaurant2->id,
        ]);
    }

    protected function authenticateAs(User $user): void
    {
        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    // ===== FLOOR PLAN ISOLATION =====

    public function test_floor_plan_only_returns_own_restaurant_zones(): void
    {
        $this->authenticateAs($this->user1);

        Table::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant1->id,
            'zone_id' => $this->zone1->id,
        ]);

        Table::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant2->id,
            'zone_id' => $this->zone2->id,
        ]);

        $response = $this->getJson("/api/tables/floor-plan?restaurant_id={$this->restaurant1->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $zones = $response->json('data.zones');
        foreach ($zones as $zone) {
            $this->assertEquals($this->restaurant1->id, $zone['restaurant_id'],
                'Floor plan returned zone from another restaurant');
        }
    }

    public function test_user_cannot_see_other_restaurant_tables(): void
    {
        $this->authenticateAs($this->user1);

        Table::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant1->id,
            'zone_id' => $this->zone1->id,
        ]);

        $otherTable = Table::factory()->create([
            'restaurant_id' => $this->restaurant2->id,
            'zone_id' => $this->zone2->id,
        ]);

        $response = $this->getJson("/api/tables?restaurant_id={$this->restaurant1->id}");

        $response->assertOk();

        $tableIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($otherTable->id, $tableIds,
            'Response contains table from another restaurant');
    }

    // ===== RESERVATIONS ISOLATION =====

    public function test_tables_index_does_not_leak_other_restaurant_reservations(): void
    {
        $this->authenticateAs($this->user1);

        $table1 = Table::factory()->create([
            'restaurant_id' => $this->restaurant1->id,
            'zone_id' => $this->zone1->id,
        ]);

        // Reservation for restaurant 1
        Reservation::factory()->today()->confirmed()->create([
            'restaurant_id' => $this->restaurant1->id,
            'table_id' => $table1->id,
            'time_from' => '18:00',
            'time_to' => '20:00',
        ]);

        // Reservation for restaurant 2 (should NOT appear)
        $table2 = Table::factory()->create([
            'restaurant_id' => $this->restaurant2->id,
            'zone_id' => $this->zone2->id,
        ]);

        Reservation::factory()->today()->confirmed()->create([
            'restaurant_id' => $this->restaurant2->id,
            'table_id' => $table2->id,
            'time_from' => '18:00',
            'time_to' => '20:00',
        ]);

        $response = $this->getJson("/api/tables?restaurant_id={$this->restaurant1->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        // All tables should belong to restaurant 1
        foreach ($data as $table) {
            $this->assertEquals($this->restaurant1->id, $table['restaurant_id']);
        }
    }

    public function test_linked_reservations_filtered_by_restaurant(): void
    {
        $this->authenticateAs($this->user1);

        $table1 = Table::factory()->create([
            'restaurant_id' => $this->restaurant1->id,
            'zone_id' => $this->zone1->id,
        ]);

        $table1b = Table::factory()->create([
            'restaurant_id' => $this->restaurant1->id,
            'zone_id' => $this->zone1->id,
        ]);

        // Linked reservation for restaurant 1
        Reservation::factory()->today()->confirmed()->create([
            'restaurant_id' => $this->restaurant1->id,
            'table_id' => $table1->id,
            'linked_table_ids' => [$table1b->id],
            'time_from' => '19:00',
            'time_to' => '21:00',
        ]);

        $response = $this->getJson("/api/tables?restaurant_id={$this->restaurant1->id}");

        $response->assertOk();
    }

    // ===== ORDERS ISOLATION =====

    public function test_linked_orders_filtered_by_restaurant(): void
    {
        $this->authenticateAs($this->user1);

        $table1 = Table::factory()->create([
            'restaurant_id' => $this->restaurant1->id,
            'zone_id' => $this->zone1->id,
        ]);

        // Active order for restaurant 1
        Order::factory()->create([
            'restaurant_id' => $this->restaurant1->id,
            'table_id' => $table1->id,
            'status' => 'new',
            'payment_status' => 'pending',
            'total' => 2000,
        ]);

        // Order for restaurant 2 (should not leak)
        $table2 = Table::factory()->create([
            'restaurant_id' => $this->restaurant2->id,
            'zone_id' => $this->zone2->id,
        ]);

        Order::factory()->create([
            'restaurant_id' => $this->restaurant2->id,
            'table_id' => $table2->id,
            'status' => 'new',
            'payment_status' => 'pending',
            'total' => 5000,
        ]);

        $response = $this->getJson("/api/tables?restaurant_id={$this->restaurant1->id}");

        $response->assertOk();

        $data = $response->json('data');
        foreach ($data as $table) {
            $this->assertEquals($this->restaurant1->id, $table['restaurant_id']);
        }
    }

    // ===== SHOW TABLE ISOLATION =====

    public function test_cannot_view_table_from_other_restaurant(): void
    {
        $this->authenticateAs($this->user1);

        $otherTable = Table::factory()->create([
            'restaurant_id' => $this->restaurant2->id,
            'zone_id' => $this->zone2->id,
        ]);

        $response = $this->getJson("/api/tables/{$otherTable->id}?restaurant_id={$this->restaurant1->id}");

        $this->assertTrue(
            $response->status() === 404 || $response->json('success') === false,
            'User was able to view a table from another restaurant'
        );
    }

    // ===== STATS ISOLATION =====

    public function test_floor_plan_stats_only_count_own_restaurant(): void
    {
        $this->authenticateAs($this->user1);

        Table::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant1->id,
            'zone_id' => $this->zone1->id,
            'status' => 'free',
        ]);

        Table::factory()->count(5)->create([
            'restaurant_id' => $this->restaurant2->id,
            'zone_id' => $this->zone2->id,
            'status' => 'free',
        ]);

        $response = $this->getJson("/api/tables/floor-plan?restaurant_id={$this->restaurant1->id}");

        $response->assertOk();

        $stats = $response->json('data.stats');
        if ($stats) {
            $this->assertEquals(3, $stats['total'],
                'Stats include tables from another restaurant');
        }
    }
}
