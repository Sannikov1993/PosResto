<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DeliveryZone;
use App\Models\DeliverySetting;

/**
 * Сидер для системы доставки
 */
class DeliverySeeder extends Seeder
{
    public function run(): void
    {
        // Зоны доставки по умолчанию
        $zones = [
            [
                'name' => 'Зона 1 (до 3 км)',
                'min_distance' => 0,
                'max_distance' => 3,
                'delivery_fee' => 150,
                'free_delivery_from' => 1000,
                'estimated_time' => 45,
                'color' => '#10B981',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Зона 2 (3-5 км)',
                'min_distance' => 3,
                'max_distance' => 5,
                'delivery_fee' => 200,
                'free_delivery_from' => 1500,
                'estimated_time' => 60,
                'color' => '#F59E0B',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Зона 3 (5-10 км)',
                'min_distance' => 5,
                'max_distance' => 10,
                'delivery_fee' => 350,
                'free_delivery_from' => null,
                'estimated_time' => 90,
                'color' => '#EF4444',
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($zones as $zone) {
            DeliveryZone::firstOrCreate(
                ['name' => $zone['name'], 'restaurant_id' => 1],
                $zone
            );
        }

        // Настройки по умолчанию
        $settings = [
            'min_order_amount' => 500,
            'working_hours' => [
                'mon' => ['10:00', '22:00'],
                'tue' => ['10:00', '22:00'],
                'wed' => ['10:00', '22:00'],
                'thu' => ['10:00', '22:00'],
                'fri' => ['10:00', '23:00'],
                'sat' => ['10:00', '23:00'],
                'sun' => ['11:00', '21:00'],
            ],
            'sms_on_create' => false,
            'sms_on_courier' => false,
            'push_courier' => true,
            'alert_unassigned_minutes' => 10,
            'default_prep_time' => 30,
            'allow_preorder' => true,
            'preorder_days' => 7,
        ];

        foreach ($settings as $key => $value) {
            DeliverySetting::setValue($key, $value, 1);
        }

        $this->command->info('Delivery zones and settings seeded.');
    }
}
