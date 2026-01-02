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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Кто создал
            $table->foreignId('table_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Основная информация
            $table->string('order_number', 20); // Дневной номер: 001, 002
            $table->string('daily_number', 10)->nullable(); // Для кухни: #45
            $table->enum('type', ['dine_in', 'delivery', 'pickup', 'aggregator'])->default('dine_in');
            $table->enum('status', [
                'new',        // Новый
                'confirmed',  // Подтверждён
                'cooking',    // Готовится
                'ready',      // Готов
                'delivering', // Доставляется
                'completed',  // Завершён
                'cancelled'   // Отменён
            ])->default('new');
            
            // Оплата
            $table->enum('payment_status', ['pending', 'paid', 'partial', 'refunded'])->default('pending');
            $table->enum('payment_method', ['cash', 'card', 'online', 'bonus', 'mixed'])->nullable();
            
            // Суммы
            $table->decimal('subtotal', 12, 2)->default(0); // Без скидки
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('tips', 10, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('change_amount', 10, 2)->default(0); // Сдача с
            
            // Детали
            $table->unsignedTinyInteger('persons')->default(1);
            $table->text('comment')->nullable();
            
            // Доставка
            $table->text('delivery_address')->nullable();
            $table->decimal('delivery_latitude', 10, 8)->nullable();
            $table->decimal('delivery_longitude', 11, 8)->nullable();
            $table->timestamp('delivery_time')->nullable(); // Желаемое время
            $table->unsignedInteger('estimated_delivery_minutes')->nullable();
            
            // Таймстампы процесса
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cooking_started_at')->nullable();
            $table->timestamp('cooking_finished_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('picked_up_at')->nullable(); // Курьер забрал
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            
            // Источник
            $table->string('source', 50)->default('crm'); // crm, website, app, yandex_eda, delivery_club
            $table->string('external_id', 100)->nullable(); // ID из агрегатора
            $table->json('external_data')->nullable(); // Доп. данные агрегатора
            
            // Печать
            $table->boolean('is_printed')->default(false);
            $table->timestamp('printed_at')->nullable();
            
            $table->timestamps();
            
            // Индексы
            $table->index(['restaurant_id', 'status', 'created_at']);
            $table->index(['restaurant_id', 'created_at']);
            $table->index(['restaurant_id', 'type', 'status']);
            $table->index(['customer_id']);
            $table->index(['table_id', 'status']);
            $table->index(['courier_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_id')->nullable()->constrained()->nullOnDelete();
            
            // Копия данных блюда (для истории)
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 10, 2); // Цена за единицу
            $table->decimal('modifiers_price', 10, 2)->default(0); // Доплата за модификаторы
            $table->decimal('discount', 10, 2)->default(0); // Скидка на позицию
            $table->decimal('total', 10, 2); // Итого
            
            // Модификаторы
            $table->json('modifiers')->nullable(); // [{name: "Большой", price: 50}, ...]
            
            // Статус приготовления
            $table->enum('status', [
                'pending',  // Ожидает
                'cooking',  // Готовится
                'ready',    // Готово
                'served'    // Подано
            ])->default('pending');
            
            $table->text('comment')->nullable();
            $table->timestamp('cooking_started_at')->nullable();
            $table->timestamp('cooking_finished_at')->nullable();
            $table->timestamp('served_at')->nullable();
            
            // Для кухонного дисплея - какая станция готовит
            $table->string('station', 50)->nullable(); // grill, cold, bar, etc.
            
            $table->timestamps();
            
            $table->index(['order_id', 'status']);
            $table->index(['status', 'created_at']); // Для кухонного дисплея
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
