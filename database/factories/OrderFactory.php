<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => 1,
            'order_number' => 'ORD-' . fake()->unique()->numberBetween(1000, 9999),
            'daily_number' => fake()->numberBetween(1, 100),
            'type' => fake()->randomElement(['dine_in', 'delivery', 'pickup']),
            'status' => 'new',
            'payment_status' => 'pending',
            'subtotal' => fake()->numberBetween(500, 5000),
            'total' => fake()->numberBetween(500, 5000),
            'discount_amount' => 0,
            'delivery_fee' => 0,
            'tips' => 0,
            'persons' => fake()->numberBetween(1, 6),
            'comment' => fake()->optional()->sentence(),
        ];
    }

    public function dineIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'dine_in',
        ]);
    }

    public function delivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'delivery',
            'delivery_fee' => 200,
        ]);
    }

    public function pickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'pickup',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
