<?php

namespace Database\Factories;

use App\Models\LegalEntity;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

class LegalEntityFactory extends Factory
{
    protected $model = LegalEntity::class;

    public function definition(): array
    {
        $type = fake()->randomElement([LegalEntity::TYPE_LLC, LegalEntity::TYPE_IE]);
        $isLLC = $type === LegalEntity::TYPE_LLC;

        return [
            'restaurant_id' => Restaurant::factory(),
            'tenant_id' => null,
            'name' => $isLLC
                ? 'LLC ' . fake('en_US')->company()
                : 'IE ' . fake('en_US')->lastName(),
            'short_name' => $isLLC ? 'LLC' : 'IE',
            'type' => $type,
            'inn' => $isLLC ? fake()->numerify('##########') : fake()->numerify('############'),
            'kpp' => $isLLC ? fake()->numerify('#########') : null,
            'ogrn' => $isLLC ? fake()->numerify('#############') : fake()->numerify('###############'),
            'legal_address' => fake('en_US')->address(),
            'actual_address' => fake('en_US')->address(),
            'director_name' => fake('en_US')->name(),
            'director_position' => $isLLC ? 'General Director' : null,
            'bank_name' => 'Bank ' . fake('en_US')->company(),
            'bank_bik' => fake()->numerify('#########'),
            'bank_account' => fake()->numerify('####################'),
            'bank_corr_account' => fake()->numerify('####################'),
            'taxation_system' => fake()->randomElement([
                LegalEntity::TAX_OSN,
                LegalEntity::TAX_USN_INCOME,
                LegalEntity::TAX_USN_INCOME_EXPENSE,
                LegalEntity::TAX_PATENT,
            ]),
            'vat_rate' => fake()->randomElement([0, 10, 20, null]),
            'has_alcohol_license' => fake()->boolean(30),
            'alcohol_license_number' => null,
            'alcohol_license_expires_at' => null,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    /**
     * LLC type
     */
    public function llc(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => LegalEntity::TYPE_LLC,
            'name' => 'LLC ' . fake('en_US')->company(),
            'short_name' => 'LLC',
            'inn' => fake()->numerify('##########'),
            'kpp' => fake()->numerify('#########'),
            'director_position' => 'General Director',
        ]);
    }

    /**
     * IE (Individual Entrepreneur) type
     */
    public function ie(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => LegalEntity::TYPE_IE,
            'name' => 'IE ' . fake('en_US')->lastName(),
            'short_name' => 'IE',
            'inn' => fake()->numerify('############'),
            'kpp' => null,
            'director_position' => null,
        ]);
    }

    /**
     * Default legal entity
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Inactive legal entity
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * With alcohol license
     */
    public function withAlcoholLicense(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_alcohol_license' => true,
            'alcohol_license_number' => fake()->numerify('##АП#######'),
            'alcohol_license_expires_at' => fake()->dateTimeBetween('+1 year', '+3 years'),
        ]);
    }
}
