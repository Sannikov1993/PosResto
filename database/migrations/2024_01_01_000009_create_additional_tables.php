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
        // Зоны доставки
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('min_order', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('free_delivery_from', 10, 2)->nullable(); // Бесплатная доставка от суммы
            $table->unsignedInteger('delivery_time')->default(60); // Минуты
            $table->json('polygon')->nullable(); // [[lat, lng], ...]
            $table->string('color', 7)->default('#10B981');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['restaurant_id', 'is_active']);
        });

        // Расширенная информация о курьерах
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('vehicle_type', ['foot', 'bicycle', 'scooter', 'car'])->default('car');
            $table->string('vehicle_number', 20)->nullable();
            $table->string('vehicle_color', 50)->nullable();
            $table->enum('status', ['offline', 'available', 'busy', 'break'])->default('offline');
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->unsignedTinyInteger('max_orders')->default(3);
            $table->unsignedTinyInteger('current_orders_count')->default(0);
            $table->timestamps();
            
            $table->index(['status']);
        });

        // Бронирования столов
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->time('time_from');
            $table->time('time_to');
            $table->unsignedTinyInteger('guests')->default(2);
            $table->string('guest_name')->nullable();
            $table->string('guest_phone', 20)->nullable();
            $table->enum('status', [
                'pending',    // Ожидает подтверждения
                'confirmed',  // Подтверждено
                'seated',     // Гости сели
                'completed',  // Завершено
                'cancelled',  // Отменено
                'no_show'     // Не пришли
            ])->default('pending');
            $table->text('comment')->nullable();
            $table->string('source', 50)->default('crm'); // crm, website, phone
            $table->timestamps();
            
            $table->index(['restaurant_id', 'date', 'status']);
            $table->index(['table_id', 'date']);
        });

        // Стоп-лист (временно недоступные блюда)
        Schema::create('stop_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_id')->constrained()->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('stopped_at');
            $table->timestamp('resume_at')->nullable(); // Когда вернётся
            $table->foreignId('stopped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->unique(['restaurant_id', 'dish_id']);
        });

        // Смены персонала
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->decimal('cash_start', 10, 2)->default(0); // Касса на начало
            $table->decimal('cash_end', 10, 2)->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('tips', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['restaurant_id', 'started_at']);
            $table->index(['user_id', 'started_at']);
        });

        // История статусов заказов (для аналитики)
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50);
            $table->string('comment')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');
            
            $table->index(['order_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('stop_list');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('couriers');
        Schema::dropIfExists('delivery_zones');
    }
};
