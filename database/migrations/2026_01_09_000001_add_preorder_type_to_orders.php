<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite не поддерживает ALTER COLUMN для enum/check constraints
        // Поэтому убираем constraint и меняем на обычный string

        if (DB::getDriverName() === 'sqlite') {
            // Для SQLite: пересоздаём колонку без constraint

            // 1. Удаляем индекс который использует type
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_restaurant_id_type_status_index');
            });

            // 2. Создаём новую колонку
            Schema::table('orders', function (Blueprint $table) {
                $table->string('type_new', 20)->default('dine_in')->after('daily_number');
            });

            // 3. Копируем данные
            DB::statement('UPDATE orders SET type_new = type');

            // 4. Удаляем старую колонку с constraint
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('type');
            });

            // 5. Переименовываем новую
            Schema::table('orders', function (Blueprint $table) {
                $table->renameColumn('type_new', 'type');
            });

            // 6. Восстанавливаем индекс
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['restaurant_id', 'type', 'status'], 'orders_restaurant_id_type_status_index');
            });
        } else {
            // Для MySQL/PostgreSQL - просто меняем enum
            DB::statement("ALTER TABLE orders MODIFY COLUMN type ENUM('dine_in', 'delivery', 'pickup', 'aggregator', 'preorder') DEFAULT 'dine_in'");
        }
    }

    public function down(): void
    {
        // Откат - убираем preorder
        if (DB::getDriverName() === 'sqlite') {
            // Для SQLite просто оставляем string - откат не критичен
        } else {
            DB::statement("ALTER TABLE orders MODIFY COLUMN type ENUM('dine_in', 'delivery', 'pickup', 'aggregator') DEFAULT 'dine_in'");
        }
    }
};
