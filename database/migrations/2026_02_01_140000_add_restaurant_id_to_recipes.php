<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Добавляет restaurant_id в таблицу recipes для полной tenant-изоляции.
 * Заполняет из родительской таблицы dishes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('recipes', 'restaurant_id')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->foreignId('restaurant_id')->nullable()->after('id')
                    ->constrained('restaurants')->nullOnDelete();
                $table->index('restaurant_id');
            });

            // Заполняем restaurant_id из dishes (SQLite-совместимо)
            $recipes = DB::table('recipes')
                ->whereNull('restaurant_id')
                ->select('id', 'dish_id')
                ->get();

            foreach ($recipes as $recipe) {
                if ($recipe->dish_id) {
                    $dish = DB::table('dishes')->where('id', $recipe->dish_id)->first();
                    if ($dish && isset($dish->restaurant_id)) {
                        DB::table('recipes')
                            ->where('id', $recipe->id)
                            ->update(['restaurant_id' => $dish->restaurant_id]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('recipes', 'restaurant_id')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->dropForeign(['restaurant_id']);
                $table->dropColumn('restaurant_id');
            });
        }
    }
};
