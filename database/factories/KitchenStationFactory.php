<?php

namespace Database\Factories;

use App\Models\KitchenStation;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KitchenStation>
 */
class KitchenStationFactory extends Factory
{
    protected $model = KitchenStation::class;

    public function definition(): array
    {
        $name = fake()->randomElement([
            'Горячий цех', 'Холодный цех', 'Гриль', 'Пицца', 'Суши',
            'Кондитерская', 'Бар', 'Фритюр', 'Заготовочный цех'
        ]);

        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 9999),
            'icon' => fake()->randomElement(['fire', 'snowflake', 'utensils', 'pizza', 'fish', 'cake', 'glass', 'drumstick', null]),
            'color' => fake()->hexColor(),
            'description' => fake()->optional()->sentence(),
            'notification_sound' => fake()->randomElement(['bell', 'chime', 'ding', 'kitchen', 'alert', 'gong']),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
            'is_bar' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function bar(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Бар',
            'slug' => 'bar-' . fake()->unique()->numberBetween(1, 9999),
            'is_bar' => true,
            'icon' => 'glass',
            'color' => '#8B5CF6',
        ]);
    }
}
