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
        Schema::table('dishes', function (Blueprint $table) {
            $table->foreignId('kitchen_station_id')
                ->nullable()           // NULL = показывать на всех дисплеях
                ->after('category_id')
                ->constrained('kitchen_stations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            $table->dropForeign(['kitchen_station_id']);
            $table->dropColumn('kitchen_station_id');
        });
    }
};
