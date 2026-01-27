<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Объединение PromoCode в Promotion (подход r_keeper)
     * Промокод теперь - это просто способ активации акции
     */
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            // Код для активации акции (опционально)
            $table->string('code', 50)->nullable()->unique()->after('slug');

            // Тип активации: auto (автоматически), manual (вручную кассир), by_code (по промокоду)
            $table->enum('activation_type', ['auto', 'manual', 'by_code'])->default('auto')->after('is_automatic');

            // Ограничение по клиентам (персональные промокоды)
            $table->json('allowed_customer_ids')->nullable()->after('excluded_customers');

            // Публичный промокод (показывать в списке доступных)
            $table->boolean('is_public')->default(false)->after('is_active');

            // Использование с другими акциями
            $table->boolean('single_use_with_promotions')->default(false)->after('is_exclusive');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'activation_type',
                'allowed_customer_ids',
                'is_public',
                'single_use_with_promotions'
            ]);
        });
    }
};
