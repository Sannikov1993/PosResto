<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');

            // Код сертификата (уникальный)
            $table->string('code', 20)->unique();

            // Номинал и остаток
            $table->decimal('amount', 10, 2);           // Первоначальная сумма
            $table->decimal('balance', 10, 2);          // Текущий остаток

            // Кто купил / кому подарили
            $table->unsignedBigInteger('buyer_customer_id')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_phone')->nullable();

            // Кому предназначен (опционально)
            $table->unsignedBigInteger('recipient_customer_id')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();

            // Оплата при покупке сертификата
            $table->enum('payment_method', ['cash', 'card', 'online'])->default('cash');
            $table->unsignedBigInteger('sold_by_user_id')->nullable(); // Кто продал

            // Статус
            $table->enum('status', [
                'pending',    // Создан, но не оплачен
                'active',     // Активен, можно использовать
                'used',       // Полностью использован
                'expired',    // Истёк срок
                'cancelled',  // Отменён
            ])->default('pending');

            // Сроки
            $table->date('sold_at')->nullable();        // Дата продажи
            $table->date('activated_at')->nullable();   // Дата активации
            $table->date('expires_at')->nullable();     // Срок действия

            // Примечания
            $table->text('notes')->nullable();

            $table->timestamps();

            // Индексы
            $table->index('status');
            $table->index('expires_at');
            $table->foreign('buyer_customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('recipient_customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('sold_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        // Таблица использований сертификата
        Schema::create('gift_certificate_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_certificate_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('amount', 10, 2);           // Сумма списания
            $table->decimal('balance_before', 10, 2);   // Баланс до
            $table->decimal('balance_after', 10, 2);    // Баланс после
            $table->unsignedBigInteger('used_by_user_id')->nullable(); // Кто применил
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('used_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_certificate_usages');
        Schema::dropIfExists('gift_certificates');
    }
};
