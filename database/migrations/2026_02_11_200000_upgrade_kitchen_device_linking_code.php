<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0/Fix 1: Kitchen Device Auth Redesign
 *
 * linking_code: string(6) → string(64) для хранения SHA-256 хеша
 * Добавляем индекс для быстрого поиска
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchen_devices', function (Blueprint $table) {
            $table->string('linking_code', 64)->nullable()->change();
            $table->index('linking_code', 'idx_kitchen_devices_linking_code');
        });

        // Сбрасываем все текущие коды привязки (они были plaintext)
        DB::table('kitchen_devices')
            ->whereNotNull('linking_code')
            ->update(['linking_code' => null, 'linking_code_expires_at' => null]);
    }

    public function down(): void
    {
        Schema::table('kitchen_devices', function (Blueprint $table) {
            $table->dropIndex('idx_kitchen_devices_linking_code');
            $table->string('linking_code', 6)->nullable()->change();
        });
    }
};
