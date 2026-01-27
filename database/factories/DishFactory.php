<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Dish>
 */
class DishFactory extends Factory
{
    protected $model = Dish::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => 1,
            'category_id' => Category::factory(),
            'name' => fake()->randomElement([
                'Борщ украинский', 'Цезарь с курицей', 'Стейк из говядины',
                'Паста Карбонара', 'Пицца Маргарита', 'Ролл Филадельфия',
                'Тирамису', 'Чизкейк', 'Лимонад', 'Капучино'
            ]),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(200, 2000),
            'cost_price' => fake()->numberBetween(50, 500),
            'weight' => fake()->numberBetween(100, 500),
            'cooking_time' => fake()->numberBetween(5, 30),
            'calories' => fake()->numberBetween(100, 800),
            'is_available' => true,
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}
