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
        Schema::create('kitchen_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 64)->unique(); // UUID сгенерированный на клиенте
            $table->string('name', 100)->nullable();   // "Планшет горячего цеха"
            $table->foreignId('kitchen_station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending, active, disabled
            $table->string('pin', 6)->nullable();      // PIN для смены станции
            $table->json('settings')->nullable();       // Доп. настройки (яркость, звук и т.д.)
            $table->timestamp('last_seen_at')->nullable();
            $table->string('user_agent')->nullable();   // Для идентификации типа устройства
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_devices');
    }
};
