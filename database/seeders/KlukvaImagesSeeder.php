<?php

namespace Database\Seeders;

use App\Models\Dish;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class KlukvaImagesSeeder extends Seeder
{
    private $baseUrl = 'https://klukvafood.ru/';

    // Маппинг названий товаров на URL изображений
    private $images = [
        // Пиццы
        'Пепперони' => 'assets/images/product_694f03d031f87.webp',
        'Маргарита' => 'assets/images/product_694f05826fd70.webp',
        'Сырная' => 'assets/images/product_694f064642082.webp',
        'Ветчина и сыр' => 'assets/images/product_694f059ec1619.webp',
        'Ветчина и грибы' => 'assets/images/product_694f04bc9c072.webp',
        'Четыре сыра' => 'assets/images/product_694f0636b7b8a.webp',
        'Морская' => 'assets/images/product_694f06109e8de.webp',
        'Мясная' => 'assets/images/product_694f03e83adc0.webp',
        'Гавайская' => 'assets/images/product_694f045f0c2d1.webp',
        'Мексиканская' => 'assets/images/product_694f04038424a.webp',

        // Комбо
        'Комбо Классика' => 'assets/images/product_694f01321b70b.webp',
        'Комбо 2 пиццы 30см' => 'assets/images/product_694f0117a8bf6.webp',
        'Комбо Три хита' => 'assets/images/product_694f018fdc226.webp',
        'Комбо Пять пицц' => 'assets/images/product_694f00e4079d6.webp',

        // Закуски
        'Картофель фри' => 'assets/images/product_694eff630410b.webp',
        'Стрипсы куриные' => 'assets/images/product_694eff3f085d7.webp',
        'Наггетсы' => 'assets/images/product_694eff1578d9a.webp',
        'Салат Цезарь с креветками' => 'assets/images/product_694efeebdff31.webp',
        'Салат Цезарь с курицей' => 'assets/images/product_694efe7952fec.webp',
        'Паста Карбонара' => 'assets/images/product_694efdd10496e.webp',

        // Бургеры
        'Чикен бургер' => 'assets/images/product_694f003d5dea5.webp',
        'Классический бургер' => 'assets/images/product_694f0020e2c4b.webp',
        'Острый бургер' => 'assets/images/product_694f000c8bbcb.webp',
        'BBQ бургер' => 'assets/images/product_694efff8ef0a8.webp',

        // Напитки
        'Coca-Cola' => 'assets/images/product_694efd75c17a0.webp',
        'Морс клюквенный' => 'assets/images/product_694efd8d0449b.webp',
        'Сок яблочный' => 'assets/images/product_694efd18c7690.webp',
        'Сок апельсиновый' => 'assets/images/product_694efcf30a2a9.webp',

        // Десерты
        'Рулетики с ананасами' => 'assets/images/product_6952d5d4151ae.webp',
        'Рулетики с клюквой' => 'assets/images/product_6952d6303bbe3.webp',
        'Рулетики с сыром' => 'assets/images/product_6952d6623d4bb.webp',
        'Чизкейк Нью-Йорк' => 'assets/images/product_6952d6a514c9c.webp',

        // Соусы
        'Соус Пицца' => 'assets/images/product_694fdfa4b422a.webp',
        'Соус Барбекю' => 'assets/images/product_694fdffe91946.webp',
        'Кетчуп' => 'assets/images/product_694fe045e3383.webp',
        'Соус Медово-горчичный' => 'assets/images/product_694fe0759b24a.webp',
        'Соус Сырный' => 'assets/images/product_694fe0aa16125.webp',
        'Соус Цезарь' => 'assets/images/product_694fe0d9550d1.webp',
        'Соус Чесночный' => 'assets/images/product_694fe125317f9.webp',
        'Соус Чили сладкий' => 'assets/images/product_6950e6a53909b.webp',
    ];

    public function run(): void
    {
        // Создаём директорию для изображений
        if (!Storage::disk('public')->exists('dishes')) {
            Storage::disk('public')->makeDirectory('dishes');
        }

        $downloaded = 0;
        $failed = 0;

        foreach ($this->images as $dishName => $imagePath) {
            // Ищем товар по имени (parent или simple)
            $dish = Dish::where('name', $dishName)
                ->whereIn('product_type', ['simple', 'parent'])
                ->first();

            if (!$dish) {
                $this->command->warn("Товар не найден: {$dishName}");
                continue;
            }

            try {
                // Скачиваем изображение
                $imageUrl = $this->baseUrl . $imagePath;
                $response = Http::timeout(30)->get($imageUrl);

                if ($response->successful()) {
                    // Генерируем имя файла
                    $extension = pathinfo($imagePath, PATHINFO_EXTENSION);
                    $filename = 'dishes/' . $dish->id . '_' . time() . '.' . $extension;

                    // Сохраняем файл
                    Storage::disk('public')->put($filename, $response->body());

                    // Обновляем товар
                    $dish->update(['image' => '/storage/' . $filename]);

                    $this->command->info("✓ {$dishName}");
                    $downloaded++;
                } else {
                    $this->command->error("✗ {$dishName} - HTTP {$response->status()}");
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->command->error("✗ {$dishName} - {$e->getMessage()}");
                $failed++;
            }

            // Небольшая пауза чтобы не перегружать сервер
            usleep(200000); // 0.2 сек
        }

        $this->command->newLine();
        $this->command->info("Готово! Скачано: {$downloaded}, Ошибок: {$failed}");
    }
}
