<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\Table;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Table>
 */
class TableFactory extends Factory
{
    protected $model = Table::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'zone_id' => Zone::factory(),
            'number' => fake()->unique()->numberBetween(1, 50),
            'name' => 'Стол ' . fake()->numberBetween(1, 50),
            'seats' => fake()->randomElement([2, 4, 6, 8]),
            'min_order' => 0,
            'shape' => fake()->randomElement(['round', 'square', 'rectangle', 'oval']),
            'position_x' => fake()->numberBetween(0, 800),
            'position_y' => fake()->numberBetween(0, 600),
            'width' => 80,
            'height' => 80,
            'rotation' => 0,
            'status' => 'free',
            'is_active' => true,
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'occupied',
        ]);
    }

    public function reserved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reserved',
        ]);
    }
}
