<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Миграция для таблицы проблем доставки
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_problems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('courier_id')->nullable()->constrained('couriers')->onDelete('set null');
            $table->enum('type', [
                'customer_unavailable',  // Клиент не отвечает
                'wrong_address',         // Неверный адрес
                'door_locked',           // Закрытая дверь/домофон
                'payment_issue',         // Проблема с оплатой
                'damaged_item',          // Повреждённый товар
                'other'                  // Другое
            ]);
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable(); // Фото проблемы
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('status', [
                'open',      // Открыта
                'in_progress', // В работе
                'resolved',  // Решена
                'cancelled'  // Отменена
            ])->default('open');
            $table->text('resolution')->nullable(); // Как решили проблему
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['delivery_order_id', 'status']);
            $table->index(['courier_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_problems');
    }
};
