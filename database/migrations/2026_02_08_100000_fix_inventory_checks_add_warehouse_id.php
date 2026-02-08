<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-3: Добавляет недостающие колонки в inventory_checks и inventory_check_items.
 * Причина: миграция 2024 создаёт эти таблицы с урезанной схемой,
 * а 2025 миграция пропускается из-за if (!Schema::hasTable()) check.
 */
return new class extends Migration
{
    public function up(): void
    {
        // === inventory_checks: добавить warehouse_id ===
        if (Schema::hasTable('inventory_checks') && !Schema::hasColumn('inventory_checks', 'warehouse_id')) {
            Schema::table('inventory_checks', function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('restaurant_id');

                if (Schema::hasTable('warehouses')) {
                    $table->foreign('warehouse_id')->references('id')->on('warehouses');
                }
            });
        }

        // === inventory_check_items: добавить cost_price ===
        if (Schema::hasTable('inventory_check_items') && !Schema::hasColumn('inventory_check_items', 'cost_price')) {
            Schema::table('inventory_check_items', function (Blueprint $table) {
                $table->decimal('cost_price', 10, 2)->default(0)->after('difference');
            });
        }

        // === inventory_check_items: добавить restaurant_id (для BelongsToRestaurant) ===
        if (Schema::hasTable('inventory_check_items') && !Schema::hasColumn('inventory_check_items', 'restaurant_id')) {
            Schema::table('inventory_check_items', function (Blueprint $table) {
                $table->unsignedBigInteger('restaurant_id')->nullable()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_checks', 'warehouse_id')) {
            Schema::table('inventory_checks', function (Blueprint $table) {
                $table->dropColumn('warehouse_id');
            });
        }

        if (Schema::hasColumn('inventory_check_items', 'cost_price')) {
            Schema::table('inventory_check_items', function (Blueprint $table) {
                $table->dropColumn('cost_price');
            });
        }

        if (Schema::hasColumn('inventory_check_items', 'restaurant_id')) {
            Schema::table('inventory_check_items', function (Blueprint $table) {
                $table->dropColumn('restaurant_id');
            });
        }
    }
};
