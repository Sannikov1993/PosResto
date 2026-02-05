<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Миграция для заполнения пустых restaurant_id
 *
 * Эта миграция заполняет NULL значения restaurant_id значением по умолчанию.
 * Необходима для перехода к строгому режиму мультитенантности.
 */
return new class extends Migration
{
    /**
     * Таблицы с полем restaurant_id, которые нужно обновить
     */
    private array $tables = [
        'orders',
        'order_items',
        'cash_shifts',
        'cash_operations',
        'reservations',
        'categories',
        'dishes',
        'customers',
        'tables',
        'zones',
        'kitchen_stations',
        'promotions',
        'promo_codes',
        'fiscal_receipts',
        'bonus_transactions',
        'bonus_settings',
        'loyalty_levels',
        'loyalty_settings',
        'modifiers',
        'ingredients',
        'ingredient_categories',
        'units',
        'suppliers',
        'warehouses',
        'stock_movements',
        'write_offs',
        'invoices',
        'inventory_checks',
        'printers',
        'print_jobs',
        'kitchen_devices',
        'tips',
        'waiter_calls',
        'reviews',
        'notifications',
        'realtime_events',
        'delivery_zones',
        'delivery_settings',
        'gift_certificates',
        'price_lists',
        'stop_lists',
        'table_qr_codes',
        'guest_menu_settings',
        'roles',
        'permissions',
        'shifts',
        'staff_schedules',
        'schedule_templates',
        'time_entries',
        'work_sessions',
        'work_schedules',
        'work_day_overrides',
        'attendance_devices',
        'attendance_events',
        'attendance_qr_codes',
        'salary_periods',
        'salary_calculations',
        'salary_payments',
        'staff_invitations',
        'legal_entities',
        'cash_registers',
    ];

    public function up(): void
    {
        // Получаем ID ресторана по умолчанию (первый ресторан)
        $defaultRestaurantId = DB::table('restaurants')->min('id');

        // Если ресторанов нет (например, в тестовом окружении) - пропускаем
        if (!$defaultRestaurantId) {
            return;
        }

        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if (!Schema::hasColumn($table, 'restaurant_id')) {
                continue;
            }

            $updated = DB::table($table)
                ->whereNull('restaurant_id')
                ->update(['restaurant_id' => $defaultRestaurantId]);

            if ($updated > 0) {
                echo "  Updated {$updated} rows in {$table}\n";
            }
        }
    }

    public function down(): void
    {
        // Откат не предусмотрен - данные останутся заполненными
        // Это безопасно, т.к. мы заполняем NULL значениями по умолчанию
    }
};
