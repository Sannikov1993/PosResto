<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enterprise-level: slug ресторана должен быть уникален в рамках tenant,
 * а не глобально. Разные тенанты могут иметь одинаковые slug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // Удаляем глобальный unique
            $table->dropUnique(['slug']);

            // Создаём composite unique: slug уникален в рамках tenant
            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->unique('slug');
        });
    }
};
