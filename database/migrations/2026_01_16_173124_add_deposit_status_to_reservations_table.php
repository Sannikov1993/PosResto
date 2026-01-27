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
        Schema::table('reservations', function (Blueprint $table) {
            // Флаг оплачен ли депозит (для совместимости)
            $table->boolean('deposit_paid')->default(false)->after('deposit');
            // Статус депозита: pending (ожидает), paid (оплачен), refunded (возвращён), transferred (переведён в заказ)
            $table->string('deposit_status', 20)->default('pending')->after('deposit_paid');
            // Когда был оплачен депозит
            $table->timestamp('deposit_paid_at')->nullable()->after('deposit_status');
            // Кто принял оплату депозита
            $table->unsignedBigInteger('deposit_paid_by')->nullable()->after('deposit_paid_at');
            // Способ оплаты депозита
            $table->string('deposit_payment_method', 20)->nullable()->after('deposit_paid_by');
            // ID кассовой операции (для связи с CashOperation)
            $table->unsignedBigInteger('deposit_operation_id')->nullable()->after('deposit_payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_paid',
                'deposit_status',
                'deposit_paid_at',
                'deposit_paid_by',
                'deposit_payment_method',
                'deposit_operation_id',
            ]);
        });
    }
};
