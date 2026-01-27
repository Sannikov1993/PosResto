<?php

namespace Database\Seeders;

use App\Models\KitchenStation;
use Illuminate\Database\Seeder;

class KitchenStationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stations = [
            [
                'restaurant_id' => 1,
                'name' => 'Горячий цех',
                'slug' => 'hot',
                'icon' => '🔥',
                'color' => '#EF4444',
                'description' => 'Горячие блюда, супы, гарниры',
                'sort_order' => 1,
            ],
            [
                'restaurant_id' => 1,
                'name' => 'Холодный цех',
                'slug' => 'cold',
                'icon' => '❄️',
                'color' => '#3B82F6',
                'description' => 'Салаты, закуски, холодные блюда',
                'sort_order' => 2,
            ],
            [
                'restaurant_id' => 1,
                'name' => 'Гриль',
                'slug' => 'grill',
                'icon' => '🥩',
                'color' => '#F97316',
                'description' => 'Стейки, шашлыки, блюда на гриле',
                'sort_order' => 3,
            ],
            [
                'restaurant_id' => 1,
                'name' => 'Пицца',
                'slug' => 'pizza',
                'icon' => '🍕',
                'color' => '#EAB308',
                'description' => 'Пицца и фокаччи',
                'sort_order' => 4,
            ],
            [
                'restaurant_id' => 1,
                'name' => 'Суши',
                'slug' => 'sushi',
                'icon' => '🍣',
                'color' => '#EC4899',
                'description' => 'Роллы, суши, сашими',
                'sort_order' => 5,
            ],
            [
                'restaurant_id' => 1,
                'name' => 'Бар',
                'slug' => 'bar',
                'icon' => '🍸',
                'color' => '#8B5CF6',
                'description' => 'Напитки, коктейли',
                'sort_order' => 6,
            ],
            [
                'restaurant_id' => 1,
                'name' => 'Десерты',
                'slug' => 'desserts',
                'icon' => '🍰',
                'color' => '#F472B6',
                'description' => 'Десерты и выпечка',
                'sort_order' => 7,
            ],
        ];

        foreach ($stations as $station) {
            KitchenStation::updateOrCreate(
                [
                    'restaurant_id' => $station['restaurant_id'],
                    'slug' => $station['slug'],
                ],
                $station
            );
        }
    }
}
