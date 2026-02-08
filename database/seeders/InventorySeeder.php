<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $restaurantId = DB::table('restaurants')->value('id');

        if (!$restaurantId) {
            $this->command->error('No restaurant found. Run MenuLabSeeder first.');
            return;
        }

        // 1. Units (system-wide)
        $units = [
            // Weight
            ['name' => 'Килограмм', 'short_name' => 'кг', 'type' => 'weight', 'base_ratio' => 1, 'is_system' => true],
            ['name' => 'Грамм', 'short_name' => 'г', 'type' => 'weight', 'base_ratio' => 0.001, 'is_system' => true],
            // Volume
            ['name' => 'Литр', 'short_name' => 'л', 'type' => 'volume', 'base_ratio' => 1, 'is_system' => true],
            ['name' => 'Миллилитр', 'short_name' => 'мл', 'type' => 'volume', 'base_ratio' => 0.001, 'is_system' => true],
            // Piece
            ['name' => 'Штука', 'short_name' => 'шт', 'type' => 'piece', 'base_ratio' => 1, 'is_system' => true],
            ['name' => 'Порция', 'short_name' => 'порц', 'type' => 'piece', 'base_ratio' => 1, 'is_system' => true],
            // Pack
            ['name' => 'Упаковка', 'short_name' => 'уп', 'type' => 'pack', 'base_ratio' => 1, 'is_system' => true],
            ['name' => 'Бутылка', 'short_name' => 'бут', 'type' => 'piece', 'base_ratio' => 1, 'is_system' => true],
        ];

        $unitIds = [];
        foreach ($units as $unit) {
            // Skip if already exists
            $exists = DB::table('units')
                ->where('short_name', $unit['short_name'])
                ->where(function ($q) use ($restaurantId) {
                    $q->whereNull('restaurant_id')->orWhere('restaurant_id', $restaurantId);
                })
                ->first();

            if ($exists) {
                $unitIds[$unit['short_name']] = $exists->id;
                continue;
            }

            $unitIds[$unit['short_name']] = DB::table('units')->insertGetId([
                'restaurant_id' => null, // system units
                'name' => $unit['name'],
                'short_name' => $unit['short_name'],
                'type' => $unit['type'],
                'base_ratio' => $unit['base_ratio'],
                'is_system' => $unit['is_system'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Units seeded: ' . count($unitIds));

        // 2. Warehouses
        $warehouses = [
            ['name' => 'Основной склад', 'type' => 'main', 'is_default' => true, 'sort_order' => 1],
            ['name' => 'Кухня', 'type' => 'kitchen', 'is_default' => false, 'sort_order' => 2],
            ['name' => 'Бар', 'type' => 'bar', 'is_default' => false, 'sort_order' => 3],
        ];

        $warehouseIds = [];
        foreach ($warehouses as $wh) {
            $exists = DB::table('warehouses')
                ->where('restaurant_id', $restaurantId)
                ->where('name', $wh['name'])
                ->first();

            if ($exists) {
                $warehouseIds[$wh['name']] = $exists->id;
                continue;
            }

            $warehouseIds[$wh['name']] = DB::table('warehouses')->insertGetId([
                'restaurant_id' => $restaurantId,
                'name' => $wh['name'],
                'type' => $wh['type'],
                'is_default' => $wh['is_default'],
                'is_active' => true,
                'sort_order' => $wh['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Warehouses seeded: ' . count($warehouseIds));

        // 3. Ingredient categories
        $categories = [
            ['name' => 'Мясо и птица', 'sort_order' => 1],
            ['name' => 'Рыба и морепродукты', 'sort_order' => 2],
            ['name' => 'Овощи и зелень', 'sort_order' => 3],
            ['name' => 'Молочные продукты', 'sort_order' => 4],
            ['name' => 'Бакалея', 'sort_order' => 5],
            ['name' => 'Напитки', 'sort_order' => 6],
            ['name' => 'Алкоголь', 'sort_order' => 7],
            ['name' => 'Специи и соусы', 'sort_order' => 8],
        ];

        $catIds = [];
        foreach ($categories as $cat) {
            $exists = DB::table('ingredient_categories')
                ->where('restaurant_id', $restaurantId)
                ->where('name', $cat['name'])
                ->first();

            if ($exists) {
                $catIds[$cat['name']] = $exists->id;
                continue;
            }

            $catIds[$cat['name']] = DB::table('ingredient_categories')->insertGetId([
                'restaurant_id' => $restaurantId,
                'name' => $cat['name'],
                'sort_order' => $cat['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Ingredient categories seeded: ' . count($catIds));

        // 4. Ingredients
        $ingredients = [
            // Мясо
            ['name' => 'Говядина (вырезка)', 'cat' => 'Мясо и птица', 'unit' => 'кг', 'cost' => 1200, 'min' => 5, 'cold_loss' => 3, 'hot_loss' => 25],
            ['name' => 'Куриная грудка', 'cat' => 'Мясо и птица', 'unit' => 'кг', 'cost' => 350, 'min' => 8, 'cold_loss' => 5, 'hot_loss' => 20],
            ['name' => 'Свинина', 'cat' => 'Мясо и птица', 'unit' => 'кг', 'cost' => 550, 'min' => 5, 'cold_loss' => 4, 'hot_loss' => 30],
            ['name' => 'Бекон', 'cat' => 'Мясо и птица', 'unit' => 'кг', 'cost' => 800, 'min' => 2],
            ['name' => 'Пепперони (колбаса)', 'cat' => 'Мясо и птица', 'unit' => 'кг', 'cost' => 900, 'min' => 2],

            // Рыба
            ['name' => 'Лосось (филе)', 'cat' => 'Рыба и морепродукты', 'unit' => 'кг', 'cost' => 1800, 'min' => 3, 'cold_loss' => 5, 'hot_loss' => 15],
            ['name' => 'Креветки тигровые', 'cat' => 'Рыба и морепродукты', 'unit' => 'кг', 'cost' => 1500, 'min' => 2, 'cold_loss' => 30],

            // Овощи
            ['name' => 'Помидоры', 'cat' => 'Овощи и зелень', 'unit' => 'кг', 'cost' => 180, 'min' => 5, 'cold_loss' => 5],
            ['name' => 'Огурцы', 'cat' => 'Овощи и зелень', 'unit' => 'кг', 'cost' => 120, 'min' => 3, 'cold_loss' => 3],
            ['name' => 'Салат Айсберг', 'cat' => 'Овощи и зелень', 'unit' => 'кг', 'cost' => 200, 'min' => 3, 'cold_loss' => 15],
            ['name' => 'Лук репчатый', 'cat' => 'Овощи и зелень', 'unit' => 'кг', 'cost' => 40, 'min' => 5, 'cold_loss' => 16],
            ['name' => 'Картофель', 'cat' => 'Овощи и зелень', 'unit' => 'кг', 'cost' => 35, 'min' => 10, 'cold_loss' => 20, 'hot_loss' => 3],
            ['name' => 'Грибы шампиньоны', 'cat' => 'Овощи и зелень', 'unit' => 'кг', 'cost' => 250, 'min' => 3, 'cold_loss' => 5, 'hot_loss' => 25],

            // Молочка
            ['name' => 'Молоко 3.2%', 'cat' => 'Молочные продукты', 'unit' => 'л', 'cost' => 80, 'min' => 10],
            ['name' => 'Сливки 33%', 'cat' => 'Молочные продукты', 'unit' => 'л', 'cost' => 350, 'min' => 5],
            ['name' => 'Сыр Моцарелла', 'cat' => 'Молочные продукты', 'unit' => 'кг', 'cost' => 700, 'min' => 3],
            ['name' => 'Сыр Пармезан', 'cat' => 'Молочные продукты', 'unit' => 'кг', 'cost' => 1500, 'min' => 1],
            ['name' => 'Масло сливочное', 'cat' => 'Молочные продукты', 'unit' => 'кг', 'cost' => 600, 'min' => 2],
            ['name' => 'Сметана 20%', 'cat' => 'Молочные продукты', 'unit' => 'кг', 'cost' => 180, 'min' => 3],
            ['name' => 'Творог 9%', 'cat' => 'Молочные продукты', 'unit' => 'кг', 'cost' => 250, 'min' => 3],

            // Бакалея
            ['name' => 'Мука пшеничная', 'cat' => 'Бакалея', 'unit' => 'кг', 'cost' => 50, 'min' => 10],
            ['name' => 'Рис', 'cat' => 'Бакалея', 'unit' => 'кг', 'cost' => 90, 'min' => 5],
            ['name' => 'Спагетти', 'cat' => 'Бакалея', 'unit' => 'кг', 'cost' => 120, 'min' => 5],
            ['name' => 'Масло оливковое', 'cat' => 'Бакалея', 'unit' => 'л', 'cost' => 600, 'min' => 3],
            ['name' => 'Яйцо куриное', 'cat' => 'Бакалея', 'unit' => 'шт', 'cost' => 12, 'min' => 60],
            ['name' => 'Сахар', 'cat' => 'Бакалея', 'unit' => 'кг', 'cost' => 55, 'min' => 5],

            // Напитки
            ['name' => 'Кофе зерновой', 'cat' => 'Напитки', 'unit' => 'кг', 'cost' => 2000, 'min' => 3],
            ['name' => 'Чай чёрный (пачка)', 'cat' => 'Напитки', 'unit' => 'шт', 'cost' => 150, 'min' => 10],
            ['name' => 'Апельсины (для сока)', 'cat' => 'Напитки', 'unit' => 'кг', 'cost' => 120, 'min' => 10, 'cold_loss' => 50],
            ['name' => 'Клюква (для морса)', 'cat' => 'Напитки', 'unit' => 'кг', 'cost' => 350, 'min' => 3],

            // Алкоголь
            ['name' => 'Вино красное (дом.)', 'cat' => 'Алкоголь', 'unit' => 'бут', 'cost' => 500, 'min' => 10],
            ['name' => 'Вино белое (дом.)', 'cat' => 'Алкоголь', 'unit' => 'бут', 'cost' => 450, 'min' => 10],
            ['name' => 'Пиво светлое (разл.)', 'cat' => 'Алкоголь', 'unit' => 'л', 'cost' => 200, 'min' => 20],

            // Специи
            ['name' => 'Соль', 'cat' => 'Специи и соусы', 'unit' => 'кг', 'cost' => 20, 'min' => 3],
            ['name' => 'Перец чёрный молотый', 'cat' => 'Специи и соусы', 'unit' => 'кг', 'cost' => 800, 'min' => 0.5],
            ['name' => 'Соус соевый', 'cat' => 'Специи и соусы', 'unit' => 'л', 'cost' => 250, 'min' => 2],
            ['name' => 'Майонез', 'cat' => 'Специи и соусы', 'unit' => 'кг', 'cost' => 150, 'min' => 3],
        ];

        $mainWarehouseId = $warehouseIds['Основной склад'];
        $ingredientCount = 0;

        foreach ($ingredients as $ing) {
            $exists = DB::table('ingredients')
                ->where('restaurant_id', $restaurantId)
                ->where('name', $ing['name'])
                ->first();

            if ($exists) {
                $ingredientCount++;
                continue;
            }

            $ingredientId = DB::table('ingredients')->insertGetId([
                'restaurant_id' => $restaurantId,
                'category_id' => $catIds[$ing['cat']],
                'unit_id' => $unitIds[$ing['unit']],
                'name' => $ing['name'],
                'cost_price' => $ing['cost'],
                'min_stock' => $ing['min'],
                'cold_loss_percent' => $ing['cold_loss'] ?? 0,
                'hot_loss_percent' => $ing['hot_loss'] ?? 0,
                'track_stock' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Initial stock on main warehouse (random realistic amounts)
            $initialQty = $ing['min'] * (1.5 + (mt_rand(0, 30) / 10)); // 1.5x-4.5x of min
            DB::table('ingredient_stocks')->insert([
                'warehouse_id' => $mainWarehouseId,
                'ingredient_id' => $ingredientId,
                'quantity' => round($initialQty, 2),
                'reserved' => 0,
                'avg_cost' => $ing['cost'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ingredientCount++;
        }

        $this->command->info('Ingredients seeded: ' . $ingredientCount);

        // 5. Supplier
        $supplierExists = DB::table('suppliers')
            ->where('restaurant_id', $restaurantId)
            ->exists();

        if (!$supplierExists) {
            DB::table('suppliers')->insert([
                [
                    'restaurant_id' => $restaurantId,
                    'name' => 'ООО "Продторг"',
                    'contact_person' => 'Сергей Иванов',
                    'phone' => '+7 (495) 111-22-33',
                    'email' => 'sergey@prodtorg.ru',
                    'inn' => '7701234567',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'restaurant_id' => $restaurantId,
                    'name' => 'ИП Петров (Фермер)',
                    'contact_person' => 'Алексей Петров',
                    'phone' => '+7 (916) 333-44-55',
                    'delivery_days' => 2,
                    'min_order_amount' => 5000,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'restaurant_id' => $restaurantId,
                    'name' => 'Море Рыбы',
                    'contact_person' => 'Дмитрий',
                    'phone' => '+7 (495) 777-88-99',
                    'email' => 'order@morefish.ru',
                    'delivery_days' => 1,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
            $this->command->info('Suppliers seeded: 3');
        }

        $this->command->newLine();
        $this->command->info('Inventory data seeded successfully!');
        $this->command->info("Restaurant: {$restaurantId}");
        $this->command->info('Units: ' . count($unitIds));
        $this->command->info('Warehouses: ' . count($warehouseIds));
        $this->command->info('Categories: ' . count($catIds));
        $this->command->info('Ingredients: ' . $ingredientCount);
    }
}
