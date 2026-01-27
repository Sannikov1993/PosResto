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
        Schema::create('fiscal_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');

            // Тип операции: sell (продажа), sell_refund (возврат)
            $table->enum('operation', ['sell', 'sell_refund'])->default('sell');

            // UUID от АТОЛ для отслеживания
            $table->string('external_id')->unique();
            $table->string('atol_uuid')->nullable();

            // Статус чека
            $table->enum('status', ['pending', 'processing', 'done', 'fail'])->default('pending');
            $table->text('error_message')->nullable();

            // Данные чека
            $table->decimal('total', 10, 2);
            $table->json('items'); // Позиции чека
            $table->json('payments'); // Способы оплаты

            // Фискальные данные (заполняются после успешной фискализации)
            $table->string('fiscal_document_number')->nullable();
            $table->string('fiscal_document_attribute')->nullable();
            $table->string('fn_number')->nullable(); // Номер ФН
            $table->string('shift_number')->nullable();
            $table->string('receipt_datetime')->nullable();
            $table->decimal('ofd_sum', 10, 2)->nullable();

            // Callback данные
            $table->json('callback_response')->nullable();

            // Данные покупателя (email или телефон для чека)
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();

            $table->timestamps();

            // Индексы
            $table->index(['restaurant_id', 'status']);
            $table->index(['order_id']);
            $table->index('atol_uuid');
        });

        // Добавляем поле fiscal_receipt_id в orders
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_fiscalized')->default(false);
            $table->string('fiscal_receipt_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['is_fiscalized', 'fiscal_receipt_number']);
        });

        Schema::dropIfExists('fiscal_receipts');
    }
};
