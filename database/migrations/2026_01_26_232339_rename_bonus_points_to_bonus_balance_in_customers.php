<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Обе колонки bonus_points и bonus_balance уже существуют.
     * Копируем данные из bonus_points в bonus_balance (если там 0),
     * затем удаляем bonus_points.
     */
    public function up(): void
    {
        // Копируем значения из bonus_points в bonus_balance если bonus_balance пустой
        DB::table('customers')
            ->where(function ($query) {
                $query->whereNull('bonus_balance')
                      ->orWhere('bonus_balance', 0);
            })
            ->whereNotNull('bonus_points')
            ->where('bonus_points', '>', 0)
            ->update(['bonus_balance' => DB::raw('bonus_points')]);

        // Удаляем колонку bonus_points
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('bonus_points');
        });
    }

    public function down(): void
    {
        // Восстанавливаем колонку bonus_points
        Schema::table('customers', function (Blueprint $table) {
            $table->integer('bonus_points')->default(0);
        });

        // Копируем данные обратно
        DB::table('customers')->update(['bonus_points' => DB::raw('bonus_balance')]);
    }
};
