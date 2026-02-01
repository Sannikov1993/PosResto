<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Добавляет restaurant_id в дочерние модели для полной tenant-изоляции:
 * - order_item_modifiers (через order_item.restaurant_id)
 * - order_status_history (через order.restaurant_id)
 * - order_item_cancellations (через order_item.restaurant_id)
 * - invoice_items (через invoice.restaurant_id)
 * - inventory_check_items (через inventory_check.restaurant_id)
 * - write_off_items (через write_off.restaurant_id)
 * - price_list_items (через price_list.restaurant_id)
 * - promo_code_usages (через promo_code.restaurant_id)
 * - promotion_usages (через promotion.restaurant_id)
 * - gift_certificate_usages (через gift_certificate.restaurant_id)
 * - shift_events (через cash_shift.restaurant_id)
 * - delivery_problems (через delivery_order.restaurant_id)
 * - delivery_order_history (через delivery_order.restaurant_id)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. order_item_modifiers
        $this->addRestaurantIdToTable('order_item_modifiers', 'order_item_id', 'order_items');

        // 2. order_status_history
        $this->addRestaurantIdToTable('order_status_history', 'order_id', 'orders');

        // 3. order_item_cancellations (если существует)
        if (Schema::hasTable('order_item_cancellations')) {
            $this->addRestaurantIdToTable('order_item_cancellations', 'order_item_id', 'order_items');
        }

        // 4. invoice_items
        if (Schema::hasTable('invoice_items')) {
            $this->addRestaurantIdToTable('invoice_items', 'invoice_id', 'invoices');
        }

        // 5. inventory_check_items
        if (Schema::hasTable('inventory_check_items')) {
            $this->addRestaurantIdToTable('inventory_check_items', 'inventory_check_id', 'inventory_checks');
        }

        // 6. write_off_items
        if (Schema::hasTable('write_off_items')) {
            $this->addRestaurantIdToTable('write_off_items', 'write_off_id', 'write_offs');
        }

        // 7. price_list_items
        if (Schema::hasTable('price_list_items')) {
            $this->addRestaurantIdToTable('price_list_items', 'price_list_id', 'price_lists');
        }

        // 8. promo_code_usages
        if (Schema::hasTable('promo_code_usages')) {
            $this->addRestaurantIdToTable('promo_code_usages', 'promo_code_id', 'promo_codes');
        }

        // 9. promotion_usages
        if (Schema::hasTable('promotion_usages')) {
            $this->addRestaurantIdToTable('promotion_usages', 'promotion_id', 'promotions');
        }

        // 10. gift_certificate_usages
        if (Schema::hasTable('gift_certificate_usages')) {
            $this->addRestaurantIdToTable('gift_certificate_usages', 'gift_certificate_id', 'gift_certificates');
        }

        // 11. shift_events (через cash_shift_id -> cash_shifts)
        if (Schema::hasTable('shift_events')) {
            $this->addRestaurantIdToTable('shift_events', 'cash_shift_id', 'cash_shifts');
        }

        // 12. delivery_problems
        if (Schema::hasTable('delivery_problems')) {
            $this->addRestaurantIdToTable('delivery_problems', 'delivery_order_id', 'delivery_orders');
        }

        // 13. delivery_order_history
        if (Schema::hasTable('delivery_order_history')) {
            $this->addRestaurantIdToTable('delivery_order_history', 'delivery_order_id', 'delivery_orders');
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
            'order_item_modifiers',
            'order_status_history',
            'order_item_cancellations',
            'invoice_items',
            'inventory_check_items',
            'write_off_items',
            'price_list_items',
            'promo_code_usages',
            'promotion_usages',
            'gift_certificate_usages',
            'shift_events',
            'delivery_problems',
            'delivery_order_history',
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
