<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Поле для хранения выбранных бонусов до оплаты.
     * Enterprise pattern: сервер = единый источник правды.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('pending_bonus_spend')->default(0)->after('bonus_used')
                ->comment('Бонусы выбранные для списания (до оплаты)');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('pending_bonus_spend');
        });
    }
};
