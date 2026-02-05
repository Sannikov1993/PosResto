<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('now', '+30 days');
        $timeFrom = fake()->randomElement(['12:00', '13:00', '14:00', '18:00', '19:00', '20:00', '21:00']);
        $timeTo = date('H:i', strtotime($timeFrom) + 2 * 3600); // +2 hours

        return [
            'restaurant_id' => Restaurant::factory(),
            'table_id' => Table::factory(),
            'guest_name' => fake()->name(),
            'guest_phone' => fake()->phoneNumber(),
            'guest_email' => fake()->optional()->email(),
            'date' => $date->format('Y-m-d'),
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'guests_count' => fake()->numberBetween(1, 8),
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
            'deposit' => fake()->randomElement([0, 0, 0, 500, 1000, 2000]),
            'deposit_status' => 'pending',
            'deposit_paid' => false,
        ];
    }

    /**
     * Pending status (default).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Confirmed reservation.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Seated - guests have arrived.
     */
    public function seated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'seated',
            'confirmed_at' => now()->subMinutes(30),
        ]);
    }

    /**
     * Completed reservation.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'confirmed_at' => now()->subHours(3),
        ]);
    }

    /**
     * Cancelled reservation.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * No-show reservation.
     */
    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'no_show',
        ]);
    }

    /**
     * With deposit.
     */
    public function withDeposit(float $amount = 1000): static
    {
        return $this->state(fn (array $attributes) => [
            'deposit' => $amount,
            'deposit_status' => 'pending',
            'deposit_paid' => false,
        ]);
    }

    /**
     * With paid deposit.
     */
    public function withPaidDeposit(float $amount = 1000): static
    {
        return $this->state(fn (array $attributes) => [
            'deposit' => $amount,
            'deposit_status' => 'paid',
            'deposit_paid' => true,
            'deposit_paid_at' => now(),
            'deposit_payment_method' => 'cash',
        ]);
    }

    /**
     * With customer.
     */
    public function withCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => Customer::factory(),
        ]);
    }

    /**
     * Today's reservation.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Tomorrow's reservation.
     */
    public function tomorrow(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => now()->addDay()->format('Y-m-d'),
        ]);
    }

    /**
     * Overnight reservation (crosses midnight).
     */
    public function overnight(): static
    {
        return $this->state(fn (array $attributes) => [
            'time_from' => '22:00',
            'time_to' => '02:00',
        ]);
    }

    /**
     * For specific table.
     */
    public function forTable(Table $table): static
    {
        return $this->state(fn (array $attributes) => [
            'table_id' => $table->id,
            'restaurant_id' => $table->restaurant_id,
        ]);
    }

    /**
     * For specific restaurant.
     */
    public function forRestaurant(Restaurant $restaurant): static
    {
        return $this->state(fn (array $attributes) => [
            'restaurant_id' => $restaurant->id,
        ]);
    }

    /**
     * At specific time.
     */
    public function atTime(string $timeFrom, string $timeTo): static
    {
        return $this->state(fn (array $attributes) => [
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
        ]);
    }

    /**
     * On specific date.
     */
    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }
}
