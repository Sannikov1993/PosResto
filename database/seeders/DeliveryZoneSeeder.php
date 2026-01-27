<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DeliveryZone;

/**
 * Сидер для зон доставки
 */
class DeliveryZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'name' => 'Центр',
                'min_distance' => 0,
                'max_distance' => 2,
                'delivery_fee' => 0,
                'free_delivery_from' => 500,
                'estimated_time' => 30,
                'is_active' => true,
                'color' => '#22c55e',
                'sort_order' => 1,
                'polygon' => json_encode([
                    ['lat' => 55.7558, 'lng' => 37.6173],
                    ['lat' => 55.7658, 'lng' => 37.6273],
                    ['lat' => 55.7558, 'lng' => 37.6373],
                    ['lat' => 55.7458, 'lng' => 37.6273],
                ]),
            ],
            [
                'name' => 'Ближняя зона',
                'min_distance' => 2,
                'max_distance' => 5,
                'delivery_fee' => 150,
                'free_delivery_from' => 1500,
                'estimated_time' => 45,
                'is_active' => true,
                'color' => '#3b82f6',
                'sort_order' => 2,
                'polygon' => json_encode([
                    ['lat' => 55.7458, 'lng' => 37.6073],
                    ['lat' => 55.7758, 'lng' => 37.6073],
                    ['lat' => 55.7758, 'lng' => 37.6473],
                    ['lat' => 55.7458, 'lng' => 37.6473],
                ]),
            ],
            [
                'name' => 'Дальняя зона',
                'min_distance' => 5,
                'max_distance' => 10,
                'delivery_fee' => 300,
                'free_delivery_from' => 2500,
                'estimated_time' => 60,
                'is_active' => true,
                'color' => '#f59e0b',
                'sort_order' => 3,
                'polygon' => json_encode([
                    ['lat' => 55.7358, 'lng' => 37.5973],
                    ['lat' => 55.7858, 'lng' => 37.5973],
                    ['lat' => 55.7858, 'lng' => 37.6573],
                    ['lat' => 55.7358, 'lng' => 37.6573],
                ]),
            ],
        ];

        foreach ($zones as $zone) {
            DeliveryZone::firstOrCreate(
                ['name' => $zone['name']],
                $zone
            );
        }

        $this->command->info('Delivery zones seeded: ' . count($zones));
    }
}
