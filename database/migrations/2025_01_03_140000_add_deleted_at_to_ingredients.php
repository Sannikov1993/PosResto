<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Добавляем deleted_at если его нет
        if (!Schema::hasColumn('ingredients', 'deleted_at')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Добавляем недостающие поля для расширенной модели ингредиентов
        if (!Schema::hasColumn('ingredients', 'barcode')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->string('barcode')->nullable();
            });
        }

        if (!Schema::hasColumn('ingredients', 'description')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->text('description')->nullable();
            });
        }

        // Добавляем min_stock если нет (для совместимости со старой схемой)
        if (!Schema::hasColumn('ingredients', 'min_stock')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->decimal('min_stock', 10, 3)->default(0);
            });
        }

        if (!Schema::hasColumn('ingredients', 'max_stock')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->decimal('max_stock', 10, 3)->nullable();
            });
        }

        if (!Schema::hasColumn('ingredients', 'shelf_life_days')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->integer('shelf_life_days')->nullable();
            });
        }

        if (!Schema::hasColumn('ingredients', 'storage_conditions')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->string('storage_conditions')->nullable();
            });
        }

        if (!Schema::hasColumn('ingredients', 'is_semi_finished')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->boolean('is_semi_finished')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['barcode', 'description', 'max_stock', 'shelf_life_days', 'storage_conditions', 'is_semi_finished']);
        });
    }
};
