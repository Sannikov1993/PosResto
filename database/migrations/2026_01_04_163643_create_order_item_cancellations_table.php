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
        Schema::create('order_item_cancellations', function (Blueprint $table) {
            $table->id();

            // Что отменили
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dish_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name'); // сохраняем на момент отмены
            $table->unsignedInteger('quantity');
            $table->decimal('price', 10, 2);
            $table->decimal('total', 10, 2);

            // Статус на момент отмены
            $table->enum('previous_status', [
                'new', 'pending', 'sent', 'cooking', 'ready', 'served'
            ]);

            // Кто отменил
            $table->foreignId('cancelled_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('cancelled_at')->useCurrent();

            // Причина (ОБЯЗАТЕЛЬНО!)
            $table->enum('reason_type', [
                'guest_refused',      // гость отказался
                'guest_changed_mind', // гость передумал
                'wrong_order',        // ошибка официанта
                'out_of_stock',       // закончился товар
                'quality_issue',      // проблема с качеством
                'long_wait',          // долго ждали
                'duplicate',          // дубликат заказа
                'other'               // другое
            ]);
            $table->text('reason_comment')->nullable();

            // Подтверждение менеджером (для дорогих/готовых)
            $table->boolean('requires_approval')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();

            // Уведомление кухни
            $table->boolean('kitchen_notified')->default(false);
            $table->timestamp('kitchen_notified_at')->nullable();
            $table->enum('kitchen_notification_method', ['kds', 'printer', 'manual'])->nullable();

            // Списание
            $table->boolean('is_writeoff')->default(false);
            $table->foreignId('writeoff_id')->nullable();

            // Метаданные
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            // Индексы
            $table->index('order_id');
            $table->index('cancelled_by');
            $table->index('cancelled_at');
            $table->index('approval_status');
            $table->index(['requires_approval', 'approval_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_cancellations');
    }
};
