<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * BUG-4: Конвертирует recipes из старой схемы (header) в новую (detail/items).
 *
 * Старая схема (2024): id, dish_id (unique), output_quantity, instructions, ...
 * Новая схема (2025): id, dish_id, ingredient_id, quantity, gross_quantity, waste_percent, ...
 *
 * Миграция добавляет недостающие колонки и убирает unique constraint на dish_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recipes')) {
            return;
        }

        // Добавляем ingredient_id если нет
        if (!Schema::hasColumn('recipes', 'ingredient_id')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->unsignedBigInteger('ingredient_id')->nullable()->after('dish_id');

                if (Schema::hasTable('ingredients')) {
                    $table->foreign('ingredient_id')->references('id')->on('ingredients');
                }
            });
        }

        // Добавляем quantity если нет
        if (!Schema::hasColumn('recipes', 'quantity')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->decimal('quantity', 10, 3)->default(0)->after('ingredient_id');
            });
        }

        // Добавляем gross_quantity если нет
        if (!Schema::hasColumn('recipes', 'gross_quantity')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->decimal('gross_quantity', 10, 3)->nullable()->after('quantity');
            });
        }

        // Добавляем waste_percent если нет
        if (!Schema::hasColumn('recipes', 'waste_percent')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->decimal('waste_percent', 5, 2)->default(0)->after('gross_quantity');
            });
        }

        // Добавляем is_optional если нет
        if (!Schema::hasColumn('recipes', 'is_optional')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->boolean('is_optional')->default(false)->after('waste_percent');
            });
        }

        // Добавляем notes если нет
        if (!Schema::hasColumn('recipes', 'notes')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('is_optional');
            });
        }

        // Добавляем sort_order если нет
        if (!Schema::hasColumn('recipes', 'sort_order')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->integer('sort_order')->default(0)->after('notes');
            });
        }

        // Убираем unique constraint на dish_id (MySQL)
        // В SQLite нельзя drop index, поэтому делаем через try-catch
        try {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                // Проверяем существование unique index
                $indexes = DB::select("SHOW INDEX FROM recipes WHERE Column_name = 'dish_id' AND Non_unique = 0");
                foreach ($indexes as $index) {
                    if ($index->Key_name !== 'PRIMARY') {
                        Schema::table('recipes', function (Blueprint $table) use ($index) {
                            $table->dropUnique($index->Key_name);
                        });
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore if index doesn't exist or SQLite
        }
    }

    public function down(): void
    {
        // Не откатываем — данные могут быть уже в новом формате
    }
};
