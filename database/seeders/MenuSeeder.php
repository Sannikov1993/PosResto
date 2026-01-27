<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Dish;

class MenuSeeder extends Seeder
{
    private function makeSlug(string $name): string
    {
        return Str::slug($name, '-');
    }

    public function run(): void
    {
        $restaurantId = 1;

        // =============================================
        // КАТЕГОРИИ
        // =============================================

        $categories = [
            // Кафе Клюква
            ['name' => 'Пицца', 'slug' => 'pizza', 'icon' => '🍕', 'sort_order' => 1],
            ['name' => 'Бургеры', 'slug' => 'burgers', 'icon' => '🍔', 'sort_order' => 2],
            ['name' => 'Закуски', 'slug' => 'snacks', 'icon' => '🍟', 'sort_order' => 3],
            ['name' => 'Паста', 'slug' => 'pasta', 'icon' => '🍝', 'sort_order' => 4],
            ['name' => 'Салаты', 'slug' => 'salads', 'icon' => '🥗', 'sort_order' => 5],
            // Nefrit Rolls
            ['name' => 'Роллы', 'slug' => 'rolls', 'icon' => '🍣', 'sort_order' => 6],
            ['name' => 'Сеты', 'slug' => 'sets', 'icon' => '🍱', 'sort_order' => 7],
            ['name' => 'Суши', 'slug' => 'sushi', 'icon' => '🍙', 'sort_order' => 8],
            ['name' => 'Супы', 'slug' => 'soups', 'icon' => '🍜', 'sort_order' => 9],
            ['name' => 'Горячее', 'slug' => 'hot-dishes', 'icon' => '🥘', 'sort_order' => 10],
            // Общие
            ['name' => 'Десерты', 'slug' => 'desserts', 'icon' => '🍰', 'sort_order' => 11],
            ['name' => 'Напитки', 'slug' => 'drinks', 'icon' => '🥤', 'sort_order' => 12],
            ['name' => 'Соусы', 'slug' => 'sauces', 'icon' => '🥫', 'sort_order' => 13],
            ['name' => 'Комбо', 'slug' => 'combo', 'icon' => '🎁', 'sort_order' => 14],
        ];

        $categoryIds = [];
        foreach ($categories as $cat) {
            $category = Category::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => $cat['slug']],
                ['name' => $cat['name'], 'icon' => $cat['icon'], 'sort_order' => $cat['sort_order'], 'is_active' => true]
            );
            $categoryIds[$cat['name']] = $category->id;
        }

        // =============================================
        // ПИЦЦА (с вариантами размеров)
        // =============================================

        $pizzas = [
            ['name' => 'Пепперони ХИТ', 'description' => 'Томатный соус, моцарелла, пепперони', 'prices' => [499, 699, 899]],
            ['name' => 'Маргарита', 'description' => 'Томатный соус, моцарелла, томаты, базилик', 'prices' => [479, 649, 849]],
            ['name' => 'Четыре сыра', 'description' => 'Моцарелла, дор блю, пармезан, чеддер', 'prices' => [619, 799, 999]],
            ['name' => 'Ветчина и грибы', 'description' => 'Томатный соус, моцарелла, ветчина, шампиньоны', 'prices' => [499, 679, 879]],
            ['name' => 'Гавайская', 'description' => 'Томатный соус, моцарелла, курица, ананасы', 'prices' => [499, 679, 879]],
            ['name' => 'Карбонара', 'description' => 'Сливочный соус, моцарелла, бекон, пармезан', 'prices' => [559, 739, 939]],
            ['name' => 'Мексиканская', 'description' => 'Томатный соус, моцарелла, фарш, халапеньо, фасоль', 'prices' => [539, 719, 919]],
            ['name' => 'Мясная', 'description' => 'Томатный соус, моцарелла, бекон, ветчина, курица, фарш', 'prices' => [629, 829, 1029]],
            ['name' => 'Морская', 'description' => 'Сливочный соус, моцарелла, креветки, мидии, кальмары', 'prices' => [599, 799, 999]],
            ['name' => 'Диабло', 'description' => 'Острый томатный соус, моцарелла, пепперони, халапеньо', 'prices' => [529, 709, 909], 'is_spicy' => true],
            ['name' => 'Цыпленок барбекю', 'description' => 'Соус барбекю, моцарелла, курица, бекон, лук', 'prices' => [499, 679, 879]],
            ['name' => 'Двойная пепперони', 'description' => 'Томатный соус, двойная моцарелла, двойная пепперони', 'prices' => [549, 749, 949], 'is_popular' => true],
        ];

        $sizes = ['25 см', '30 см', '35 см'];

        foreach ($pizzas as $index => $pizza) {
            $slug = $this->makeSlug($pizza['name']);
            // Создаём родительский товар
            $parent = Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => $slug],
                [
                    'name' => $pizza['name'],
                    'category_id' => $categoryIds['Пицца'],
                    'product_type' => 'parent',
                    'description' => $pizza['description'],
                    'price' => $pizza['prices'][0], // минимальная цена
                    'is_available' => true,
                    'is_popular' => $pizza['is_popular'] ?? false,
                    'is_spicy' => $pizza['is_spicy'] ?? false,
                    'sort_order' => $index + 1,
                ]
            );

            // Создаём варианты
            foreach ($sizes as $sizeIndex => $size) {
                $variantSlug = $slug . '-' . $this->makeSlug($size);
                Dish::updateOrCreate(
                    ['restaurant_id' => $restaurantId, 'slug' => $variantSlug],
                    [
                        'parent_id' => $parent->id,
                        'variant_name' => $size,
                        'product_type' => 'variant',
                        'name' => $pizza['name'],
                        'category_id' => $categoryIds['Пицца'],
                        'price' => $pizza['prices'][$sizeIndex],
                        'is_available' => true,
                        'variant_sort' => $sizeIndex + 1,
                    ]
                );
            }
        }

        // =============================================
        // БУРГЕРЫ
        // =============================================

        $burgers = [
            ['name' => 'Бургер классический', 'description' => 'Говяжья котлета, сыр чеддер, салат, томат, соус', 'price' => 329, 'weight' => 250],
            ['name' => 'Чикен бургер', 'description' => 'Куриная котлета, сыр, салат, томат, майонез', 'price' => 329, 'weight' => 250],
            ['name' => 'Бургер острый', 'description' => 'Говяжья котлета, халапеньо, острый соус, сыр', 'price' => 329, 'weight' => 250, 'is_spicy' => true],
            ['name' => 'Бургер BBQ', 'description' => 'Говяжья котлета, бекон, лук фри, соус барбекю', 'price' => 299, 'weight' => 250],
            ['name' => 'Чизбургер', 'description' => 'Говяжья котлета, двойной сыр, маринованные огурцы', 'price' => 329, 'weight' => 220],
        ];

        foreach ($burgers as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => $this->makeSlug($item['name'])],
                [
                    'name' => $item['name'],
                    'category_id' => $categoryIds['Бургеры'],
                    'product_type' => 'simple',
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'weight' => $item['weight'],
                    'is_available' => true,
                    'is_spicy' => $item['is_spicy'] ?? false,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // =============================================
        // ЗАКУСКИ
        // =============================================

        $snacks = [
            ['name' => 'Картофель фри', 'description' => 'Хрустящий картофель фри', 'price' => 199, 'weight' => 150],
            ['name' => 'Стрипсы куриные', 'description' => 'Куриные стрипсы в панировке', 'price' => 279, 'weight' => 170],
            ['name' => 'Наггетсы', 'description' => 'Куриные наггетсы 8 шт', 'price' => 199, 'weight' => 120],
            ['name' => 'Чикен ролл', 'description' => 'Тортилья, курица, овощи, соус', 'price' => 259, 'weight' => 220],
            ['name' => 'Луковые кольца', 'description' => 'Хрустящие луковые кольца в кляре', 'price' => 189, 'weight' => 120],
            ['name' => 'Сырные палочки', 'description' => 'Моцарелла в панировке', 'price' => 249, 'weight' => 150],
        ];

        foreach ($snacks as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => $this->makeSlug($item['name'])],
                [
                    'name' => $item['name'],
                    'category_id' => $categoryIds['Закуски'],
                    'product_type' => 'simple',
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'weight' => $item['weight'],
                    'is_available' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // =============================================
        // САЛАТЫ
        // =============================================

        $salads = [
            ['name' => 'Цезарь с курицей', 'description' => 'Романо, курица, пармезан, сухарики, соус цезарь', 'price' => 379, 'weight' => 250],
            ['name' => 'Цезарь с креветками', 'description' => 'Романо, креветки, пармезан, сухарики, соус цезарь', 'price' => 429, 'weight' => 250],
            ['name' => 'Греческий', 'description' => 'Томаты, огурцы, перец, маслины, фета', 'price' => 329, 'weight' => 230, 'is_vegetarian' => true],
            ['name' => 'Овощной', 'description' => 'Свежие сезонные овощи', 'price' => 249, 'weight' => 200, 'is_vegetarian' => true, 'is_vegan' => true],
        ];

        foreach ($salads as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => $this->makeSlug($item['name'])],
                [
                    'name' => $item['name'],
                    'category_id' => $categoryIds['Салаты'],
                    'product_type' => 'simple',
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'weight' => $item['weight'],
                    'is_available' => true,
                    'is_vegetarian' => $item['is_vegetarian'] ?? false,
                    'is_vegan' => $item['is_vegan'] ?? false,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // =============================================
        // ПАСТА
        // =============================================

        $pasta = [
            ['name' => 'Карбонара', 'description' => 'Спагетти, бекон, пармезан, сливочный соус, желток', 'price' => 399, 'weight' => 300],
            ['name' => 'Паста с курицей и грибами', 'description' => 'Пенне, курица, шампиньоны, сливочный соус', 'price' => 399, 'weight' => 300],
            ['name' => 'Паста с креветками', 'description' => 'Спагетти, креветки, чеснок, белое вино', 'price' => 429, 'weight' => 300],
            ['name' => 'Болоньезе', 'description' => 'Спагетти, мясной соус, пармезан', 'price' => 379, 'weight' => 320],
        ];

        foreach ($pasta as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => $this->makeSlug('pasta-' . $item['name'])],
                [
                    'name' => $item['name'],
                    'category_id' => $categoryIds['Паста'],
                    'product_type' => 'simple',
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'weight' => $item['weight'],
                    'is_available' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // =============================================
        // РОЛЛЫ
        // =============================================

        $rolls = [
            ['name' => 'Филадельфия классик', 'description' => 'Лосось, сливочный сыр, огурец, нори', 'price' => 459, 'weight' => 230, 'is_popular' => true],
            ['name' => 'Филадельфия делюкс', 'description' => 'Лосось, сливочный сыр, авокадо, икра', 'price' => 549, 'weight' => 250],
            ['name' => 'Калифорния', 'description' => 'Краб, авокадо, огурец, икра тобико', 'price' => 399, 'weight' => 220],
            ['name' => 'Калифорния с лососем', 'description' => 'Лосось, авокадо, огурец, икра тобико', 'price' => 449, 'weight' => 220],
            ['name' => 'Дракон', 'description' => 'Угорь, огурец, авокадо сверху, унаги соус', 'price' => 529, 'weight' => 240],
            ['name' => 'Канада', 'description' => 'Лосось, угорь, сливочный сыр, огурец', 'price' => 489, 'weight' => 230],
            ['name' => 'Бонито', 'description' => 'Лосось, сливочный сыр, стружка тунца', 'price' => 429, 'weight' => 210],
            ['name' => 'Ролл с тунцом', 'description' => 'Тунец, сливочный сыр, огурец', 'price' => 419, 'weight' => 200],
            ['name' => 'Ролл с креветкой', 'description' => 'Креветка, сливочный сыр, авокадо', 'price' => 439, 'weight' => 210],
            ['name' => 'Цезарь ролл', 'description' => 'Курица, пармезан, салат, соус цезарь, темпура', 'price' => 389, 'weight' => 220],
            ['name' => 'Темпурный с лососем', 'description' => 'Лосось, сливочный сыр, в темпуре', 'price' => 469, 'weight' => 240],
            ['name' => 'Запечённый с лососем', 'description' => 'Лосось, сливочный сыр, спайси соус, запечённый', 'price' => 449, 'weight' => 230],
            ['name' => 'Запечённый с угрём', 'description' => 'Угорь, сливочный сыр, спайси соус, запечённый', 'price' => 489, 'weight' => 230],
            ['name' => 'Горячий с креветкой', 'description' => 'Креветка, сливочный сыр, темпура', 'price' => 459, 'weight' => 220],
        ];

        foreach ($rolls as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => $this->makeSlug($item['name'])],
                [
                    'name' => $item['name'],
                    'category_id' => $categoryIds['Роллы'],
                    'product_type' => 'simple',
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'weight' => $item['weight'],
                    'is_available' => true,
                    'is_popular' => $item['is_popular'] ?? false,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // =============================================
        // СЕТЫ
        // =============================================

        $sets = [
            ['name' => 'Сет Созвездие', 'description' => 'Япоша, с тунцом, филка делюкс, с лососем терияки', 'price' => 1290, 'weight' => 715],
            ['name' => 'Сет Остин', 'description' => 'Япоша, с лососем и огурцом, сливочный с креветкой, филка делюкс', 'price' => 1390, 'weight' => 720],
            ['name' => 'Сет Миссисипи', 'description' => 'Запеч. мини с лососем, филадельфия делюкс, цезарь фрай, горячий с тунцом', 'price' => 1590, 'weight' => 820],
            ['name' => 'Сет Хиросима', 'description' => 'Филадельфия делюкс, с лососем терияки, под чукой, калифорния лайт', 'price' => 1750, 'weight' => 1010],
            ['name' => 'Сет Вавилон', 'description' => 'Горячий с сыром, цезарь фрай, горячий с семгой, нефрит фрай, канни хотто', 'price' => 1990, 'weight' => 1290],
            ['name' => 'Сет Барс', 'description' => 'Эби-крим, с авокадо, бонито, филадельфия классик, калифорния с лососем', 'price' => 2350, 'weight' => 1220],
            ['name' => 'Сет Филадельфия микс', 'description' => 'Разные виды филадельфии: классик, делюкс, нефрит, под авокадо', 'price' => 3490, 'weight' => 1610, 'is_popular' => true],
            ['name' => 'Сет Веган', 'description' => 'С огурцом, с авокадо, с чукой, суши с авокадо', 'price' => 730, 'weight' => 395, 'is_vegan' => true],
            ['name' => 'Сет Классический', 'description' => 'С угрем и авокадо, лосось с огурцом, с креветкой, с чукой', 'price' => 990, 'weight' => 430],
        ];

        foreach ($sets as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => $this->makeSlug($item['name'])],
                [
                    'name' => $item['name'],
                    'category_id' => $categoryIds['Сеты'],
                    'product_type' => 'simple',
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'weight' => $item['weight'],
                    'is_available' => true,
                    'is_popular' => $item['is_popular'] ?? false,
                    'is_vegan' => $item['is_vegan'] ?? false,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // =============================================
        // СУПЫ
        // =============================================

        $soups = [
            ['name' => 'Мисо суп', 'description' => 'Классический японский суп с тофу, вакаме, зелёным луком', 'price' => 199, 'weight' => 300],
            ['name' => 'Том Ям', 'description' => 'Острый тайский суп с креветками, грибами, кокосовым молоком', 'price' => 399, 'weight' => 350, 'is_spicy' => true],
            ['name' => 'Рамен с курицей', 'description' => 'Японский суп с лапшой, курицей, яйцом, нори', 'price' => 379, 'weight' => 400],
            ['name' => 'Фо Бо', 'description' => 'Вьетнамский суп с говядиной и рисовой лапшой', 'price' => 389, 'weight' => 400],
        ];

        foreach ($soups as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => $this->makeSlug($item['name'])],
                [
                    'name' => $item['name'],
                    'category_id' => $categoryIds['Супы'],
                    'product_type' => 'simple',
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'weight' => $item['weight'],
                    'is_available' => true,
                    'is_spicy' => $item['is_spicy'] ?? false,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // =============================================
        // ГОРЯЧЕЕ (WOK)
        // =============================================

        $hot = [
            ['name' => 'Вок с курицей', 'description' => 'Лапша удон, курица, овощи, соус терияки', 'price' => 349, 'weight' => 350],
            ['name' => 'Вок с говядиной', 'description' => 'Лапша удон, говядина, овощи, соус терияки', 'price' => 389, 'weight' => 350],
            ['name' => 'Вок с креветками', 'description' => 'Лапша удон, креветки, овощи, соус терияки', 'price' => 419, 'weight' => 350],
            ['name' => 'Вок овощной', 'description' => 'Лапша удон, овощи микс, соус терияки', 'price' => 299, 'weight' => 320, 'is_vegetarian' => true],
            ['name' => 'Рис с курицей', 'description' => 'Жареный рис, курица, яйцо, овощи', 'price' => 329, 'weight' => 300],
            ['name' => 'Рис с морепродуктами', 'description' => 'Жареный рис, креветки, кальмар, мидии', 'price' => 399, 'weight' => 300],
        ];

        foreach ($hot as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => $this->makeSlug($item['name'])],
                [
                    'name' => $item['name'],
                    'category_id' => $categoryIds['Горячее'],
                    'product_type' => 'simple',
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'weight' => $item['weight'],
                    'is_available' => true,
                    'is_vegetarian' => $item['is_vegetarian'] ?? false,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // =============================================
        // ДЕСЕРТЫ
        // =============================================

        $desserts = [
            ['name' => 'Чизкейк Нью-Йорк', 'description' => 'Классический американский чизкейк', 'price' => 199, 'weight' => 120],
            ['name' => 'Тирамису', 'description' => 'Итальянский десерт с маскарпоне и кофе', 'price' => 229, 'weight' => 130],
            ['name' => 'Шоколадный фондан', 'description' => 'Горячий шоколадный кекс с жидким центром', 'price' => 249, 'weight' => 110],
            ['name' => 'Рулетики с ананасами', 'description' => 'Сладкие роллы с ананасом 8 шт', 'price' => 249, 'weight' => 180],
            ['name' => 'Рулетики с клюквой', 'description' => 'Сладкие роллы с клюквой 8 шт', 'price' => 249, 'weight' => 180],
            ['name' => 'Моти', 'description' => 'Японский рисовый десерт с мороженым', 'price' => 149, 'weight' => 90],
        ];

        foreach ($desserts as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => $this->makeSlug($item['name'])],
                [
                    'name' => $item['name'],
                    'category_id' => $categoryIds['Десерты'],
                    'product_type' => 'simple',
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'weight' => $item['weight'],
                    'is_available' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // =============================================
        // НАПИТКИ
        // =============================================

        $drinks = [
            ['name' => 'Coca-Cola 0.5л', 'description' => '', 'price' => 120, 'weight' => 500],
            ['name' => 'Coca-Cola 1л', 'description' => '', 'price' => 180, 'weight' => 1000],
            ['name' => 'Sprite 0.5л', 'description' => '', 'price' => 120, 'weight' => 500],
            ['name' => 'Sprite 1л', 'description' => '', 'price' => 180, 'weight' => 1000],
            ['name' => 'Сок яблочный 1л', 'description' => '', 'price' => 220, 'weight' => 1000],
            ['name' => 'Сок апельсиновый 1л', 'description' => '', 'price' => 220, 'weight' => 1000],
            ['name' => 'Морс клюквенный 0.33л', 'description' => '', 'price' => 75, 'weight' => 330],
            ['name' => 'Вода без газа 0.5л', 'description' => '', 'price' => 79, 'weight' => 500],
            ['name' => 'Вода с газом 0.5л', 'description' => '', 'price' => 79, 'weight' => 500],
        ];

        foreach ($drinks as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => $this->makeSlug($item['name'])],
                [
                    'name' => $item['name'],
                    'category_id' => $categoryIds['Напитки'],
                    'product_type' => 'simple',
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'weight' => $item['weight'],
                    'is_available' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // =============================================
        // СОУСЫ
        // =============================================

        $sauces = [
            ['name' => 'Соус соевый', 'price' => 39],
            ['name' => 'Соус спайси', 'price' => 49],
            ['name' => 'Соус унаги', 'price' => 49],
            ['name' => 'Соус терияки', 'price' => 49],
            ['name' => 'Соус барбекю', 'price' => 50],
            ['name' => 'Соус сырный', 'price' => 50],
            ['name' => 'Соус чесночный', 'price' => 50],
            ['name' => 'Соус цезарь', 'price' => 50],
            ['name' => 'Имбирь маринованный', 'price' => 49],
            ['name' => 'Васаби', 'price' => 39],
        ];

        foreach ($sauces as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => $this->makeSlug($item['name'])],
                [
                    'name' => $item['name'],
                    'category_id' => $categoryIds['Соусы'],
                    'product_type' => 'simple',
                    'price' => $item['price'],
                    'weight' => 30,
                    'is_available' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // =============================================
        // КОМБО
        // =============================================

        $combos = [
            ['name' => 'Комбо Классика', 'description' => 'Пицца 30см + картофель фри + напиток', 'price' => 759, 'weight' => 880],
            ['name' => '2 пиццы 30см', 'description' => 'Любые 2 пиццы 30см на выбор', 'price' => 990, 'weight' => 1380],
            ['name' => 'Три хита', 'description' => 'Пепперони + Маргарита + Четыре сыра 30см', 'price' => 1499, 'weight' => 1920],
            ['name' => 'Пять пицц', 'description' => '5 пицц 25см на выбор', 'price' => 2599, 'weight' => 3160],
            ['name' => 'Комбо Пикник', 'description' => 'Пицца + бургер + картофель + напиток', 'price' => 950, 'weight' => 870],
        ];

        foreach ($combos as $index => $item) {
            Dish::updateOrCreate(
                ['restaurant_id' => $restaurantId, 'slug' => $this->makeSlug($item['name'])],
                [
                    'name' => $item['name'],
                    'category_id' => $categoryIds['Комбо'],
                    'product_type' => 'simple',
                    'description' => $item['description'],
                    'price' => $item['price'],
                    'weight' => $item['weight'],
                    'is_available' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }

        $this->command->info('Меню успешно заполнено!');
        $this->command->info('Категорий: ' . count($categories));
        $this->command->info('Блюд: ' . Dish::where('restaurant_id', $restaurantId)->count());
    }
}
