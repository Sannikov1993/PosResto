<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Добавляет restaurant_id в дополнительные дочерние модели:
 * - couriers (через user.restaurant_id)
 * - customer_addresses (через customer.restaurant_id)
 * - courier_location_logs (через order.restaurant_id)
 * - ingredient_packagings (через ingredient.restaurant_id)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. couriers
        if (Schema::hasTable('couriers')) {
            $this->addRestaurantIdToTable('couriers', 'user_id', 'users');
        }

        // 2. customer_addresses
        if (Schema::hasTable('customer_addresses')) {
            $this->addRestaurantIdToTable('customer_addresses', 'customer_id', 'customers');
        }

        // 3. courier_location_logs
        if (Schema::hasTable('courier_location_logs')) {
            $this->addRestaurantIdToTable('courier_location_logs', 'order_id', 'orders');
        }

        // 4. ingredient_packagings
        if (Schema::hasTable('ingredient_packagings')) {
            $this->addRestaurantIdToTable('ingredient_packagings', 'ingredient_id', 'ingredients');
        }
    }

    /**
     * Добавить restaurant_id в таблицу и заполнить из родительской таблицы
     */
    private function addRestaurantIdToTable(string $table, string $parentFk, string $parentTable): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        if (!Schema::hasColumn($table, 'restaurant_id')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('restaurant_id')->nullable()->after('id')
                    ->constrained('restaurants')->nullOnDelete();
                $blueprint->index('restaurant_id');
            });

            // Заполняем из родительской таблицы (SQLite-совместимо)
            $items = DB::table($table)
                ->whereNull('restaurant_id')
                ->select('id', $parentFk)
                ->get();

            foreach ($items as $item) {
                $parentId = $item->$parentFk;
                if ($parentId) {
                    $parent = DB::table($parentTable)->where('id', $parentId)->first();
                    if ($parent && isset($parent->restaurant_id)) {
                        DB::table($table)
                            ->where('id', $item->id)
                            ->update(['restaurant_id' => $parent->restaurant_id]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'couriers',
            'customer_addresses',
            'courier_location_logs',
            'ingredient_packagings',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'restaurant_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropForeign(['restaurant_id']);
                    $blueprint->dropColumn('restaurant_id');
                });
            }
        }
    }
};
