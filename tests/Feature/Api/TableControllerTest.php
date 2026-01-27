<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Table;
use App\Models\Zone;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TableControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->user = User::factory()->create();
        $this->zone = Zone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);
    }

    // ===== INDEX TESTS =====

    public function test_can_list_tables(): void
    {
        Table::factory()->count(5)->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/tables?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'number', 'status', 'seats']
                ]
            ])
            ->assertJson(['success' => true]);

        $this->assertCount(5, $response->json('data'));
    }

    // ===== FLOOR PLAN TESTS =====

    public function test_can_get_floor_plan(): void
    {
        Table::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/tables/floor-plan?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    // ===== ZONES TESTS =====

    public function test_can_list_zones(): void
    {
        Zone::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/tables/zones?restaurant_id={$this->restaurant->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertGreaterThanOrEqual(4, count($response->json('data')));
    }

    public function test_can_create_zone(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/tables/zones', [
                'name' => 'Новая зона',
                'color' => '#FF5733',
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('zones', [
            'name' => 'Новая зона',
            'color' => '#FF5733',
        ]);
    }

    public function test_can_update_zone(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("/api/tables/zones/{$this->zone->id}", [
                'name' => 'Обновлённая зона',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('zones', [
            'id' => $this->zone->id,
            'name' => 'Обновлённая зона',
        ]);
    }

    public function test_can_delete_zone(): void
    {
        $zoneToDelete = Zone::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/tables/zones/{$zoneToDelete->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('zones', [
            'id' => $zoneToDelete->id,
        ]);
    }

    // ===== TABLE CRUD TESTS =====

    public function test_can_create_table(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/tables', [
                'number' => '99',
                'name' => 'Стол 99',
                'seats' => 4,
                'zone_id' => $this->zone->id,
                'restaurant_id' => $this->restaurant->id,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tables', [
            'number' => '99',
            'seats' => 4,
        ]);
    }

    public function test_can_show_table(): void
    {
        $table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/tables/{$table->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['id' => $table->id]
            ]);
    }

    public function test_can_update_table(): void
    {
        $table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
            'seats' => 4,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/tables/{$table->id}", [
                'seats' => 6,
                'name' => 'VIP Стол',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tables', [
            'id' => $table->id,
            'seats' => 6,
            'name' => 'VIP Стол',
        ]);
    }

    public function test_can_delete_table(): void
    {
        $table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/tables/{$table->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('tables', [
            'id' => $table->id,
        ]);
    }

    // ===== STATUS UPDATE TESTS =====

    public function test_can_update_table_status(): void
    {
        $table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
            'status' => 'free',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/tables/{$table->id}/status", [
                'status' => 'occupied',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('tables', [
            'id' => $table->id,
            'status' => 'occupied',
        ]);
    }

    public function test_update_table_status_validates_status_value(): void
    {
        $table = Table::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'zone_id' => $this->zone->id,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/tables/{$table->id}/status", [
                'status' => 'invalid_status',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }
}
