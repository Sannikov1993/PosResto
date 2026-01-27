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
        // Кассовые смены
        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('cashier_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('shift_number')->nullable(); // Номер смены
            $table->enum('status', ['open', 'closed'])->default('open');

            // Суммы
            $table->decimal('opening_amount', 12, 2)->default(0); // Сумма при открытии
            $table->decimal('closing_amount', 12, 2)->nullable(); // Сумма при закрытии
            $table->decimal('expected_amount', 12, 2)->nullable(); // Ожидаемая сумма
            $table->decimal('difference', 12, 2)->nullable(); // Расхождение

            // Итоги по типам оплаты
            $table->decimal('total_cash', 12, 2)->default(0); // Наличные
            $table->decimal('total_card', 12, 2)->default(0); // Безнал
            $table->decimal('total_online', 12, 2)->default(0); // Онлайн

            // Счётчики
            $table->integer('orders_count')->default(0); // Кол-во заказов
            $table->integer('refunds_count')->default(0); // Кол-во возвратов
            $table->decimal('refunds_amount', 12, 2)->default(0); // Сумма возвратов

            // Время
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index(['restaurant_id', 'opened_at']);
        });

        // Кассовые операции (движение денег)
        Schema::create('cash_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('cash_shift_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            // Тип операции
            $table->enum('type', [
                'income',      // Приход (оплата заказа)
                'expense',     // Расход (возврат, закупка)
                'deposit',     // Внесение денег в кассу
                'withdrawal',  // Изъятие из кассы
                'correction',  // Корректировка
            ]);

            // Категория для расходов/приходов
            $table->string('category')->nullable(); // например: refund, purchase, salary, tips

            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'card', 'online'])->default('cash');

            $table->string('description')->nullable();
            $table->text('notes')->nullable();

            // Связь с фискальным чеком
            $table->foreignId('fiscal_receipt_id')->nullable()->constrained()->onDelete('set null');

            $table->timestamps();

            $table->index(['restaurant_id', 'type']);
            $table->index(['cash_shift_id', 'type']);
            $table->index(['restaurant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_operations');
        Schema::dropIfExists('cash_shifts');
    }
};
