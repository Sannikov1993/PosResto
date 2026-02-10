<?php

namespace Tests\Feature\Api\Finance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\CashShift;
use App\Models\CashOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class FinanceTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Restaurant $restaurant;
    protected CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'is_active' => true,
            'role' => 'super_admin',
        ]);

        $this->shift = CashShift::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'cashier_id' => $this->user->id,
            'status' => 'open',
            'opening_amount' => 10000,
            'opened_at' => now(),
        ]);
    }

    protected function authenticate(): void
    {
        $token = $this->user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    // ===== DEPOSIT TESTS =====

    public function test_deposit_creates_operation_atomically(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/finance/operations/deposit', [
            'amount' => 5000,
            'description' => 'Test deposit',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('cash_operations', [
            'restaurant_id' => $this->restaurant->id,
            'type' => 'deposit',
            'amount' => 5000,
        ]);
    }

    public function test_deposit_requires_open_shift(): void
    {
        $this->authenticate();

        // Close the shift
        $this->shift->update(['status' => 'closed', 'closed_at' => now()]);

        $response = $this->postJson('/api/finance/operations/deposit', [
            'amount' => 5000,
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_deposit_validates_minimum_amount(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/finance/operations/deposit', [
            'amount' => 0,
        ]);

        $response->assertStatus(422);
    }

    // ===== WITHDRAWAL TESTS =====

    public function test_withdrawal_creates_operation_atomically(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/finance/operations/withdrawal', [
            'amount' => 1000,
            'category' => 'purchase',
            'description' => 'Test withdrawal',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('cash_operations', [
            'restaurant_id' => $this->restaurant->id,
            'type' => 'withdrawal',
            'amount' => 1000,
        ]);
    }

    public function test_withdrawal_requires_open_shift(): void
    {
        $this->authenticate();

        $this->shift->update(['status' => 'closed', 'closed_at' => now()]);

        $response = $this->postJson('/api/finance/operations/withdrawal', [
            'amount' => 1000,
            'category' => 'purchase',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_withdrawal_validates_category(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/finance/operations/withdrawal', [
            'amount' => 1000,
            'category' => 'invalid_category',
        ]);

        $response->assertStatus(422);
    }

    public function test_withdrawal_checks_sufficient_cash(): void
    {
        $this->authenticate();

        // Try to withdraw more than available
        $response = $this->postJson('/api/finance/operations/withdrawal', [
            'amount' => 999999,
            'category' => 'purchase',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    // ===== REFUND TESTS =====

    public function test_refund_creates_operation_atomically(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/finance/operations/refund', [
            'amount' => 500,
            'refund_method' => 'cash',
            'reason' => 'Test refund',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_refund_requires_open_shift(): void
    {
        $this->authenticate();

        $this->shift->update(['status' => 'closed', 'closed_at' => now()]);

        $response = $this->postJson('/api/finance/operations/refund', [
            'amount' => 500,
            'refund_method' => 'cash',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_refund_validates_method(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/finance/operations/refund', [
            'amount' => 500,
            'refund_method' => 'bitcoin',
        ]);

        $response->assertStatus(422);
    }

    public function test_cash_refund_checks_sufficient_cash(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/finance/operations/refund', [
            'amount' => 999999,
            'refund_method' => 'cash',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    // ===== MULTI-TENANCY TESTS =====

    public function test_deposit_uses_correct_restaurant(): void
    {
        $this->authenticate();

        $otherRestaurant = Restaurant::factory()->create();

        $response = $this->postJson('/api/finance/operations/deposit', [
            'amount' => 1000,
            'description' => 'Restaurant test',
        ]);

        $response->assertStatus(201);

        // Verify the operation is linked to the correct restaurant
        $operation = CashOperation::latest()->first();
        $this->assertEquals($this->restaurant->id, $operation->restaurant_id);
        $this->assertNotEquals($otherRestaurant->id, $operation->restaurant_id);
    }

    // ===== CONCURRENT OPERATIONS =====

    public function test_multiple_deposits_all_recorded(): void
    {
        $this->authenticate();

        $initialCount = CashOperation::where('restaurant_id', $this->restaurant->id)->count();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/finance/operations/deposit', [
                'amount' => 1000 + $i,
                'description' => "Deposit $i",
            ]);
        }

        $finalCount = CashOperation::where('restaurant_id', $this->restaurant->id)->count();
        $this->assertEquals($initialCount + 3, $finalCount);
    }

    // ===== UNAUTHENTICATED ACCESS =====

    public function test_deposit_requires_authentication(): void
    {
        $response = $this->postJson('/api/finance/operations/deposit', [
            'amount' => 5000,
        ]);

        $response->assertStatus(401);
    }

    public function test_withdrawal_requires_authentication(): void
    {
        $response = $this->postJson('/api/finance/operations/withdrawal', [
            'amount' => 1000,
            'category' => 'purchase',
        ]);

        $response->assertStatus(401);
    }

    public function test_refund_requires_authentication(): void
    {
        $response = $this->postJson('/api/finance/operations/refund', [
            'amount' => 500,
            'refund_method' => 'cash',
        ]);

        $response->assertStatus(401);
    }
}
