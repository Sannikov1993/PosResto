<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enterprise-level: добавляем недостающие FK constraints и индексы
 * для обеспечения referential integrity.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. FK на order_item_cancellations.writeoff_id → write_offs.id
        Schema::table('order_item_cancellations', function (Blueprint $table) {
            $table->foreign('writeoff_id')
                ->references('id')
                ->on('write_offs')
                ->nullOnDelete();

            // Дополнительные индексы для частых запросов
            $table->index('writeoff_id');
        });

        // 2. FK на orders.cancelled_by → users.id
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('cancelled_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_cancellations', function (Blueprint $table) {
            $table->dropForeign(['writeoff_id']);
            $table->dropIndex(['writeoff_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropIndex(['cancelled_by']);
        });
    }
};
