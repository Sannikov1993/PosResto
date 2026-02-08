<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_check_items') && !Schema::hasColumn('inventory_check_items', 'cost_price')) {
            Schema::table('inventory_check_items', function (Blueprint $table) {
                $table->decimal('cost_price', 10, 2)->default(0)->after('difference');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_check_items', 'cost_price')) {
            Schema::table('inventory_check_items', function (Blueprint $table) {
                $table->dropColumn('cost_price');
            });
        }
    }
};
