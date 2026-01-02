<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Поставщики
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->string('name', 100);
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('restaurant_id');
        });

        // Единицы измерения
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);        // Килограмм
            $table->string('short_name', 10);  // кг
            $table->enum('type', ['weight', 'volume', 'piece'])->default('piece');
            $table->decimal('base_ratio', 10, 4)->default(1); // коэффициент к базовой единице
        });

        // Заполняем базовые единицы
        DB::table('units')->insert([
            ['name' => 'Килограмм', 'short_name' => 'кг', 'type' => 'weight', 'base_ratio' => 1],
            ['name' => 'Грамм', 'short_name' => 'г', 'type' => 'weight', 'base_ratio' => 0.001],
            ['name' => 'Литр', 'short_name' => 'л', 'type' => 'volume', 'base_ratio' => 1],
            ['name' => 'Миллилитр', 'short_name' => 'мл', 'type' => 'volume', 'base_ratio' => 0.001],
            ['name' => 'Штука', 'short_name' => 'шт', 'type' => 'piece', 'base_ratio' => 1],
            ['name' => 'Порция', 'short_name' => 'порц', 'type' => 'piece', 'base_ratio' => 1],
            ['name' => 'Упаковка', 'short_name' => 'уп', 'type' => 'piece', 'base_ratio' => 1],
        ]);

        // Категории ингредиентов
        Schema::create('ingredient_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->string('name', 100);
            $table->string('icon', 10)->nullable();
            $table->integer('sort_order')->default(0);
        });

        // Заполняем базовые категории
        DB::table('ingredient_categories')->insert([
            ['restaurant_id' => 1, 'name' => 'Мясо и птица', 'icon' => '🥩', 'sort_order' => 1],
            ['restaurant_id' => 1, 'name' => 'Рыба и морепродукты', 'icon' => '🐟', 'sort_order' => 2],
            ['restaurant_id' => 1, 'name' => 'Овощи', 'icon' => '🥕', 'sort_order' => 3],
            ['restaurant_id' => 1, 'name' => 'Фрукты', 'icon' => '🍎', 'sort_order' => 4],
            ['restaurant_id' => 1, 'name' => 'Молочные продукты', 'icon' => '🧀', 'sort_order' => 5],
            ['restaurant_id' => 1, 'name' => 'Бакалея', 'icon' => '🌾', 'sort_order' => 6],
            ['restaurant_id' => 1, 'name' => 'Напитки', 'icon' => '🥤', 'sort_order' => 7],
            ['restaurant_id' => 1, 'name' => 'Специи и соусы', 'icon' => '🧂', 'sort_order' => 8],
            ['restaurant_id' => 1, 'name' => 'Заморозка', 'icon' => '❄️', 'sort_order' => 9],
            ['restaurant_id' => 1, 'name' => 'Прочее', 'icon' => '📦', 'sort_order' => 10],
        ]);

        // Ингредиенты (продукты на складе)
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('name', 150);
            $table->string('sku', 50)->nullable();              // артикул
            $table->unsignedBigInteger('unit_id');              // единица измерения
            $table->decimal('quantity', 12, 3)->default(0);     // текущий остаток
            $table->decimal('min_quantity', 12, 3)->default(0); // мин. остаток (для уведомлений)
            $table->decimal('cost_price', 10, 2)->default(0);   // себестоимость за единицу
            $table->date('expiry_date')->nullable();            // срок годности
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('track_stock')->default(true);      // отслеживать остатки
            $table->timestamps();
            
            $table->index(['restaurant_id', 'category_id']);
            $table->index(['restaurant_id', 'quantity']);
        });

        // Рецепты (техкарты) - связь блюда с ингредиентами
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dish_id')->unique();
            $table->decimal('output_quantity', 10, 3)->default(1); // выход порций
            $table->text('instructions')->nullable();              // инструкция приготовления
            $table->integer('prep_time_minutes')->nullable();      // время подготовки
            $table->integer('cook_time_minutes')->nullable();      // время готовки
            $table->decimal('calculated_cost', 10, 2)->default(0); // расчётная себестоимость
            $table->timestamps();
            
            $table->foreign('dish_id')->references('id')->on('dishes')->onDelete('cascade');
        });

        // Состав рецепта (ингредиенты в рецепте)
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipe_id');
            $table->unsignedBigInteger('ingredient_id');
            $table->decimal('quantity', 10, 3);           // количество ингредиента
            $table->decimal('waste_percent', 5, 2)->default(0); // % отхода
            $table->text('notes')->nullable();
            
            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->onDelete('cascade');
            
            $table->unique(['recipe_id', 'ingredient_id']);
        });

        // Движение товаров (приход, расход, списание, инвентаризация)
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->unsignedBigInteger('ingredient_id');
            $table->enum('type', ['income', 'expense', 'write_off', 'inventory', 'transfer', 'return'])
                  ->default('income');
            $table->decimal('quantity', 12, 3);           // количество (+ приход, - расход)
            $table->decimal('quantity_before', 12, 3);    // остаток до
            $table->decimal('quantity_after', 12, 3);     // остаток после
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();   // если списание по заказу
            $table->string('document_number', 50)->nullable();    // номер накладной
            $table->text('reason')->nullable();                   // причина списания
            $table->unsignedBigInteger('user_id')->nullable();    // кто провёл
            $table->timestamps();
            
            $table->index(['restaurant_id', 'ingredient_id', 'created_at']);
            $table->index(['restaurant_id', 'type', 'created_at']);
        });

        // Инвентаризации
        Schema::create('inventory_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->string('number', 20);
            $table->date('date');
            $table->enum('status', ['draft', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['restaurant_id', 'date']);
        });

        // Позиции инвентаризации
        Schema::create('inventory_check_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_check_id');
            $table->unsignedBigInteger('ingredient_id');
            $table->decimal('expected_quantity', 12, 3);  // ожидаемое кол-во (по системе)
            $table->decimal('actual_quantity', 12, 3)->nullable(); // фактическое
            $table->decimal('difference', 12, 3)->nullable();      // разница
            $table->text('notes')->nullable();
            
            $table->foreign('inventory_check_id')->references('id')->on('inventory_checks')->onDelete('cascade');
            $table->unique(['inventory_check_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_check_items');
        Schema::dropIfExists('inventory_checks');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('ingredient_categories');
        Schema::dropIfExists('units');
        Schema::dropIfExists('suppliers');
    }
};
