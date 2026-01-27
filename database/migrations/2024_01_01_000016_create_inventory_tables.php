<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Поставщики
        if (!Schema::hasTable('suppliers')) {
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
        }

        // Единицы измерения
        if (!Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->string('name', 50);
                $table->string('short_name', 10);
                $table->enum('type', ['weight', 'volume', 'piece'])->default('piece');
                $table->decimal('base_ratio', 10, 4)->default(1);
            });

            DB::table('units')->insert([
                ['name' => 'Килограмм', 'short_name' => 'кг', 'type' => 'weight', 'base_ratio' => 1],
                ['name' => 'Грамм', 'short_name' => 'г', 'type' => 'weight', 'base_ratio' => 0.001],
                ['name' => 'Литр', 'short_name' => 'л', 'type' => 'volume', 'base_ratio' => 1],
                ['name' => 'Миллилитр', 'short_name' => 'мл', 'type' => 'volume', 'base_ratio' => 0.001],
                ['name' => 'Штука', 'short_name' => 'шт', 'type' => 'piece', 'base_ratio' => 1],
                ['name' => 'Порция', 'short_name' => 'порц', 'type' => 'piece', 'base_ratio' => 1],
                ['name' => 'Упаковка', 'short_name' => 'уп', 'type' => 'piece', 'base_ratio' => 1],
            ]);
        }

        // Категории ингредиентов
        if (!Schema::hasTable('ingredient_categories')) {
            Schema::create('ingredient_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('restaurant_id')->default(1);
                $table->string('name', 100);
                $table->string('icon', 10)->nullable();
                $table->integer('sort_order')->default(0);
            });

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
        }

        // Ингредиенты
        if (!Schema::hasTable('ingredients')) {
            Schema::create('ingredients', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('restaurant_id')->default(1);
                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->string('name', 150);
                $table->string('sku', 50)->nullable();
                $table->unsignedBigInteger('unit_id');
                $table->decimal('quantity', 12, 3)->default(0);
                $table->decimal('min_quantity', 12, 3)->default(0);
                $table->decimal('cost_price', 10, 2)->default(0);
                $table->date('expiry_date')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('track_stock')->default(true);
                $table->timestamps();

                $table->index(['restaurant_id', 'category_id']);
                $table->index(['restaurant_id', 'quantity']);
            });
        }

        // Рецепты
        if (!Schema::hasTable('recipes')) {
            Schema::create('recipes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('dish_id')->unique();
                $table->decimal('output_quantity', 10, 3)->default(1);
                $table->text('instructions')->nullable();
                $table->integer('prep_time_minutes')->nullable();
                $table->integer('cook_time_minutes')->nullable();
                $table->decimal('calculated_cost', 10, 2)->default(0);
                $table->timestamps();
            });
        }

        // Состав рецепта
        if (!Schema::hasTable('recipe_items')) {
            Schema::create('recipe_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recipe_id');
                $table->unsignedBigInteger('ingredient_id');
                $table->decimal('quantity', 10, 3);
                $table->decimal('waste_percent', 5, 2)->default(0);
                $table->text('notes')->nullable();

                $table->unique(['recipe_id', 'ingredient_id']);
            });
        }

        // Движение товаров
        if (!Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('restaurant_id')->default(1);
                $table->unsignedBigInteger('ingredient_id');
                $table->enum('type', ['income', 'expense', 'write_off', 'inventory', 'transfer', 'return'])
                      ->default('income');
                $table->decimal('quantity', 12, 3);
                $table->decimal('quantity_before', 12, 3);
                $table->decimal('quantity_after', 12, 3);
                $table->decimal('cost_price', 10, 2)->nullable();
                $table->decimal('total_cost', 12, 2)->nullable();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->string('document_number', 50)->nullable();
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index(['restaurant_id', 'ingredient_id', 'created_at']);
                $table->index(['restaurant_id', 'type', 'created_at']);
            });
        }

        // Инвентаризации
        if (!Schema::hasTable('inventory_checks')) {
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
        }

        // Позиции инвентаризации
        if (!Schema::hasTable('inventory_check_items')) {
            Schema::create('inventory_check_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('inventory_check_id');
                $table->unsignedBigInteger('ingredient_id');
                $table->decimal('expected_quantity', 12, 3);
                $table->decimal('actual_quantity', 12, 3)->nullable();
                $table->decimal('difference', 12, 3)->nullable();
                $table->text('notes')->nullable();

                $table->unique(['inventory_check_id', 'ingredient_id']);
            });
        }
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
