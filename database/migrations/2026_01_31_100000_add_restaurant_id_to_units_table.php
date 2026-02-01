<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (!Schema::hasColumn('units', 'restaurant_id')) {
                $table->foreignId('restaurant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('units', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('base_ratio');
            }
            if (!Schema::hasColumn('units', 'created_at')) {
                $table->timestamps();
            }
        });

        // Помечаем существующие единицы как системные
        \DB::table('units')->whereNull('restaurant_id')->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'restaurant_id')) {
                $table->dropForeign(['restaurant_id']);
                $table->dropColumn('restaurant_id');
            }
            if (Schema::hasColumn('units', 'is_system')) {
                $table->dropColumn('is_system');
            }
            if (Schema::hasColumn('units', 'created_at')) {
                $table->dropTimestamps();
            }
        });
    }
};
