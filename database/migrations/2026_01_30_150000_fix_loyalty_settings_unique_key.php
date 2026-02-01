<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_settings', function (Blueprint $table) {
            // Удаляем старый уникальный ключ на (restaurant_id, key)
            $table->dropUnique(['restaurant_id', 'key']);

            // Создаём новый уникальный ключ на (tenant_id, key)
            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_settings', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'key']);
            $table->unique(['restaurant_id', 'key']);
        });
    }
};
