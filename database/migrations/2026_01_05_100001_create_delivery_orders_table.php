<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Миграция для таблицы заказов доставки
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->enum('type', ['delivery', 'pickup'])->default('delivery');
            $table->enum('status', ['new', 'cooking', 'ready', 'delivering', 'completed', 'cancelled'])->default('new');

            // Клиент
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->text('customer_comment')->nullable();

            // Адрес доставки
            $table->string('address_street')->nullable();
            $table->string('address_house')->nullable();
            $table->string('address_apartment')->nullable();
            $table->string('address_entrance')->nullable();
            $table->string('address_floor')->nullable();
            $table->string('address_intercom')->nullable();
            $table->text('address_comment')->nullable();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();

            // Время
            $table->timestamp('deliver_at')->nullable();
            $table->timestamp('cooking_started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Курьер
            $table->foreignId('courier_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('courier_assigned_at')->nullable();

            // Оплата
            $table->enum('payment_method', ['cash', 'card', 'online'])->default('cash');
            $table->decimal('change_from', 10, 2)->nullable();
            $table->boolean('is_paid')->default(false);

            // Суммы
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('delivery_cost', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            // Служебные
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->text('internal_comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index('deliver_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};
