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
        // Add enhanced deposit fields to reservations
        if (!Schema::hasColumn('reservations', 'deposit_transaction_id')) {
            Schema::table('reservations', function (Blueprint $table) {
                // Transaction ID for external payment systems
                $table->string('deposit_transaction_id')->nullable()->after('deposit_payment_method');

                // Refund fields
                $table->timestamp('deposit_refunded_at')->nullable();
                $table->unsignedBigInteger('deposit_refunded_by')->nullable();
                $table->string('deposit_refund_reason', 500)->nullable();

                // Transfer fields
                $table->unsignedBigInteger('deposit_transferred_to_order_id')->nullable();
                $table->timestamp('deposit_transferred_at')->nullable();
                $table->unsignedBigInteger('deposit_transferred_by')->nullable();

                // Forfeiture fields
                $table->timestamp('deposit_forfeited_at')->nullable();
                $table->unsignedBigInteger('deposit_forfeited_by')->nullable();
                $table->string('deposit_forfeit_reason', 500)->nullable();
            });
        }

        // Add prepaid fields to orders
        if (!Schema::hasColumn('orders', 'prepaid_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('prepaid_amount', 12, 2)->default(0)->after('tips');
                $table->string('prepaid_source')->nullable()->after('prepaid_amount');
                $table->unsignedBigInteger('prepaid_reservation_id')->nullable()->after('prepaid_source');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['prepaid_amount', 'prepaid_source', 'prepaid_reservation_id']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'deposit_transaction_id',
                'deposit_refunded_at',
                'deposit_refunded_by',
                'deposit_refund_reason',
                'deposit_transferred_to_order_id',
                'deposit_transferred_at',
                'deposit_transferred_by',
                'deposit_forfeited_at',
                'deposit_forfeited_by',
                'deposit_forfeit_reason',
            ]);
        });
    }
};
