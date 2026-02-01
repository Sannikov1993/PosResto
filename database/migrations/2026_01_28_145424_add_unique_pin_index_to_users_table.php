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
        Schema::table('users', function (Blueprint $table) {
            // Удаляем старый индекс на pin_lookup если есть
            if (Schema::hasColumn('users', 'pin_lookup')) {
                try {
                    $table->dropIndex(['pin_lookup']);
                } catch (\Exception $e) {
                    // Индекс может не существовать
                }
            }

            // Добавляем составной индекс для быстрого поиска по ресторану + роли + PIN
            $table->index(['restaurant_id', 'role', 'pin_lookup'], 'users_restaurant_role_pin_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Удаляем составной индекс
            $table->dropIndex('users_restaurant_role_pin_index');

            // Восстанавливаем простой индекс на pin_lookup
            $table->index('pin_lookup');
        });
    }
};
