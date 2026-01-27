<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Обновление таблицы курьеров
 */
return new class extends Migration
{
    public function up(): void
    {
        // Для SQLite/MySQL нужно пересоздать таблицу с правильной структурой
        if (Schema::hasTable('couriers')) {
            // Получаем существующие данные
            $existingCouriers = \DB::table('couriers')->get();

            // Отключаем проверку FK для MySQL
            if (\DB::connection()->getDriverName() === 'mysql') {
                \DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            // Удаляем старую таблицу
            Schema::dropIfExists('couriers');

            // Создаём новую с правильной структурой
            Schema::create('couriers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name')->nullable();
                $table->string('phone')->nullable();
                $table->string('status')->default('offline');
                $table->string('transport')->default('car');
                $table->decimal('current_lat', 10, 8)->nullable();
                $table->decimal('current_lng', 11, 8)->nullable();
                $table->timestamp('last_location_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Восстанавливаем данные
            foreach ($existingCouriers as $courier) {
                \DB::table('couriers')->insert([
                    'id' => $courier->id,
                    'user_id' => $courier->user_id ?? null,
                    'name' => $courier->name ?? null,
                    'phone' => $courier->phone ?? null,
                    'status' => $courier->status ?? 'offline',
                    'transport' => $courier->transport ?? $courier->vehicle_type ?? 'car',
                    'current_lat' => $courier->current_lat ?? null,
                    'current_lng' => $courier->current_lng ?? null,
                    'last_location_at' => $courier->last_location_at ?? null,
                    'is_active' => $courier->is_active ?? true,
                    'created_at' => $courier->created_at ?? now(),
                    'updated_at' => $courier->updated_at ?? now(),
                ]);
            }

            // Включаем проверку FK обратно
            if (\DB::connection()->getDriverName() === 'mysql') {
                \DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    public function down(): void
    {
        // Не удаляем колонки при откате
    }
};
