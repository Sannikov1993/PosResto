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
        // Сначала проверяем и добавляем is_birthday_only если нет
        if (!Schema::hasColumn('promotions', 'is_birthday_only')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->boolean('is_birthday_only')->default(false);
            });
        }

        // Диапазон действия акции ко дню рождения
        if (!Schema::hasColumn('promotions', 'birthday_days_before')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->integer('birthday_days_before')->default(0);
            });
        }
        if (!Schema::hasColumn('promotions', 'birthday_days_after')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->integer('birthday_days_after')->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['birthday_days_before', 'birthday_days_after']);
        });
    }
};
