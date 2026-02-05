<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Models\Order;

class CustomersApiTest extends ApiTestCase
{
    /** @test */
    public function it_returns_customers_list(): void
    {
        Customer::factory()->count(3)->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->apiGet('/customers');

        $this->assertApiSuccess($response);
        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function it_searches_customers(): void
    {
        Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Иван Петров',
            'phone' => '+79991111111',
        ]);
        Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Петр Сидоров',
            'phone' => '+79992222222',
        ]);

        $response = $this->apiGet('/customers?search=Иван');

        $this->assertApiSuccess($response);
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.name', 'Иван Петров');
    }

    /** @test */
    public function it_creates_customer(): void
    {
        $response = $this->apiPost('/customers', [
            'phone' => '+79991234567',
            'name' => 'Новый Клиент',
            'email' => 'client@example.com',
            'birthday' => '1990-05-15',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('customers', [
            'restaurant_id' => $this->restaurant->id,
            'phone' => '+79991234567',
            'name' => 'Новый Клиент',
        ]);
    }

    /** @test */
    public function it_validates_phone_required(): void
    {
        $response = $this->apiPost('/customers', [
            'name' => 'Без телефона',
        ]);

        $this->assertValidationError($response, 'phone');
    }

    /** @test */
    public function it_returns_single_customer(): void
    {
        $customer = Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Тестовый Клиент',
        ]);

        $response = $this->apiGet("/customers/{$customer->id}");

        $this->assertApiSuccess($response);
        $response->assertJsonPath('data.id', $customer->id);
        $response->assertJsonPath('data.name', 'Тестовый Клиент');
    }

    /** @test */
    public function it_finds_customer_by_phone(): void
    {
        $customer = Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'phone' => '+79997654321',
        ]);

        $response = $this->apiGet('/customers/phone/+79997654321');

        $this->assertApiSuccess($response);
        $response->assertJsonPath('data.id', $customer->id);
    }

    /** @test */
    public function it_returns_404_for_unknown_phone(): void
    {
        $response = $this->apiGet('/customers/phone/+70000000000');

        $this->assertApiError($response, 404, 'NOT_FOUND');
    }

    /** @test */
    public function it_updates_customer(): void
    {
        $customer = Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        $response = $this->apiPatch("/customers/{$customer->id}", [
            'name' => 'Обновлённое Имя',
            'notes' => 'VIP клиент',
        ]);

        $this->assertApiSuccess($response);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Обновлённое Имя',
            'notes' => 'VIP клиент',
        ]);
    }

    /** @test */
    public function it_returns_customer_orders(): void
    {
        $customer = Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        Order::factory()->count(2)->create([
            'restaurant_id' => $this->restaurant->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->apiGet("/customers/{$customer->id}/orders");

        $this->assertApiSuccess($response);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function it_returns_customer_bonus_balance(): void
    {
        $customer = Customer::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'bonus_balance' => 500,
        ]);

        $response = $this->apiGet("/customers/{$customer->id}/bonus");

        $this->assertApiSuccess($response);
        $this->assertEquals(500, (int) $response->json('data.balance'));
    }

    /** @test */
    public function it_prevents_access_to_other_restaurant_customers(): void
    {
        $otherRestaurant = \App\Models\Restaurant::factory()->create();
        $otherCustomer = Customer::factory()->create([
            'restaurant_id' => $otherRestaurant->id,
        ]);

        $response = $this->apiGet("/customers/{$otherCustomer->id}");

        $this->assertApiError($response, 404, 'NOT_FOUND');
    }
}
