<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    protected $model = DeliveryZone::class;

    public function definition(): array
    {
        $minDistance = $this->faker->randomFloat(1, 0, 5);
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => $this->faker->randomElement(['Zone A', 'Zone B', 'Zone C', 'Central', 'Near', 'Far']),
            'min_distance' => $minDistance,
            'max_distance' => $minDistance + $this->faker->randomFloat(1, 2, 5),
            'delivery_fee' => $this->faker->randomElement([0, 100, 150, 200, 250, 300]),
            'free_delivery_from' => $this->faker->optional()->randomElement([1500, 2000, 2500]),
            'estimated_time' => $this->faker->numberBetween(20, 60),
            'color' => $this->faker->hexColor(),
            'polygon' => null,
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(1, 10),
        ];
    }

    public function inactive(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function free(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'delivery_fee' => 0,
        ]);
    }
}
