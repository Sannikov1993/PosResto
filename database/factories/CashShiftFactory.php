<?php

namespace Database\Factories;

use App\Models\CashShift;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashShiftFactory extends Factory
{
    protected $model = CashShift::class;

    public function definition(): array
    {
        $openingAmount = fake()->randomFloat(2, 0, 10000);

        return [
            'restaurant_id' => Restaurant::factory(),
            'cashier_id' => User::factory(),
            'cash_register_id' => null,
            'shift_number' => now()->format('dmy') . '-' . str_pad(fake()->numberBetween(1, 10), 2, '0', STR_PAD_LEFT),
            'status' => CashShift::STATUS_OPEN,
            'opening_amount' => $openingAmount,
            'closing_amount' => null,
            'expected_amount' => null,
            'difference' => null,
            'total_cash' => 0,
            'total_card' => 0,
            'total_online' => 0,
            'orders_count' => 0,
            'refunds_count' => 0,
            'refunds_amount' => 0,
            'opened_at' => now(),
            'closed_at' => null,
            'notes' => null,
        ];
    }

    /**
     * Closed shift
     */
    public function closed(): static
    {
        return $this->state(function (array $attributes) {
            $closingAmount = fake()->randomFloat(2, 0, 50000);
            $expectedAmount = $closingAmount + fake()->randomFloat(2, -100, 100);

            return [
                'status' => CashShift::STATUS_CLOSED,
                'closing_amount' => $closingAmount,
                'expected_amount' => $expectedAmount,
                'difference' => $closingAmount - $expectedAmount,
                'closed_at' => now(),
            ];
        });
    }

    /**
     * With transactions
     */
    public function withTransactions(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_cash' => fake()->randomFloat(2, 1000, 10000),
            'total_card' => fake()->randomFloat(2, 1000, 10000),
            'total_online' => fake()->randomFloat(2, 0, 5000),
            'orders_count' => fake()->numberBetween(10, 100),
        ]);
    }
}
