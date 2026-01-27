<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Склады
        if (!Schema::hasTable('warehouses')) {
            Schema::create('warehouses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('type', 20)->default('main'); // main, kitchen, bar, storage
                $table->string('address')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // Категории ингредиентов
        if (!Schema::hasTable('ingredient_categories')) {
            Schema::create('ingredient_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('icon', 10)->default('📦');
                $table->string('color', 20)->default('#6b7280');
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // Единицы измерения
        if (!Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name', 50); // Килограмм, Литр, Штука
                $table->string('short_name', 10); // кг, л, шт
                $table->string('type', 20)->default('weight'); // weight, volume, piece, pack
                $table->decimal('base_ratio', 10, 4)->default(1); // Для конвертации (1000 для г->кг)
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        // Ингредиенты (товары на складе)
        if (!Schema::hasTable('ingredients')) {
            Schema::create('ingredients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('ingredient_categories')->nullOnDelete();
                $table->foreignId('unit_id')->constrained('units');
                $table->string('name', 150);
                $table->string('sku', 50)->nullable(); // Артикул
                $table->string('barcode', 50)->nullable();
                $table->text('description')->nullable();
                $table->decimal('cost_price', 10, 2)->default(0); // Себестоимость
                $table->decimal('min_stock', 10, 3)->default(0); // Минимальный остаток
                $table->decimal('max_stock', 10, 3)->nullable(); // Максимальный остаток
                $table->integer('shelf_life_days')->nullable(); // Срок годности в днях
                $table->string('storage_conditions')->nullable(); // Условия хранения
                $table->string('image')->nullable();
                $table->boolean('is_semi_finished')->default(false); // Полуфабрикат
                $table->boolean('track_stock')->default(true); // Вести учёт остатков
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['restaurant_id', 'category_id']);
                $table->index(['restaurant_id', 'sku']);
                $table->index('barcode');
            });
        }

        // Остатки ингредиентов на складах
        if (!Schema::hasTable('ingredient_stocks')) {
            Schema::create('ingredient_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
                $table->decimal('quantity', 12, 3)->default(0);
                $table->decimal('reserved', 12, 3)->default(0); // Зарезервировано
                $table->decimal('avg_cost', 10, 2)->default(0); // Средняя себестоимость
                $table->timestamps();

                $table->unique(['warehouse_id', 'ingredient_id']);
            });
        }

        // Движения товаров (приход, расход, списание, перемещение)
        if (!Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained();
                $table->foreignId('ingredient_id')->constrained();
                $table->foreignId('user_id')->constrained(); // Кто провёл
                $table->string('type', 20); // income, expense, write_off, transfer, production, sale, adjustment
                $table->string('document_type', 30)->nullable(); // invoice, order, inventory, production
                $table->unsignedBigInteger('document_id')->nullable();
                $table->decimal('quantity', 12, 3); // Положительное или отрицательное
                $table->decimal('cost_price', 10, 2)->default(0);
                $table->decimal('total_cost', 12, 2)->default(0);
                $table->string('reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('movement_date');
                $table->timestamps();

                $table->index(['restaurant_id', 'type', 'movement_date']);
                $table->index(['warehouse_id', 'ingredient_id']);
            });
        }

        // Поставщики
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('contact_person')->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('email')->nullable();
                $table->string('address')->nullable();
                $table->string('inn', 20)->nullable();
                $table->string('kpp', 20)->nullable();
                $table->text('payment_terms')->nullable();
                $table->integer('delivery_days')->nullable();
                $table->decimal('min_order_amount', 10, 2)->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Накладные (приходные/расходные документы)
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained();
                $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->constrained(); // Кто создал
                $table->string('type', 20); // income, expense, transfer, write_off
                $table->string('number', 50); // Номер накладной
                $table->string('external_number', 50)->nullable(); // Внешний номер
                $table->string('status', 20)->default('draft'); // draft, pending, completed, cancelled
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->foreignId('target_warehouse_id')->nullable()->constrained('warehouses'); // Для перемещений
                $table->date('invoice_date');
                $table->text('notes')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('completed_by')->nullable()->constrained('users');
                $table->timestamps();

                $table->index(['restaurant_id', 'type', 'status']);
            });
        }

        // Позиции накладных
        if (!Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained();
                $table->decimal('quantity', 12, 3);
                $table->decimal('cost_price', 10, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->date('expiry_date')->nullable();
                $table->string('batch_number', 50)->nullable();
                $table->timestamps();
            });
        }

        // Технологические карты (рецепты блюд)
        if (!Schema::hasTable('recipes')) {
            Schema::create('recipes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dish_id')->constrained('dishes')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained();
                $table->decimal('quantity', 10, 3); // Количество ингредиента
                $table->decimal('gross_quantity', 10, 3)->nullable(); // Брутто (до обработки)
                $table->decimal('waste_percent', 5, 2)->default(0); // Процент отходов
                $table->boolean('is_optional')->default(false); // Опциональный ингредиент
                $table->text('notes')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['dish_id', 'ingredient_id']);
            });
        }

        // Инвентаризации
        if (!Schema::hasTable('inventories')) {
            Schema::create('inventories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained();
                $table->foreignId('user_id')->constrained();
                $table->string('number', 50);
                $table->string('status', 20)->default('draft'); // draft, in_progress, completed, cancelled
                $table->date('inventory_date');
                $table->text('notes')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('completed_by')->nullable()->constrained('users');
                $table->timestamps();
            });
        }

        // Позиции инвентаризации
        if (!Schema::hasTable('inventory_items')) {
            Schema::create('inventory_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained();
                $table->decimal('expected_quantity', 12, 3); // Ожидаемое количество
                $table->decimal('actual_quantity', 12, 3)->nullable(); // Фактическое количество
                $table->decimal('difference', 12, 3)->nullable(); // Разница
                $table->decimal('cost_difference', 10, 2)->nullable(); // Разница в деньгах
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['inventory_id', 'ingredient_id']);
            });
        }

        // Добавим поле food_cost к блюдам если его нет
        if (!Schema::hasColumn('dishes', 'food_cost')) {
            Schema::table('dishes', function (Blueprint $table) {
                $table->decimal('food_cost', 10, 2)->default(0)->after('price');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('ingredient_stocks');
        Schema::dropIfExists('ingredients');
        Schema::dropIfExists('units');
        Schema::dropIfExists('ingredient_categories');
        Schema::dropIfExists('warehouses');

        if (Schema::hasColumn('dishes', 'food_cost')) {
            Schema::table('dishes', function (Blueprint $table) {
                $table->dropColumn('food_cost');
            });
        }
    }
};
