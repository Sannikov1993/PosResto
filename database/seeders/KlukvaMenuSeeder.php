<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Dish;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KlukvaMenuSeeder extends Seeder
{
    public function run(): void
    {
        $restaurantId = 1;

        // Создаём категории
        $categories = [
            ['name' => 'Пицца', 'icon' => '🍕', 'color' => '#EF4444', 'sort_order' => 1],
            ['name' => 'Комбо', 'icon' => '🎁', 'color' => '#8B5CF6', 'sort_order' => 2],
            ['name' => 'Закуски', 'icon' => '🍟', 'color' => '#F59E0B', 'sort_order' => 3],
            ['name' => 'Бургеры', 'icon' => '🍔', 'color' => '#10B981', 'sort_order' => 4],
            ['name' => 'Напитки', 'icon' => '🥤', 'color' => '#3B82F6', 'sort_order' => 5],
            ['name' => 'Десерты', 'icon' => '🍰', 'color' => '#EC4899', 'sort_order' => 6],
            ['name' => 'Соусы', 'icon' => '🌶️', 'color' => '#F97316', 'sort_order' => 7],
        ];

        $categoryIds = [];
        foreach ($categories as $cat) {
            $category = Category::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => Str::slug($cat['name'])],
                [
                    'name' => $cat['name'],
                    'icon' => $cat['icon'],
                    'color' => $cat['color'],
                    'sort_order' => $cat['sort_order'],
                    'is_active' => true,
                ]
            );
            $categoryIds[$cat['name']] = $category->id;
        }

        // Пиццы (с вариантами 25см и 30см)
        $pizzas = [
            [
                'name' => 'Пепперони',
                'description' => 'Томатный соус, сыр моцарелла, пикантные колбаски пепперони',
                'is_popular' => true,
                'variants' => [
                    ['name' => '25 см', 'price' => 499],
                    ['name' => '30 см', 'price' => 649],
                ]
            ],
            [
                'name' => 'Маргарита',
                'description' => 'Томатный соус, двойная порция сыра моцарелла и свежих томатов',
                'variants' => [
                    ['name' => '25 см', 'price' => 479],
                    ['name' => '30 см', 'price' => 629],
                ]
            ],
            [
                'name' => 'Сырная',
                'description' => 'Томатный соус, двойная порция сыра моцарелла',
                'variants' => [
                    ['name' => '25 см', 'price' => 469],
                    ['name' => '30 см', 'price' => 619],
                ]
            ],
            [
                'name' => 'Ветчина и сыр',
                'description' => 'Томатный соус, сыр моцарелла, нежная ветчина',
                'variants' => [
                    ['name' => '25 см', 'price' => 499],
                    ['name' => '30 см', 'price' => 649],
                ]
            ],
            [
                'name' => 'Ветчина и грибы',
                'description' => 'Томатный соус, сыр моцарелла, ветчина, шампиньоны',
                'variants' => [
                    ['name' => '25 см', 'price' => 499],
                    ['name' => '30 см', 'price' => 649],
                ]
            ],
            [
                'name' => 'Четыре сыра',
                'description' => 'Сливочный соус, моцарелла, пармезан, дор блю, фета',
                'variants' => [
                    ['name' => '25 см', 'price' => 619],
                    ['name' => '30 см', 'price' => 769],
                ]
            ],
            [
                'name' => 'Морская',
                'description' => 'Сливочный соус, тигровые креветки, сладкий перец, красный лук, сыр моцарелла',
                'variants' => [
                    ['name' => '25 см', 'price' => 599],
                    ['name' => '30 см', 'price' => 749],
                ]
            ],
            [
                'name' => 'Мясная',
                'description' => 'Томатный соус, цыпленок, ветчина, пепперони, бекон, сыр моцарелла',
                'is_popular' => true,
                'variants' => [
                    ['name' => '25 см', 'price' => 629],
                    ['name' => '30 см', 'price' => 779],
                ]
            ],
            [
                'name' => 'Гавайская',
                'description' => 'Томатный соус, сыр моцарелла, цыпленок, ананасы',
                'variants' => [
                    ['name' => '25 см', 'price' => 499],
                    ['name' => '30 см', 'price' => 649],
                ]
            ],
            [
                'name' => 'Мексиканская',
                'description' => 'Томатный соус, цыпленок, острый халапеньо, болгарский перец, красный лук, сыр моцарелла',
                'is_spicy' => true,
                'variants' => [
                    ['name' => '25 см', 'price' => 539],
                    ['name' => '30 см', 'price' => 689],
                ]
            ],
        ];

        foreach ($pizzas as $index => $pizza) {
            $parent = Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => Str::slug($pizza['name'])],
                [
                    'category_id' => $categoryIds['Пицца'],
                    'product_type' => 'parent',
                    'name' => $pizza['name'],
                    'description' => $pizza['description'],
                    'price' => 0,
                    'is_available' => true,
                    'is_popular' => $pizza['is_popular'] ?? false,
                    'is_spicy' => $pizza['is_spicy'] ?? false,
                    'sort_order' => $index + 1,
                ]
            );

            foreach ($pizza['variants'] as $vIndex => $variant) {
                Dish::updateOrCreate(
                    ['restaurant_id' => $restaurantId, 'parent_id' => $parent->id, 'variant_name' => $variant['name']],
                    [
                        'category_id' => $categoryIds['Пицца'],
                        'product_type' => 'variant',
                        'name' => $pizza['name'],
                        'variant_name' => $variant['name'],
                        'slug' => Str::slug($pizza['name'] . '-' . $variant['name']),
                        'description' => $pizza['description'],
                        'price' => $variant['price'],
                        'is_available' => true,
                        'variant_sort' => $vIndex,
                    ]
                );
            }
        }

        // Комбо (простые товары)
        $combos = [
            ['name' => 'Комбо Классика', 'description' => '2 пиццы на выбор (25см)', 'price' => 759],
            ['name' => 'Комбо 2 пиццы 30см', 'description' => 'Пепперони + Ветчина с сыром (30см)', 'price' => 990],
            ['name' => 'Комбо Три хита', 'description' => '3 пиццы на выбор (30см)', 'price' => 1499, 'is_popular' => true],
            ['name' => 'Комбо Пять пицц', 'description' => '5 пицц на выбор (30см) — для большой компании', 'price' => 2599],
        ];

        foreach ($combos as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => Str::slug($item['name'])],
                [
                    'category_id' => $categoryIds['Комбо'],
                    'product_type' => 'simple',
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'is_available' => true,
                    'is_popular' => $item['is_popular'] ?? false,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // Закуски
        $snacks = [
            ['name' => 'Картофель фри', 'description' => 'Хрустящий картофель фри', 'price' => 199, 'weight' => 150],
            ['name' => 'Стрипсы куриные', 'description' => 'Хрустящие куриные стрипсы в панировке', 'price' => 279, 'weight' => 170],
            ['name' => 'Наггетсы', 'description' => 'Куриные наггетсы в хрустящей панировке', 'price' => 199, 'weight' => 120],
            ['name' => 'Салат Цезарь с креветками', 'description' => 'Романо, тигровые креветки, пармезан, соус цезарь, сухарики', 'price' => 429, 'weight' => 250],
            ['name' => 'Салат Цезарь с курицей', 'description' => 'Романо, куриное филе, пармезан, соус цезарь, сухарики', 'price' => 379, 'weight' => 250, 'is_popular' => true],
            ['name' => 'Паста Карбонара', 'description' => 'Спагетти, бекон, сливочный соус, пармезан, яичный желток', 'price' => 399, 'weight' => 300],
        ];

        foreach ($snacks as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => Str::slug($item['name'])],
                [
                    'category_id' => $categoryIds['Закуски'],
                    'product_type' => 'simple',
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'weight' => $item['weight'] ?? null,
                    'is_available' => true,
                    'is_popular' => $item['is_popular'] ?? false,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // Бургеры
        $burgers = [
            ['name' => 'Чикен бургер', 'description' => 'Куриная котлета, салат айсберг, томаты, соус', 'price' => 329, 'weight' => 250],
            ['name' => 'Классический бургер', 'description' => 'Говяжья котлета, салат, томаты, лук, соус', 'price' => 329, 'weight' => 250],
            ['name' => 'Острый бургер', 'description' => 'Говяжья котлета, халапеньо, острый соус, салат', 'price' => 329, 'weight' => 250, 'is_spicy' => true],
            ['name' => 'BBQ бургер', 'description' => 'Говяжья котлета, бекон, соус барбекю, лук', 'price' => 299, 'weight' => 250],
        ];

        foreach ($burgers as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => Str::slug($item['name'])],
                [
                    'category_id' => $categoryIds['Бургеры'],
                    'product_type' => 'simple',
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'weight' => $item['weight'] ?? null,
                    'is_available' => true,
                    'is_spicy' => $item['is_spicy'] ?? false,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // Напитки (с вариантами объёма)
        $drinks = [
            [
                'name' => 'Coca-Cola',
                'description' => 'Классическая Кока-Кола',
                'variants' => [
                    ['name' => '0.5л', 'price' => 120],
                    ['name' => '1л', 'price' => 180],
                ]
            ],
            [
                'name' => 'Морс клюквенный',
                'description' => 'Домашний клюквенный морс',
                'price' => 75,
            ],
            [
                'name' => 'Сок яблочный',
                'description' => 'Натуральный яблочный сок',
                'price' => 250,
                'weight' => 1000,
            ],
            [
                'name' => 'Сок апельсиновый',
                'description' => 'Натуральный апельсиновый сок',
                'price' => 250,
                'weight' => 1000,
            ],
        ];

        foreach ($drinks as $index => $item) {
            if (isset($item['variants'])) {
                $parent = Dish::updateOrCreate(
                    ['restaurant_id' => $restaurantId, 'slug' => Str::slug($item['name'])],
                    [
                        'category_id' => $categoryIds['Напитки'],
                        'product_type' => 'parent',
                        'name' => $item['name'],
                        'description' => $item['description'],
                        'price' => 0,
                        'is_available' => true,
                        'sort_order' => $index + 1,
                    ]
                );

                foreach ($item['variants'] as $vIndex => $variant) {
                    Dish::updateOrCreate(
                        ['restaurant_id' => $restaurantId, 'parent_id' => $parent->id, 'variant_name' => $variant['name']],
                        [
                            'category_id' => $categoryIds['Напитки'],
                            'product_type' => 'variant',
                            'name' => $item['name'],
                            'variant_name' => $variant['name'],
                            'slug' => Str::slug($item['name'] . '-' . $variant['name']),
                            'price' => $variant['price'],
                            'is_available' => true,
                            'variant_sort' => $vIndex,
                        ]
                    );
                }
            } else {
                Dish::updateOrCreate(
                    ['restaurant_id' => $restaurantId, 'slug' => Str::slug($item['name'])],
                    [
                        'category_id' => $categoryIds['Напитки'],
                        'product_type' => 'simple',
                        'name' => $item['name'],
                        'description' => $item['description'],
                        'price' => $item['price'],
                        'weight' => $item['weight'] ?? null,
                        'is_available' => true,
                        'sort_order' => $index + 1,
                    ]
                );
            }
        }

        // Десерты
        $desserts = [
            ['name' => 'Рулетики с ананасами', 'description' => 'Сладкие рулетики из теста с ананасовой начинкой', 'price' => 249],
            ['name' => 'Рулетики с клюквой', 'description' => 'Сладкие рулетики из теста с клюквенной начинкой', 'price' => 249],
            ['name' => 'Рулетики с сыром', 'description' => 'Рулетики из теста с сырной начинкой', 'price' => 279],
            ['name' => 'Чизкейк Нью-Йорк', 'description' => 'Классический американский чизкейк', 'price' => 199, 'is_popular' => true],
        ];

        foreach ($desserts as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => Str::slug($item['name'])],
                [
                    'category_id' => $categoryIds['Десерты'],
                    'product_type' => 'simple',
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'is_available' => true,
                    'is_popular' => $item['is_popular'] ?? false,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // Соусы
        $sauces = [
            'Соус Пицца', 'Соус Барбекю', 'Кетчуп', 'Соус Медово-горчичный',
            'Соус Сырный', 'Соус Цезарь', 'Соус Чесночный', 'Соус Чили сладкий'
        ];

        foreach ($sauces as $index => $name) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => Str::slug($name)],
                [
                    'category_id' => $categoryIds['Соусы'],
                    'product_type' => 'simple',
                    'name' => $name,
                    'description' => 'Порция соуса 30г',
                    'price' => 50,
                    'weight' => 30,
                    'is_available' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }

        $this->command->info('Меню "Клюква" успешно загружено!');
        $this->command->info('Категорий: ' . count($categories));
        $this->command->info('Пицц с вариантами: ' . count($pizzas));
    }
}
