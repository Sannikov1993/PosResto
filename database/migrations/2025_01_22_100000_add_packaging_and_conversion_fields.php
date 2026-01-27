<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Улучшение системы единиц измерения:
     * - Фасовки (упаковки) для ингредиентов
     * - Вес штуки, плотность
     * - Потери при обработке (брутто/нетто)
     */
    public function up(): void
    {
        // 1. Расширяем таблицу ingredients
        Schema::table('ingredients', function (Blueprint $table) {
            // Вес одной штуки в базовых единицах (для штучных товаров)
            // Пример: яйцо = 0.05 кг, булочка = 0.06 кг
            $table->decimal('piece_weight', 10, 4)->nullable()->after('unit_id')
                ->comment('Вес 1 штуки в кг (для штучных товаров)');

            // Плотность для конвертации объём ↔ вес
            // Пример: молоко = 1.03, масло растительное = 0.92
            $table->decimal('density', 6, 4)->nullable()->after('piece_weight')
                ->comment('Плотность (кг/л) для конвертации объём-вес');

            // Потери при холодной обработке (очистка, разделка)
            // Пример: картофель 20%, мясо 15%
            $table->decimal('cold_loss_percent', 5, 2)->default(0)->after('density')
                ->comment('Потери при холодной обработке, %');

            // Потери при горячей обработке (варка, жарка)
            // Пример: мясо при жарке 35%, овощи при варке 10%
            $table->decimal('hot_loss_percent', 5, 2)->default(0)->after('cold_loss_percent')
                ->comment('Потери при горячей обработке, %');
        });

        // 2. Создаём таблицу фасовок
        Schema::create('ingredient_packagings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->onDelete('cascade');
            $table->foreignId('unit_id')->constrained()->onDelete('restrict');

            // Количество базовых единиц в этой фасовке
            // Пример: коробка яиц = 30 шт, канистра молока = 5 л
            $table->decimal('quantity', 12, 4);

            // Штрих-код фасовки (может отличаться от штрих-кода ингредиента)
            $table->string('barcode', 50)->nullable();

            // Фасовка по умолчанию для приёмки
            $table->boolean('is_default')->default(false);

            // Фасовка для закупки (показывать в заказах поставщику)
            $table->boolean('is_purchase')->default(true);

            // Название фасовки (опционально, для отображения)
            // Пример: "Коробка 30 шт", "Канистра 5л"
            $table->string('name', 100)->nullable();

            $table->timestamps();

            // Индексы
            $table->unique(['ingredient_id', 'unit_id']);
            $table->index('barcode');
        });

        // 3. Расширяем таблицу recipes
        if (!Schema::hasColumn('recipes', 'unit_id')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('recipes', 'processing_type')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->enum('processing_type', ['none', 'cold', 'hot', 'both'])->default('none');
            });
        }

        // 4. Расширяем таблицу invoice_items для поддержки фасовок при приёмке
        if (Schema::hasTable('invoice_items')) {
            if (!Schema::hasColumn('invoice_items', 'packaging_id')) {
                Schema::table('invoice_items', function (Blueprint $table) {
                    $table->foreignId('packaging_id')->nullable()
                        ->constrained('ingredient_packagings')->nullOnDelete();
                });
            }

            if (!Schema::hasColumn('invoice_items', 'packaging_quantity')) {
                Schema::table('invoice_items', function (Blueprint $table) {
                    $table->decimal('packaging_quantity', 12, 4)->nullable()
                        ->comment('Количество в фасовках');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('packaging_id');
            $table->dropColumn('packaging_quantity');
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn('processing_type');
        });

        Schema::dropIfExists('ingredient_packagings');

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn([
                'piece_weight',
                'density',
                'cold_loss_percent',
                'hot_loss_percent'
            ]);
        });
    }
};
