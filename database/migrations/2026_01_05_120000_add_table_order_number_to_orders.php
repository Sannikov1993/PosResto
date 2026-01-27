<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Добавляем table_order_number если его ещё нет
        if (!Schema::hasColumn('orders', 'table_order_number')) {
            Schema::table('orders', function (Blueprint $table) {
                // Номер заказа внутри стола (для нескольких заказов на одном столе)
                $table->unsignedTinyInteger('table_order_number')->default(1)->after('table_id');
            });
        }

        // Добавляем индекс если его нет
        Schema::table('orders', function (Blueprint $table) {
            // Проверяем существование индекса через try-catch
            try {
                $table->index(['table_id', 'table_order_number', 'status'], 'orders_table_order_status_idx');
            } catch (\Exception $e) {
                // Индекс уже существует
            }
        });

        // guest_number уже существует, пропускаем
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            try {
                $table->dropIndex('orders_table_order_status_idx');
            } catch (\Exception $e) {
                // Индекс не существует
            }

            if (Schema::hasColumn('orders', 'table_order_number')) {
                $table->dropColumn('table_order_number');
            }
        });
    }
};
