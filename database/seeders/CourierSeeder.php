<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Courier;

/**
 * Сидер для курьеров
 */
class CourierSeeder extends Seeder
{
    public function run(): void
    {
        $couriers = [
            [
                'name' => 'Алексей Быстров',
                'phone' => '+7 (999) 111-22-33',
                'status' => 'available',
                'transport' => 'car',
                'is_active' => true,
            ],
            [
                'name' => 'Дмитрий Колёсов',
                'phone' => '+7 (999) 222-33-44',
                'status' => 'available',
                'transport' => 'scooter',
                'is_active' => true,
            ],
            [
                'name' => 'Иван Скороход',
                'phone' => '+7 (999) 333-44-55',
                'status' => 'busy',
                'transport' => 'bike',
                'is_active' => true,
            ],
            [
                'name' => 'Михаил Путевой',
                'phone' => '+7 (999) 444-55-66',
                'status' => 'offline',
                'transport' => 'car',
                'is_active' => true,
            ],
            [
                'name' => 'Сергей Доставкин',
                'phone' => '+7 (999) 555-66-77',
                'status' => 'available',
                'transport' => 'scooter',
                'is_active' => true,
            ],
        ];

        foreach ($couriers as $courier) {
            Courier::firstOrCreate(
                ['phone' => $courier['phone']],
                $courier
            );
        }

        $this->command->info('Couriers seeded: ' . count($couriers));
    }
}
