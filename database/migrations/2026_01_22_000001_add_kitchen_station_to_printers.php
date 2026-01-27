<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Проверяем, что колонка ещё не существует (могла быть добавлена предыдущей миграцией)
        if (!Schema::hasColumn('printers', 'kitchen_station_id')) {
            Schema::table('printers', function (Blueprint $table) {
                // Привязка к цеху кухни (для кухонных принтеров)
                $table->unsignedBigInteger('kitchen_station_id')->nullable()->after('type');

                // Индекс для быстрого поиска принтеров по цеху
                $table->index('kitchen_station_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropIndex(['kitchen_station_id']);
            $table->dropColumn('kitchen_station_id');
        });
    }
};
