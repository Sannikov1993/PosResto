<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'tenant_id' => function (array $attributes) {
                return Restaurant::find($attributes['restaurant_id'])?->tenant_id ?? 1;
            },
            'name' => fake('ru_RU')->name(),
            'phone' => '+7' . fake()->numerify('9#########'),
            'email' => fake()->unique()->safeEmail(),
            'birth_date' => fake()->optional(0.7)->dateTimeBetween('-60 years', '-18 years'),
            'source' => fake()->randomElement(array_keys(Customer::SOURCES)),
            'bonus_balance' => fake()->numberBetween(0, 1000),
            'total_orders' => fake()->numberBetween(0, 50),
            'total_spent' => fake()->randomFloat(2, 0, 50000),
            'is_blacklisted' => false,
            'sms_consent' => true,
            'email_consent' => fake()->boolean(70),
        ];
    }

    public function withBonus(int $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'bonus_balance' => $balance,
        ]);
    }

    public function blacklisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_blacklisted' => true,
        ]);
    }

    public function vip(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_spent' => fake()->randomFloat(2, 50000, 200000),
            'total_orders' => fake()->numberBetween(50, 200),
            'tags' => ['vip'],
        ]);
    }
}
