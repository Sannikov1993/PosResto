<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => 1,
            'name' => fake()->randomElement([
                'Супы', 'Салаты', 'Горячие блюда', 'Гарниры',
                'Напитки', 'Десерты', 'Закуски', 'Пицца', 'Роллы'
            ]),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->optional()->sentence(),
            'image' => null,
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}
