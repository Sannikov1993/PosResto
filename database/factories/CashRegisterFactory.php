<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\LegalEntity;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashRegisterFactory extends Factory
{
    protected $model = CashRegister::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'legal_entity_id' => LegalEntity::factory(),
            'name' => 'Касса ' . fake()->numberBetween(1, 10),
            'serial_number' => fake()->numerify('KKT-########'),
            'registration_number' => fake()->numerify('##########'),
            'fn_number' => fake()->numerify('################'),
            'fn_expires_at' => fake()->dateTimeBetween('+6 months', '+3 years'),
            'ofd_name' => fake()->randomElement(['ОФД.ру', 'Такском', 'Платформа ОФД', 'Первый ОФД']),
            'ofd_inn' => fake()->numerify('##########'),
            'is_active' => true,
            'is_default' => false,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    /**
     * Default cash register
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Inactive cash register
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * With expired FN (fiscal accumulator)
     */
    public function expiredFn(): static
    {
        return $this->state(fn (array $attributes) => [
            'fn_expires_at' => fake()->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }
}
