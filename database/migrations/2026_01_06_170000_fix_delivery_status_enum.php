<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Для SQLite нужно пересоздать колонку
        // Сначала добавляем новую колонку без ограничений
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_status_new')->nullable();
        });

        // Копируем данные
        DB::table('orders')->update([
            'delivery_status_new' => DB::raw('delivery_status')
        ]);

        // Удаляем старую колонку
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_status');
        });

        // Переименовываем новую колонку
        Schema::table('orders', function (Blueprint $table) {
            $table->renameColumn('delivery_status_new', 'delivery_status');
        });
    }

    public function down(): void
    {
        // При откате возвращаем enum (но это редко нужно)
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_status_old')->nullable();
        });

        DB::table('orders')->update([
            'delivery_status_old' => DB::raw('delivery_status')
        ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_status');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('delivery_status', ['pending', 'preparing', 'ready', 'picked_up', 'in_transit', 'delivered', 'cancelled'])
                  ->nullable();
        });

        DB::table('orders')->update([
            'delivery_status' => DB::raw('delivery_status_old')
        ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_status_old');
        });
    }
};
