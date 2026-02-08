<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MenuLabSeeder extends Seeder
{
    /**
     * Seed the application's database with sample restaurant data.
     */
    public function run(): void
    {
        // 1. Создаём ресторан
        $restaurantId = DB::table('restaurants')->insertGetId([
            'name' => 'Клюква Food',
            'slug' => 'klukva-food',
            'address' => 'г. Москва, ул. Пушкина, д. 10',
            'phone' => '+7 (999) 123-45-67',
            'email' => 'info@klukvafood.ru',
            'settings' => json_encode([
                'currency' => 'RUB',
                'timezone' => 'Europe/Moscow',
                'work_hours' => [
                    'mon' => ['10:00', '22:00'],
                    'tue' => ['10:00', '22:00'],
                    'wed' => ['10:00', '22:00'],
                    'thu' => ['10:00', '22:00'],
                    'fri' => ['10:00', '23:00'],
                    'sat' => ['11:00', '23:00'],
                    'sun' => ['11:00', '22:00'],
                ],
                'order_prefix' => 'KF',
                'tax_rate' => 0,
                'service_charge' => 0,
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Создаём пользователей (персонал)
        $staff = [
            [
                'name' => 'Администратор',
                'email' => 'admin@menulab.local',
                'phone' => '+7 (999) 000-00-01',
                'role' => 'admin',
                'pin' => '1234',
            ],
            [
                'name' => 'Анна Официант',
                'email' => 'anna@menulab.local',
                'phone' => '+7 (999) 100-00-01',
                'role' => 'waiter',
                'pin' => '1111',
            ],
            [
                'name' => 'Максим Официант',
                'email' => 'maxim@menulab.local',
                'phone' => '+7 (999) 100-00-02',
                'role' => 'waiter',
                'pin' => '2222',
            ],
            [
                'name' => 'Елена Кассир',
                'email' => 'elena@menulab.local',
                'phone' => '+7 (999) 200-00-01',
                'role' => 'cashier',
                'pin' => '3333',
            ],
            [
                'name' => 'Иван Повар',
                'email' => 'ivan@menulab.local',
                'phone' => '+7 (999) 300-00-01',
                'role' => 'cook',
                'pin' => '4444',
            ],
            [
                'name' => 'Сергей Повар',
                'email' => 'sergey@menulab.local',
                'phone' => '+7 (999) 300-00-02',
                'role' => 'cook',
                'pin' => '5555',
            ],
            [
                'name' => 'Мария Кассирова',
                'email' => 'maria@menulab.local',
                'phone' => '+7 (999) 200-00-02',
                'role' => 'cashier',
                'pin' => '6666',
            ],
        ];

        foreach ($staff as $person) {
            DB::table('users')->insert([
                'restaurant_id' => $restaurantId,
                'name' => $person['name'],
                'email' => $person['email'],
                'phone' => $person['phone'],
                'password' => Hash::make('password'),
                'role' => $person['role'],
                'pin_code' => Hash::make($person['pin']),
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Создаём зоны
        $zones = [
            ['name' => 'Основной зал', 'color' => '#3B82F6', 'sort_order' => 1],
            ['name' => 'VIP зал', 'color' => '#F59E0B', 'sort_order' => 2],
            ['name' => 'Терраса', 'color' => '#10B981', 'sort_order' => 3],
            ['name' => 'Бар', 'color' => '#8B5CF6', 'sort_order' => 4],
        ];

        $zoneIds = [];
        foreach ($zones as $zone) {
            $zoneIds[$zone['name']] = DB::table('zones')->insertGetId([
                'restaurant_id' => $restaurantId,
                'name' => $zone['name'],
                'color' => $zone['color'],
                'sort_order' => $zone['sort_order'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Создаём столы
        $tables = [
            // Основной зал
            ['zone' => 'Основной зал', 'number' => '1', 'seats' => 4, 'x' => 50, 'y' => 50],
            ['zone' => 'Основной зал', 'number' => '2', 'seats' => 4, 'x' => 150, 'y' => 50],
            ['zone' => 'Основной зал', 'number' => '3', 'seats' => 2, 'x' => 250, 'y' => 50],
            ['zone' => 'Основной зал', 'number' => '4', 'seats' => 6, 'x' => 50, 'y' => 150, 'shape' => 'rectangle', 'width' => 120],
            ['zone' => 'Основной зал', 'number' => '5', 'seats' => 4, 'x' => 200, 'y' => 150],
            // VIP
            ['zone' => 'VIP зал', 'number' => 'V1', 'seats' => 6, 'x' => 50, 'y' => 50, 'min_order' => 5000],
            ['zone' => 'VIP зал', 'number' => 'V2', 'seats' => 8, 'x' => 200, 'y' => 50, 'min_order' => 7000],
            // Терраса
            ['zone' => 'Терраса', 'number' => 'T1', 'seats' => 4, 'x' => 50, 'y' => 50],
            ['zone' => 'Терраса', 'number' => 'T2', 'seats' => 4, 'x' => 150, 'y' => 50],
            // Бар
            ['zone' => 'Бар', 'number' => 'B1', 'seats' => 2, 'x' => 50, 'y' => 50, 'shape' => 'round'],
            ['zone' => 'Бар', 'number' => 'B2', 'seats' => 2, 'x' => 130, 'y' => 50, 'shape' => 'round'],
        ];

        foreach ($tables as $table) {
            DB::table('tables')->insert([
                'restaurant_id' => $restaurantId,
                'zone_id' => $zoneIds[$table['zone']],
                'number' => $table['number'],
                'seats' => $table['seats'],
                'min_order' => $table['min_order'] ?? 0,
                'shape' => $table['shape'] ?? 'square',
                'position_x' => $table['x'],
                'position_y' => $table['y'],
                'width' => $table['width'] ?? 80,
                'height' => $table['height'] ?? 80,
                'status' => 'free',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. Создаём категории меню
        $categories = [
            ['name' => 'Завтраки', 'icon' => '🍳', 'color' => '#F59E0B'],
            ['name' => 'Салаты', 'icon' => '🥗', 'color' => '#10B981'],
            ['name' => 'Супы', 'icon' => '🍲', 'color' => '#EF4444'],
            ['name' => 'Горячее', 'icon' => '🍖', 'color' => '#8B5CF6'],
            ['name' => 'Пицца', 'icon' => '🍕', 'color' => '#F97316'],
            ['name' => 'Роллы', 'icon' => '🍣', 'color' => '#EC4899'],
            ['name' => 'Десерты', 'icon' => '🍰', 'color' => '#D946EF'],
            ['name' => 'Напитки', 'icon' => '🥤', 'color' => '#06B6D4'],
            ['name' => 'Алкоголь', 'icon' => '🍷', 'color' => '#6366F1'],
        ];

        $categoryIds = [];
        foreach ($categories as $i => $cat) {
            $categoryIds[$cat['name']] = DB::table('categories')->insertGetId([
                'restaurant_id' => $restaurantId,
                'name' => $cat['name'],
                'slug' => Str::slug($cat['name']),
                'icon' => $cat['icon'],
                'color' => $cat['color'],
                'sort_order' => $i + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 6. Создаём блюда
        $dishes = [
            // Завтраки
            ['cat' => 'Завтраки', 'name' => 'Яичница с беконом', 'price' => 350, 'weight' => 250, 'time' => 15],
            ['cat' => 'Завтраки', 'name' => 'Овсянка с ягодами', 'price' => 280, 'weight' => 300, 'time' => 10, 'vegetarian' => true],
            ['cat' => 'Завтраки', 'name' => 'Блины с творогом', 'price' => 320, 'weight' => 280, 'time' => 15],
            ['cat' => 'Завтраки', 'name' => 'Сырники со сметаной', 'price' => 290, 'weight' => 250, 'time' => 20],
            
            // Салаты
            ['cat' => 'Салаты', 'name' => 'Цезарь с курицей', 'price' => 450, 'weight' => 280, 'time' => 10, 'popular' => true],
            ['cat' => 'Салаты', 'name' => 'Греческий', 'price' => 380, 'weight' => 250, 'time' => 8, 'vegetarian' => true],
            ['cat' => 'Салаты', 'name' => 'Оливье', 'price' => 320, 'weight' => 220, 'time' => 10],
            ['cat' => 'Салаты', 'name' => 'Тёплый салат с говядиной', 'price' => 520, 'weight' => 300, 'time' => 15],
            
            // Супы
            ['cat' => 'Супы', 'name' => 'Борщ украинский', 'price' => 350, 'weight' => 350, 'time' => 10, 'popular' => true],
            ['cat' => 'Супы', 'name' => 'Том Ям с креветками', 'price' => 480, 'weight' => 300, 'time' => 15, 'spicy' => true],
            ['cat' => 'Супы', 'name' => 'Куриный бульон', 'price' => 280, 'weight' => 300, 'time' => 8],
            ['cat' => 'Супы', 'name' => 'Грибной крем-суп', 'price' => 320, 'weight' => 280, 'time' => 10, 'vegetarian' => true],
            
            // Горячее
            ['cat' => 'Горячее', 'name' => 'Стейк Рибай', 'price' => 1450, 'weight' => 300, 'time' => 25, 'popular' => true],
            ['cat' => 'Горячее', 'name' => 'Лосось на гриле', 'price' => 890, 'weight' => 250, 'time' => 20],
            ['cat' => 'Горячее', 'name' => 'Куриная грудка', 'price' => 520, 'weight' => 280, 'time' => 20],
            ['cat' => 'Горячее', 'name' => 'Свиная рулька', 'price' => 980, 'weight' => 800, 'time' => 30],
            ['cat' => 'Горячее', 'name' => 'Паста Карбонара', 'price' => 450, 'weight' => 350, 'time' => 15, 'popular' => true],
            
            // Пицца
            ['cat' => 'Пицца', 'name' => 'Маргарита', 'price' => 490, 'weight' => 500, 'time' => 20, 'vegetarian' => true],
            ['cat' => 'Пицца', 'name' => 'Пепперони', 'price' => 590, 'weight' => 550, 'time' => 20, 'popular' => true, 'spicy' => true],
            ['cat' => 'Пицца', 'name' => '4 сыра', 'price' => 650, 'weight' => 520, 'time' => 20, 'vegetarian' => true],
            ['cat' => 'Пицца', 'name' => 'Мясная', 'price' => 720, 'weight' => 600, 'time' => 25],
            
            // Роллы
            ['cat' => 'Роллы', 'name' => 'Филадельфия', 'price' => 580, 'weight' => 280, 'time' => 15, 'popular' => true],
            ['cat' => 'Роллы', 'name' => 'Калифорния', 'price' => 520, 'weight' => 260, 'time' => 15],
            ['cat' => 'Роллы', 'name' => 'Дракон', 'price' => 650, 'weight' => 300, 'time' => 15],
            ['cat' => 'Роллы', 'name' => 'Спайси лосось', 'price' => 480, 'weight' => 240, 'time' => 12, 'spicy' => true],
            
            // Десерты
            ['cat' => 'Десерты', 'name' => 'Тирамису', 'price' => 380, 'weight' => 180, 'time' => 5, 'popular' => true],
            ['cat' => 'Десерты', 'name' => 'Чизкейк Нью-Йорк', 'price' => 350, 'weight' => 150, 'time' => 5],
            ['cat' => 'Десерты', 'name' => 'Шоколадный фондан', 'price' => 420, 'weight' => 160, 'time' => 15],
            ['cat' => 'Десерты', 'name' => 'Мороженое (3 шарика)', 'price' => 280, 'weight' => 150, 'time' => 3],
            
            // Напитки
            ['cat' => 'Напитки', 'name' => 'Американо', 'price' => 180, 'weight' => 200, 'time' => 3],
            ['cat' => 'Напитки', 'name' => 'Капучино', 'price' => 220, 'weight' => 250, 'time' => 4],
            ['cat' => 'Напитки', 'name' => 'Латте', 'price' => 250, 'weight' => 300, 'time' => 4],
            ['cat' => 'Напитки', 'name' => 'Свежевыжатый апельсин', 'price' => 280, 'weight' => 300, 'time' => 5],
            ['cat' => 'Напитки', 'name' => 'Морс клюквенный', 'price' => 180, 'weight' => 400, 'time' => 2],
            ['cat' => 'Напитки', 'name' => 'Лимонад домашний', 'price' => 220, 'weight' => 400, 'time' => 3],
        ];

        foreach ($dishes as $i => $dish) {
            DB::table('dishes')->insert([
                'restaurant_id' => $restaurantId,
                'category_id' => $categoryIds[$dish['cat']],
                'name' => $dish['name'],
                'slug' => Str::slug($dish['name']),
                'price' => $dish['price'],
                'weight' => $dish['weight'],
                'cooking_time' => $dish['time'],
                'is_available' => true,
                'is_popular' => $dish['popular'] ?? false,
                'is_new' => false,
                'is_spicy' => $dish['spicy'] ?? false,
                'is_vegetarian' => $dish['vegetarian'] ?? false,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 7. Создаём модификаторы для пиццы
        $sizeModifierId = DB::table('modifiers')->insertGetId([
            'restaurant_id' => $restaurantId,
            'name' => 'Размер',
            'type' => 'single',
            'is_required' => true,
            'min_selections' => 1,
            'max_selections' => 1,
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sizes = [
            ['name' => 'Маленькая (25 см)', 'price' => 0, 'default' => true],
            ['name' => 'Средняя (30 см)', 'price' => 100, 'default' => false],
            ['name' => 'Большая (35 см)', 'price' => 200, 'default' => false],
        ];

        foreach ($sizes as $i => $size) {
            DB::table('modifier_options')->insert([
                'modifier_id' => $sizeModifierId,
                'name' => $size['name'],
                'price' => $size['price'],
                'is_default' => $size['default'],
                'sort_order' => $i + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Добавки к пицце
        $toppingsModifierId = DB::table('modifiers')->insertGetId([
            'restaurant_id' => $restaurantId,
            'name' => 'Дополнительные топпинги',
            'type' => 'multiple',
            'is_required' => false,
            'min_selections' => 0,
            'max_selections' => 5,
            'sort_order' => 2,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $toppings = [
            ['name' => 'Дополнительный сыр', 'price' => 80],
            ['name' => 'Пепперони', 'price' => 100],
            ['name' => 'Грибы', 'price' => 60],
            ['name' => 'Оливки', 'price' => 50],
            ['name' => 'Халапеньо', 'price' => 50],
            ['name' => 'Бекон', 'price' => 120],
        ];

        foreach ($toppings as $i => $topping) {
            DB::table('modifier_options')->insert([
                'modifier_id' => $toppingsModifierId,
                'name' => $topping['name'],
                'price' => $topping['price'],
                'is_default' => false,
                'sort_order' => $i + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Привязываем модификаторы к пиццам
        $pizzaDishes = DB::table('dishes')
            ->where('restaurant_id', $restaurantId)
            ->where('category_id', $categoryIds['Пицца'])
            ->pluck('id');

        foreach ($pizzaDishes as $dishId) {
            DB::table('dish_modifier')->insert([
                ['dish_id' => $dishId, 'modifier_id' => $sizeModifierId],
                ['dish_id' => $dishId, 'modifier_id' => $toppingsModifierId],
            ]);
        }

        // 8. Создаём зоны доставки
        $deliveryZones = [
            ['name' => 'Центр (до 3 км)', 'min' => 500, 'fee' => 0, 'time' => 30, 'color' => '#10B981'],
            ['name' => 'Ближняя (3-5 км)', 'min' => 800, 'fee' => 150, 'time' => 45, 'color' => '#F59E0B'],
            ['name' => 'Дальняя (5-10 км)', 'min' => 1200, 'fee' => 300, 'time' => 60, 'color' => '#EF4444'],
        ];

        foreach ($deliveryZones as $i => $zone) {
            DB::table('delivery_zones')->insert([
                'restaurant_id' => $restaurantId,
                'name' => $zone['name'],
                'min_order' => $zone['min'],
                'delivery_fee' => $zone['fee'],
                'delivery_time' => $zone['time'],
                'color' => $zone['color'],
                'sort_order' => $i + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 9. Создаём тестовых клиентов
        $customers = [
            ['name' => 'Иван Петров', 'phone' => '+7 (999) 111-11-11', 'orders' => 15, 'spent' => 12500],
            ['name' => 'Мария Сидорова', 'phone' => '+7 (999) 222-22-22', 'orders' => 8, 'spent' => 6800],
            ['name' => 'Алексей Козлов', 'phone' => '+7 (999) 333-33-33', 'orders' => 23, 'spent' => 28900],
        ];

        foreach ($customers as $customer) {
            DB::table('customers')->insert([
                'restaurant_id' => $restaurantId,
                'name' => $customer['name'],
                'phone' => $customer['phone'],
                'total_orders' => $customer['orders'],
                'total_spent' => $customer['spent'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('');
        $this->command->info('✅ MenuLab demo data seeded successfully!');
        $this->command->info('');
        $this->command->info('📋 Учётные данные персонала:');
        $this->command->info('┌────────────────────┬──────────┬──────┐');
        $this->command->info('│ Имя                │ Роль     │ PIN  │');
        $this->command->info('├────────────────────┼──────────┼──────┤');
        $this->command->info('│ Администратор      │ admin    │ 1234 │');
        $this->command->info('│ Анна Официант      │ waiter   │ 1111 │');
        $this->command->info('│ Максим Официант    │ waiter   │ 2222 │');
        $this->command->info('│ Елена Кассир       │ cashier  │ 3333 │');
        $this->command->info('│ Иван Повар         │ cook     │ 4444 │');
        $this->command->info('│ Сергей Повар       │ cook     │ 5555 │');
        $this->command->info('│ Мария Кассирова    │ cashier  │ 6666 │');
        $this->command->info('└────────────────────┴──────────┴──────┘');
        $this->command->info('');
        $this->command->info('🔐 Пароль для всех: password');
        $this->command->info('📧 Email админа: admin@menulab.local');
    }
}
