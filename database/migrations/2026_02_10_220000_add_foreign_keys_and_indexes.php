<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Добавляет недостающие FK constraints и production-индексы.
 *
 * Каждый FK и индекс обёрнут в try-catch, чтобы миграция
 * не падала при повторном запуске или если constraint уже существует.
 */
return new class extends Migration
{
    // ─── UP ────────────────────────────────────────────────────────────
    public function up(): void
    {
        // ───────────────────────────────────────────────────────
        //  1. Foreign Key Constraints
        // ───────────────────────────────────────────────────────

        // recipe_items.recipe_id → recipes.id (cascadeOnDelete)
        $this->addForeignKey('recipe_items', 'recipe_id', 'recipes', 'id', 'cascade');

        // recipe_items.ingredient_id → ingredients.id (cascadeOnDelete)
        $this->addForeignKey('recipe_items', 'ingredient_id', 'ingredients', 'id', 'cascade');

        // stock_movements.ingredient_id → ingredients.id (cascadeOnDelete)
        $this->addForeignKey('stock_movements', 'ingredient_id', 'ingredients', 'id', 'cascade');

        // stock_movements.supplier_id → suppliers.id (nullOnDelete) [nullable]
        $this->addForeignKey('stock_movements', 'supplier_id', 'suppliers', 'id', 'set null');

        // stock_movements.order_id → orders.id (nullOnDelete) [nullable]
        $this->addForeignKey('stock_movements', 'order_id', 'orders', 'id', 'set null');

        // stock_movements.user_id → users.id (nullOnDelete) [nullable]
        $this->addForeignKey('stock_movements', 'user_id', 'users', 'id', 'set null');

        // inventory_check_items.inventory_check_id → inventory_checks.id (cascadeOnDelete)
        $this->addForeignKey('inventory_check_items', 'inventory_check_id', 'inventory_checks', 'id', 'cascade');

        // inventory_check_items.ingredient_id → ingredients.id (cascadeOnDelete)
        $this->addForeignKey('inventory_check_items', 'ingredient_id', 'ingredients', 'id', 'cascade');

        // shifts.user_id → users.id (cascadeOnDelete)
        $this->addForeignKey('shifts', 'user_id', 'users', 'id', 'cascade');

        // time_entries.user_id → users.id (cascadeOnDelete)
        $this->addForeignKey('time_entries', 'user_id', 'users', 'id', 'cascade');

        // time_entries.shift_id → shifts.id (nullOnDelete) [nullable]
        $this->addForeignKey('time_entries', 'shift_id', 'shifts', 'id', 'set null');

        // tips.user_id → users.id (cascadeOnDelete)
        $this->addForeignKey('tips', 'user_id', 'users', 'id', 'cascade');

        // tips.order_id → orders.id (nullOnDelete) [nullable]
        $this->addForeignKey('tips', 'order_id', 'orders', 'id', 'set null');

        // print_jobs.printer_id → printers.id (cascadeOnDelete)
        $this->addForeignKey('print_jobs', 'printer_id', 'printers', 'id', 'cascade');

        // table_qr_codes.table_id → tables.id (cascadeOnDelete)
        $this->addForeignKey('table_qr_codes', 'table_id', 'tables', 'id', 'cascade');

        // waiter_calls.table_id → tables.id (cascadeOnDelete)
        $this->addForeignKey('waiter_calls', 'table_id', 'tables', 'id', 'cascade');

        // promo_code_usages.promo_code_id → promo_codes.id (cascadeOnDelete)
        $this->addForeignKey('promo_code_usages', 'promo_code_id', 'promo_codes', 'id', 'cascade');

        // promo_code_usages.customer_id → customers.id (cascadeOnDelete)
        $this->addForeignKey('promo_code_usages', 'customer_id', 'customers', 'id', 'cascade');

        // promo_code_usages.order_id → orders.id (nullOnDelete) [nullable]
        $this->addForeignKey('promo_code_usages', 'order_id', 'orders', 'id', 'set null');

        // bonus_transactions.customer_id → customers.id (cascadeOnDelete)
        $this->addForeignKey('bonus_transactions', 'customer_id', 'customers', 'id', 'cascade');

        // ───────────────────────────────────────────────────────
        //  2. Production Indexes
        // ───────────────────────────────────────────────────────

        // reservations — полная проверка конфликтов
        $this->addIndex('reservations', ['restaurant_id', 'date', 'table_id', 'status'], 'reservations_conflict_check');

        // cash_operations — Z-report aggregation
        $this->addIndex('cash_operations', ['cash_shift_id', 'payment_method', 'type'], 'cash_ops_report');

        // stock_movements — история по ингредиенту
        $this->addIndex('stock_movements', ['ingredient_id', 'created_at'], 'stock_mvt_ingredient_history');

        // promotions — auto-promotion lookup
        $this->addIndex('promotions', ['restaurant_id', 'is_active', 'is_automatic'], 'promotions_auto_lookup');

        // order_items — kitchen per station
        $this->addIndex('order_items', ['order_id', 'status', 'station'], 'order_items_kitchen_station');

        // bonus_transactions — loyalty history
        $this->addIndex('bonus_transactions', ['restaurant_id', 'customer_id', 'created_at'], 'bonus_tx_history');

        // delivery_orders — dashboard
        $this->addIndex('delivery_orders', ['restaurant_id', 'delivery_status', 'created_at'], 'delivery_orders_dashboard');

        // promo_codes — code validation
        $this->addIndex('promo_codes', ['restaurant_id', 'is_active', 'code'], 'promo_codes_validation');
    }

    // ─── DOWN ──────────────────────────────────────────────────────────
    public function down(): void
    {
        // ── Удаляем индексы ──────────────────────────────────
        $this->dropIndex('reservations', 'reservations_conflict_check');
        $this->dropIndex('cash_operations', 'cash_ops_report');
        $this->dropIndex('stock_movements', 'stock_mvt_ingredient_history');
        $this->dropIndex('promotions', 'promotions_auto_lookup');
        $this->dropIndex('order_items', 'order_items_kitchen_station');
        $this->dropIndex('bonus_transactions', 'bonus_tx_history');
        $this->dropIndex('delivery_orders', 'delivery_orders_dashboard');
        $this->dropIndex('promo_codes', 'promo_codes_validation');

        // ── Удаляем FK constraints ───────────────────────────
        $this->dropForeignKey('recipe_items', 'recipe_id');
        $this->dropForeignKey('recipe_items', 'ingredient_id');
        $this->dropForeignKey('stock_movements', 'ingredient_id');
        $this->dropForeignKey('stock_movements', 'supplier_id');
        $this->dropForeignKey('stock_movements', 'order_id');
        $this->dropForeignKey('stock_movements', 'user_id');
        $this->dropForeignKey('inventory_check_items', 'inventory_check_id');
        $this->dropForeignKey('inventory_check_items', 'ingredient_id');
        $this->dropForeignKey('shifts', 'user_id');
        $this->dropForeignKey('time_entries', 'user_id');
        $this->dropForeignKey('time_entries', 'shift_id');
        $this->dropForeignKey('tips', 'user_id');
        $this->dropForeignKey('tips', 'order_id');
        $this->dropForeignKey('print_jobs', 'printer_id');
        $this->dropForeignKey('table_qr_codes', 'table_id');
        $this->dropForeignKey('waiter_calls', 'table_id');
        $this->dropForeignKey('promo_code_usages', 'promo_code_id');
        $this->dropForeignKey('promo_code_usages', 'customer_id');
        $this->dropForeignKey('promo_code_usages', 'order_id');
        $this->dropForeignKey('bonus_transactions', 'customer_id');
    }

    // ─── HELPERS ───────────────────────────────────────────────────────

    /**
     * Безопасно добавляет foreign key constraint.
     * Пропускает, если таблица/колонка не существует или FK уже есть.
     */
    private function addForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $onDelete,
    ): void {
        try {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                Log::info("[FK] Пропуск: таблица {$table} или колонка {$column} не существует");
                return;
            }

            if (!Schema::hasTable($referencedTable)) {
                Log::info("[FK] Пропуск: целевая таблица {$referencedTable} не существует");
                return;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable, $referencedColumn, $onDelete) {
                $blueprint->foreign($column)
                    ->references($referencedColumn)
                    ->on($referencedTable)
                    ->onDelete($onDelete);
            });
        } catch (\Throwable $e) {
            Log::warning("[FK] {$table}.{$column} → {$referencedTable}.{$referencedColumn}: {$e->getMessage()}");
        }
    }

    /**
     * Безопасно добавляет составной индекс.
     */
    private function addIndex(string $table, array $columns, string $indexName): void
    {
        try {
            if (!Schema::hasTable($table)) {
                Log::info("[INDEX] Пропуск: таблица {$table} не существует");
                return;
            }

            // Проверяем наличие всех колонок
            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    Log::info("[INDEX] Пропуск: колонка {$table}.{$column} не существует");
                    return;
                }
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
                $blueprint->index($columns, $indexName);
            });
        } catch (\Throwable $e) {
            Log::warning("[INDEX] {$indexName}: {$e->getMessage()}");
        }
    }

    /**
     * Безопасно удаляет foreign key constraint.
     */
    private function dropForeignKey(string $table, string $column): void
    {
        try {
            if (!Schema::hasTable($table)) {
                return;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                $blueprint->dropForeign(["{$column}"]);
            });
        } catch (\Throwable $e) {
            Log::warning("[DROP FK] {$table}.{$column}: {$e->getMessage()}");
        }
    }

    /**
     * Безопасно удаляет индекс.
     */
    private function dropIndex(string $table, string $indexName): void
    {
        try {
            if (!Schema::hasTable($table)) {
                return;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropIndex($indexName);
            });
        } catch (\Throwable $e) {
            Log::warning("[DROP INDEX] {$indexName}: {$e->getMessage()}");
        }
    }
};
