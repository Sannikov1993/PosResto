<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Миграция для таблицы курьеров
 */
return new class extends Migration
{
    public function up(): void
    {
        // Пропускаем если таблица уже существует
        if (Schema::hasTable('couriers')) {
            return;
        }

        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->enum('status', ['available', 'busy', 'offline'])->default('offline');
            $table->enum('transport', ['car', 'bike', 'scooter', 'foot'])->default('car');
            $table->decimal('current_lat', 10, 8)->nullable();
            $table->decimal('current_lng', 11, 8)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couriers');
    }
};
