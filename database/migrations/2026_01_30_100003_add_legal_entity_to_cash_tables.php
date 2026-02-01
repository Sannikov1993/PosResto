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
        // Добавляем поля в cash_shifts
        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->foreignId('cash_register_id')
                ->nullable()
                ->after('cashier_id')
                ->constrained()
                ->nullOnDelete();
        });

        // Добавляем поля в cash_operations
        Schema::table('cash_operations', function (Blueprint $table) {
            $table->foreignId('legal_entity_id')
                ->nullable()
                ->after('restaurant_id')
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('cash_register_id')
                ->nullable()
                ->after('legal_entity_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['restaurant_id', 'legal_entity_id']);
        });

        // Добавляем поля в fiscal_receipts
        Schema::table('fiscal_receipts', function (Blueprint $table) {
            $table->foreignId('legal_entity_id')
                ->nullable()
                ->after('restaurant_id')
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('cash_register_id')
                ->nullable()
                ->after('legal_entity_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['restaurant_id', 'legal_entity_id']);
        });

        // Добавляем поле payment_split в orders
        Schema::table('orders', function (Blueprint $table) {
            $table->json('payment_split')->nullable()->after('applied_discounts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('payment_split');
        });

        Schema::table('fiscal_receipts', function (Blueprint $table) {
            $table->dropForeign(['legal_entity_id']);
            $table->dropForeign(['cash_register_id']);
            $table->dropIndex(['restaurant_id', 'legal_entity_id']);
            $table->dropColumn(['legal_entity_id', 'cash_register_id']);
        });

        Schema::table('cash_operations', function (Blueprint $table) {
            $table->dropForeign(['legal_entity_id']);
            $table->dropForeign(['cash_register_id']);
            $table->dropIndex(['restaurant_id', 'legal_entity_id']);
            $table->dropColumn(['legal_entity_id', 'cash_register_id']);
        });

        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->dropForeign(['cash_register_id']);
            $table->dropColumn('cash_register_id');
        });
    }
};
