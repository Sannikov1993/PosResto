<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\Customer;
use App\Models\KitchenStation;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $restaurantId = 2;

        // Kitchen Stations
        $hotStation = KitchenStation::where('restaurant_id', $restaurantId)->where('slug', 'goryachiy-tseh')->first();

        $coldStation = KitchenStation::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'slug' => 'cold'],
            ['name' => 'Холодный цех', 'icon' => '🥗', 'color' => '#3B82F6', 'sort_order' => 2, 'is_active' => true]
        );

        $barStation = KitchenStation::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'slug' => 'bar'],
            ['name' => 'Бар', 'icon' => '🍸', 'color' => '#8B5CF6', 'sort_order' => 3, 'is_active' => true, 'is_bar' => true]
        );

        $dessertStation = KitchenStation::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'slug' => 'dessert'],
            ['name' => 'Кондитерская', 'icon' => '🍰', 'color' => '#EC4899', 'sort_order' => 4, 'is_active' => true]
        );

        $this->command->info('Kitchen stations ready');

        // Get existing categories
        $pizzaCat = Category::where('restaurant_id', $restaurantId)->where('name', 'Пицца')->first();
        $alcoholCat = Category::where('restaurant_id', $restaurantId)->where('name', 'Алкоголь')->first();

        // Create new categories
        $saladsCat = Category::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'slug' => 'salads'],
            ['tenant_id' => $tenantId, 'name' => 'Салаты', 'icon' => '🥗', 'sort_order' => 1, 'is_active' => true]
        );

        $soupsCat = Category::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'slug' => 'soups'],
            ['tenant_id' => $tenantId, 'name' => 'Супы', 'icon' => '🍲', 'sort_order' => 2, 'is_active' => true]
        );

        $hotCat = Category::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'slug' => 'hot'],
            ['tenant_id' => $tenantId, 'name' => 'Горячее', 'icon' => '🍖', 'sort_order' => 3, 'is_active' => true]
        );

        $pastaCat = Category::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'slug' => 'pasta'],
            ['tenant_id' => $tenantId, 'name' => 'Паста', 'icon' => '🍝', 'sort_order' => 4, 'is_active' => true]
        );

        $sidesCat = Category::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'slug' => 'sides'],
            ['tenant_id' => $tenantId, 'name' => 'Гарниры', 'icon' => '🍟', 'sort_order' => 5, 'is_active' => true]
        );

        $dessertsCat = Category::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'slug' => 'desserts'],
            ['tenant_id' => $tenantId, 'name' => 'Десерты', 'icon' => '🍰', 'sort_order' => 6, 'is_active' => true]
        );

        $drinksCat = Category::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'slug' => 'drinks'],
            ['tenant_id' => $tenantId, 'name' => 'Напитки', 'icon' => '🥤', 'sort_order' => 7, 'is_active' => true]
        );

        $cocktailsCat = Category::firstOrCreate(
            ['restaurant_id' => $restaurantId, 'slug' => 'cocktails'],
            ['tenant_id' => $tenantId, 'name' => 'Коктейли', 'icon' => '🍹', 'sort_order' => 8, 'is_active' => true]
        );

        $this->command->info('Categories ready');

        // Dishes: [category_id, name, price, description, kitchen_station_id]
        $dishes = [
            // Пицца
            [$pizzaCat->id, 'Пепперони', 550, 'Пепперони, моцарелла, томатный соус', $hotStation?->id],
            [$pizzaCat->id, 'Четыре сыра', 620, 'Моцарелла, пармезан, горгонзола, эмменталь', $hotStation?->id],
            [$pizzaCat->id, 'Гавайская', 490, 'Ветчина, ананас, моцарелла', $hotStation?->id],
            [$pizzaCat->id, 'Карбонара', 580, 'Бекон, яйцо, пармезан, сливочный соус', $hotStation?->id],

            // Салаты
            [$saladsCat->id, 'Цезарь с курицей', 420, 'Куриная грудка, романо, пармезан, соус цезарь', $coldStation->id],
            [$saladsCat->id, 'Греческий', 380, 'Томаты, огурцы, маслины, фета', $coldStation->id],
            [$saladsCat->id, 'Оливье', 320, 'Классический рецепт с говядиной', $coldStation->id],
            [$saladsCat->id, 'Капрезе', 450, 'Моцарелла, томаты, базилик, песто', $coldStation->id],

            // Супы
            [$soupsCat->id, 'Борщ', 280, 'Со сметаной и пампушками', $hotStation?->id],
            [$soupsCat->id, 'Том Ям', 450, 'С креветками и грибами', $hotStation?->id],
            [$soupsCat->id, 'Солянка', 320, 'Мясная сборная', $hotStation?->id],
            [$soupsCat->id, 'Крем-суп грибной', 290, 'Из шампиньонов со сливками', $hotStation?->id],

            // Горячее
            [$hotCat->id, 'Стейк Рибай', 1450, '300г, мраморная говядина', $hotStation?->id],
            [$hotCat->id, 'Свиная рулька', 890, 'Запечённая с квашеной капустой', $hotStation?->id],
            [$hotCat->id, 'Куриная грудка', 520, 'На гриле с овощами', $hotStation?->id],
            [$hotCat->id, 'Лосось на гриле', 780, '200г, с лимонным соусом', $hotStation?->id],
            [$hotCat->id, 'Бургер Классик', 490, 'Говяжья котлета, сыр, соус', $hotStation?->id],

            // Паста
            [$pastaCat->id, 'Паста Карбонара', 420, 'Бекон, пармезан, сливки', $hotStation?->id],
            [$pastaCat->id, 'Паста Болоньезе', 390, 'Мясной соус, пармезан', $hotStation?->id],
            [$pastaCat->id, 'Паста Песто', 380, 'Соус песто, кедровые орехи', $hotStation?->id],
            [$pastaCat->id, 'Паста с морепродуктами', 550, 'Креветки, мидии, кальмары', $hotStation?->id],

            // Гарниры
            [$sidesCat->id, 'Картофель фри', 180, 'Хрустящий, с соусом', $hotStation?->id],
            [$sidesCat->id, 'Рис', 120, 'Отварной рассыпчатый', $hotStation?->id],
            [$sidesCat->id, 'Овощи гриль', 220, 'Кабачок, перец, баклажан', $hotStation?->id],
            [$sidesCat->id, 'Пюре', 150, 'Картофельное со сливками', $hotStation?->id],

            // Десерты
            [$dessertsCat->id, 'Тирамису', 350, 'Классический итальянский', $dessertStation->id],
            [$dessertsCat->id, 'Чизкейк', 320, 'Нью-Йорк с ягодами', $dessertStation->id],
            [$dessertsCat->id, 'Наполеон', 280, 'Слоёный с кремом', $dessertStation->id],
            [$dessertsCat->id, 'Мороженое', 190, '3 шарика на выбор', $dessertStation->id],

            // Напитки
            [$drinksCat->id, 'Кола', 150, '0.5л', $barStation->id],
            [$drinksCat->id, 'Сок апельсиновый', 180, 'Свежевыжатый', $barStation->id],
            [$drinksCat->id, 'Чай', 120, 'Чёрный/зелёный', $barStation->id],
            [$drinksCat->id, 'Кофе Американо', 150, 'Двойной эспрессо', $barStation->id],
            [$drinksCat->id, 'Капучино', 200, 'С молочной пенкой', $barStation->id],
            [$drinksCat->id, 'Латте', 220, 'С ванильным сиропом', $barStation->id],

            // Коктейли
            [$cocktailsCat->id, 'Мохито', 380, 'Ром, мята, лайм', $barStation->id],
            [$cocktailsCat->id, 'Пина Колада', 420, 'Ром, кокос, ананас', $barStation->id],
            [$cocktailsCat->id, 'Маргарита', 390, 'Текила, лайм, апельсиновый ликёр', $barStation->id],
            [$cocktailsCat->id, 'Лонг Айленд', 450, 'Микс крепких напитков', $barStation->id],

            // Алкоголь
            [$alcoholCat->id, 'Пиво светлое', 250, '0.5л', $barStation->id],
            [$alcoholCat->id, 'Пиво тёмное', 280, '0.5л', $barStation->id],
            [$alcoholCat->id, 'Вино красное', 350, 'Бокал 150мл', $barStation->id],
            [$alcoholCat->id, 'Вино белое', 320, 'Бокал 150мл', $barStation->id],
        ];

        $sort = 0;
        foreach ($dishes as [$catId, $name, $price, $desc, $stationId]) {
            $slug = \Illuminate\Support\Str::slug($name) . '-' . $sort;
            Dish::firstOrCreate(
                ['restaurant_id' => $restaurantId, 'name' => $name],
                [
                    'tenant_id' => $tenantId,
                    'restaurant_id' => $restaurantId,
                    'category_id' => $catId,
                    'name' => $name,
                    'slug' => $slug,
                    'price' => $price,
                    'description' => $desc,
                    'kitchen_station_id' => $stationId,
                    'is_available' => true,
                    'sort_order' => $sort++,
                ]
            );
        }

        $this->command->info('Dishes created: ' . count($dishes));

        // Modifiers
        $modifiers = [
            ['name' => 'Размер пиццы', 'type' => 'single', 'options' => [
                ['name' => '25 см', 'price' => 0],
                ['name' => '30 см', 'price' => 100],
                ['name' => '35 см', 'price' => 200],
            ]],
            ['name' => 'Дополнительно к пицце', 'type' => 'multiple', 'options' => [
                ['name' => 'Двойной сыр', 'price' => 100],
                ['name' => 'Пепперони', 'price' => 80],
                ['name' => 'Грибы', 'price' => 60],
                ['name' => 'Оливки', 'price' => 50],
            ]],
            ['name' => 'Соус', 'type' => 'single', 'options' => [
                ['name' => 'Кетчуп', 'price' => 30],
                ['name' => 'Майонез', 'price' => 30],
                ['name' => 'Сырный', 'price' => 50],
                ['name' => 'Чесночный', 'price' => 40],
            ]],
            ['name' => 'Степень прожарки', 'type' => 'single', 'options' => [
                ['name' => 'Rare', 'price' => 0],
                ['name' => 'Medium Rare', 'price' => 0],
                ['name' => 'Medium', 'price' => 0],
                ['name' => 'Well Done', 'price' => 0],
            ]],
        ];

        foreach ($modifiers as $m) {
            $modifier = Modifier::firstOrCreate(
                ['restaurant_id' => $restaurantId, 'name' => $m['name']],
                [
                    'tenant_id' => $tenantId,
                    'restaurant_id' => $restaurantId,
                    'name' => $m['name'],
                    'type' => $m['type'],
                    'is_required' => false,
                    'is_active' => true,
                ]
            );

            foreach ($m['options'] as $sortOrder => $opt) {
                ModifierOption::firstOrCreate(
                    ['modifier_id' => $modifier->id, 'name' => $opt['name']],
                    [
                        'tenant_id' => $tenantId,
                        'modifier_id' => $modifier->id,
                        'name' => $opt['name'],
                        'price' => $opt['price'],
                        'is_active' => true,
                        'sort_order' => $sortOrder,
                    ]
                );
            }
        }

        $this->command->info('Modifiers created: ' . count($modifiers));

        // Customers
        $customers = [
            ['name' => 'Иван Петров', 'phone' => '+79001234567', 'email' => 'ivan@test.com'],
            ['name' => 'Мария Сидорова', 'phone' => '+79007654321', 'email' => 'maria@test.com'],
            ['name' => 'Алексей Козлов', 'phone' => '+79009876543', 'email' => 'alex@test.com'],
            ['name' => 'Елена Новикова', 'phone' => '+79005551234', 'email' => 'elena@test.com'],
            ['name' => 'Дмитрий Волков', 'phone' => '+79003334455', 'email' => 'dmitry@test.com'],
            ['name' => 'Анна Белова', 'phone' => '+79002223344', 'email' => 'anna@test.com'],
            ['name' => 'Сергей Морозов', 'phone' => '+79001112233', 'email' => 'sergey@test.com'],
            ['name' => 'Ольга Лебедева', 'phone' => '+79008889900', 'email' => 'olga@test.com'],
        ];

        foreach ($customers as $c) {
            Customer::firstOrCreate(
                ['restaurant_id' => $restaurantId, 'phone' => $c['phone']],
                array_merge($c, [
                    'tenant_id' => $tenantId,
                    'restaurant_id' => $restaurantId,
                ])
            );
        }

        $this->command->info('Customers created: ' . count($customers));
        $this->command->info('Test data seeding completed!');
    }
}
