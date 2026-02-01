<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Таблицы, которые переносим на уровень tenant (общие для сети)
     */
    private array $tenantLevelTables = [
        'categories',
        'dishes',
        'customers',
        'customer_addresses',
        'loyalty_levels',
        'promotions',
        'ingredients',
        'ingredient_categories',
        'modifiers',
        'modifier_options',
        'bonus_settings',
        'loyalty_settings',
        'units',
        'suppliers',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Добавляем tenant_id во все таблицы
        foreach ($this->tenantLevelTables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                    $table->index('tenant_id');
                });
            }
        }

        // 2. Заполняем tenant_id из связанного restaurant
        $this->fillTenantIds();

        // 3. Для customer_addresses берём tenant_id из customer
        if (Schema::hasTable('customer_addresses') && Schema::hasColumn('customer_addresses', 'tenant_id')) {
            DB::statement("
                UPDATE customer_addresses
                SET tenant_id = (
                    SELECT customers.tenant_id
                    FROM customers
                    WHERE customers.id = customer_addresses.customer_id
                )
                WHERE tenant_id IS NULL
            ");
        }

        // 4. Для modifier_options берём tenant_id из modifier
        if (Schema::hasTable('modifier_options') && Schema::hasColumn('modifier_options', 'tenant_id')) {
            DB::statement("
                UPDATE modifier_options
                SET tenant_id = (
                    SELECT modifiers.tenant_id
                    FROM modifiers
                    WHERE modifiers.id = modifier_options.modifier_id
                )
                WHERE tenant_id IS NULL
            ");
        }
    }

    /**
     * Заполняем tenant_id из restaurant_id
     */
    private function fillTenantIds(): void
    {
        // Получаем маппинг restaurant_id -> tenant_id
        $restaurants = DB::table('restaurants')
            ->whereNotNull('tenant_id')
            ->pluck('tenant_id', 'id')
            ->toArray();

        if (empty($restaurants)) {
            return;
        }

        // Для каждой таблицы с restaurant_id заполняем tenant_id
        $tablesWithRestaurantId = [
            'categories',
            'dishes',
            'customers',
            'loyalty_levels',
            'promotions',
            'ingredients',
            'ingredient_categories',
            'modifiers',
            'bonus_settings',
            'loyalty_settings',
            'units',
            'suppliers',
        ];

        foreach ($tablesWithRestaurantId as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'restaurant_id')) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            foreach ($restaurants as $restaurantId => $tenantId) {
                DB::table($tableName)
                    ->where('restaurant_id', $restaurantId)
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $tenantId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tenantLevelTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->dropIndex([$tableName . '_tenant_id_index']);
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }
};
