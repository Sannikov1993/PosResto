<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * NEW-BUG-1: Убираем UNIQUE constraint на recipes.dish_id.
 *
 * Старая схема (2024) создавала dish_id как unique (1 рецепт = 1 блюдо).
 * Новая схема: рецепт = набор строк (dish_id + ingredient_id), поэтому
 * unique на dish_id блокирует сохранение рецепта из 2+ ингредиентов.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recipes')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: dropUnique вызывает внутреннее пересоздание таблицы
            try {
                Schema::table('recipes', function (Blueprint $table) {
                    $table->dropUnique(['dish_id']);
                });
            } catch (\Exception $e) {
                // Индекс уже не существует — OK
            }
        } else {
            // MySQL/PostgreSQL
            try {
                Schema::table('recipes', function (Blueprint $table) {
                    $table->dropUnique(['dish_id']);
                });
            } catch (\Exception $e) {
                // Индекс уже не существует — OK
            }
        }
    }

    public function down(): void
    {
        // Не восстанавливаем — unique constraint на dish_id несовместим с новой архитектурой
    }
};
