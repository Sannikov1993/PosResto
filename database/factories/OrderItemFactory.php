<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Dish;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $price = fake()->numberBetween(200, 2000);
        $quantity = fake()->numberBetween(1, 3);

        return [
            'order_id' => Order::factory(),
            'dish_id' => Dish::factory(),
            'name' => fake()->randomElement([
                'Борщ украинский', 'Цезарь с курицей', 'Стейк из говядины',
                'Паста Карбонара', 'Пицца Маргарита', 'Ролл Филадельфия',
            ]),
            'quantity' => $quantity,
            'price' => $price,
            'total' => $price * $quantity,
            'modifiers_price' => 0,
            'discount' => 0,
            'guest_number' => 1,
            'status' => OrderItem::STATUS_NEW,
            'modifiers' => null,
            'comment' => fake()->optional()->sentence(),
        ];
    }

    public function cooking(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderItem::STATUS_COOKING,
            'cooking_started_at' => now(),
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderItem::STATUS_READY,
            'cooking_started_at' => now()->subMinutes(10),
            'cooking_finished_at' => now(),
        ]);
    }

    public function served(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderItem::STATUS_SERVED,
            'cooking_started_at' => now()->subMinutes(15),
            'cooking_finished_at' => now()->subMinutes(5),
            'served_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderItem::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
