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
        Schema::table('orders', function (Blueprint $table) {
            // Интеграция лояльности
            $table->decimal('bonus_used', 10, 2)->default(0);
            $table->string('promo_code', 50)->nullable();

            // Интеграция склада
            $table->boolean('inventory_deducted')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['bonus_used', 'promo_code', 'inventory_deducted']);
        });
    }
};
