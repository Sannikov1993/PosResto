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
            // Флаг списания (еда была приготовлена и списана)
            $table->boolean('is_write_off')->default(false);
            // Сумма списания
            $table->decimal('write_off_amount', 10, 2)->default(0);
            // Кто отменил/списал
            $table->unsignedBigInteger('cancelled_by')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Флаг списания для позиции
            $table->boolean('is_write_off')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['is_write_off', 'write_off_amount', 'cancelled_by']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('is_write_off');
        });
    }
};
