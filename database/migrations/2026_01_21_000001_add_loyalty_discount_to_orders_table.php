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
            $table->decimal('loyalty_discount_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('loyalty_level_id')->nullable();

            $table->foreign('loyalty_level_id')->references('id')->on('loyalty_levels')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['loyalty_level_id']);
            $table->dropColumn(['loyalty_discount_amount', 'loyalty_level_id']);
        });
    }
};
