<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reservations')) {
            return; // Таблица уже создана в другой миграции
        }

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->unsignedBigInteger('table_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            
            // Данные гостя
            $table->string('guest_name');
            $table->string('guest_phone');
            $table->string('guest_email')->nullable();
            
            // Дата и время
            $table->date('date');
            $table->time('time_from');
            $table->time('time_to');
            $table->integer('guests_count')->default(2);
            
            // Статус
            $table->enum('status', ['pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show'])
                  ->default('pending');
            
            // Дополнительно
            $table->text('notes')->nullable();
            $table->text('special_requests')->nullable();
            $table->decimal('deposit', 10, 2)->nullable();
            $table->boolean('deposit_paid')->default(false);
            
            // Напоминания
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('reminder_sent_at')->nullable();
            
            // Служебное
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            
            $table->index(['restaurant_id', 'date']);
            $table->index(['table_id', 'date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
