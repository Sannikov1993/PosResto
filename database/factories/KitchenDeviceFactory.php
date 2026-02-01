<?php

namespace Database\Factories;

use App\Models\KitchenDevice;
use App\Models\KitchenStation;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KitchenDevice>
 */
class KitchenDeviceFactory extends Factory
{
    protected $model = KitchenDevice::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'device_id' => fake()->unique()->uuid(),
            'name' => fake()->randomElement([
                'Планшет кухни', 'Планшет бара', 'Устройство гриль',
                'Терминал пицца', 'Монитор кондитерской', 'Новое устройство'
            ]) . ' ' . fake()->numberBetween(1, 99),
            'kitchen_station_id' => null,
            'status' => KitchenDevice::STATUS_PENDING,
            'pin' => null,
            'settings' => null,
            'last_seen_at' => now(),
            'user_agent' => fake()->userAgent(),
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KitchenDevice::STATUS_ACTIVE,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KitchenDevice::STATUS_DISABLED,
        ]);
    }

    public function configured(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KitchenDevice::STATUS_ACTIVE,
            'kitchen_station_id' => KitchenStation::factory(),
        ]);
    }

    public function withPin(string $pin = '1234'): static
    {
        return $this->state(fn (array $attributes) => [
            'pin' => $pin,
        ]);
    }

    public function withStation(int $stationId): static
    {
        return $this->state(fn (array $attributes) => [
            'kitchen_station_id' => $stationId,
            'status' => KitchenDevice::STATUS_ACTIVE,
        ]);
    }
}
