<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Уровни лояльности
        if (!Schema::hasTable('loyalty_levels')) {
            Schema::create('loyalty_levels', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('restaurant_id')->default(1);
                $table->string('name', 50);
                $table->string('icon', 10)->nullable();
                $table->string('color', 20)->default('#666');
                $table->decimal('min_total', 12, 2)->default(0);
                $table->decimal('discount_percent', 5, 2)->default(0);
                $table->decimal('cashback_percent', 5, 2)->default(0);
                $table->decimal('bonus_multiplier', 3, 2)->default(1);
                $table->boolean('birthday_bonus')->default(false);
                $table->decimal('birthday_discount', 5, 2)->default(0);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('restaurant_id');
            });

            DB::table('loyalty_levels')->insert([
                ['restaurant_id' => 1, 'name' => 'Новичок', 'icon' => '⭐', 'color' => '#9ca3af', 'min_total' => 0, 'discount_percent' => 0, 'cashback_percent' => 3, 'bonus_multiplier' => 1, 'birthday_bonus' => false, 'birthday_discount' => 0, 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['restaurant_id' => 1, 'name' => 'Бронза', 'icon' => '🥉', 'color' => '#cd7f32', 'min_total' => 5000, 'discount_percent' => 3, 'cashback_percent' => 5, 'bonus_multiplier' => 1, 'birthday_bonus' => true, 'birthday_discount' => 10, 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['restaurant_id' => 1, 'name' => 'Серебро', 'icon' => '🥈', 'color' => '#c0c0c0', 'min_total' => 15000, 'discount_percent' => 5, 'cashback_percent' => 7, 'bonus_multiplier' => 1.5, 'birthday_bonus' => true, 'birthday_discount' => 15, 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['restaurant_id' => 1, 'name' => 'Золото', 'icon' => '🥇', 'color' => '#ffd700', 'min_total' => 50000, 'discount_percent' => 10, 'cashback_percent' => 10, 'bonus_multiplier' => 2, 'birthday_bonus' => true, 'birthday_discount' => 20, 'sort_order' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // Промокоды
        if (!Schema::hasTable('promo_codes')) {
            Schema::create('promo_codes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('restaurant_id')->default(1);
                $table->string('code', 30)->unique();
                $table->string('name', 100);
                $table->enum('type', ['percent', 'fixed', 'bonus'])->default('percent');
                $table->decimal('value', 10, 2);
                $table->decimal('min_order', 10, 2)->default(0);
                $table->decimal('max_discount', 10, 2)->nullable();
                $table->integer('usage_limit')->nullable();
                $table->integer('usage_count')->default(0);
                $table->integer('per_customer_limit')->nullable();
                $table->date('valid_from')->nullable();
                $table->date('valid_until')->nullable();
                $table->boolean('first_order_only')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['restaurant_id', 'code']);
                $table->index(['restaurant_id', 'is_active']);
            });
        }

        // История использования промокодов
        if (!Schema::hasTable('promo_code_usages')) {
            Schema::create('promo_code_usages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('promo_code_id');
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('order_id');
                $table->decimal('discount_amount', 10, 2);
                $table->timestamps();

                $table->index(['promo_code_id', 'customer_id']);
            });
        }

        // Бонусные транзакции
        if (!Schema::hasTable('bonus_transactions')) {
            Schema::create('bonus_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('restaurant_id')->default(1);
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->enum('type', ['earn', 'spend', 'expire', 'manual', 'birthday', 'promo']);
                $table->decimal('amount', 10, 2);
                $table->decimal('balance_after', 10, 2);
                $table->string('description', 255)->nullable();
                $table->date('expires_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'created_at']);
                $table->index(['restaurant_id', 'type']);
            });
        }

        // Добавляем поля в customers если их нет
        if (!Schema::hasColumn('customers', 'loyalty_level_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedBigInteger('loyalty_level_id')->nullable()->after('restaurant_id');
            });
        }
        if (!Schema::hasColumn('customers', 'bonus_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->decimal('bonus_balance', 10, 2)->default(0);
            });
        }
        if (!Schema::hasColumn('customers', 'birthday')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->date('birthday')->nullable();
            });
        }

        // Настройки программы лояльности
        if (!Schema::hasTable('loyalty_settings')) {
            Schema::create('loyalty_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('restaurant_id')->default(1);
                $table->string('key', 50);
                $table->text('value')->nullable();
                $table->timestamps();

                $table->unique(['restaurant_id', 'key']);
            });

            DB::table('loyalty_settings')->insert([
                ['restaurant_id' => 1, 'key' => 'bonus_rate', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
                ['restaurant_id' => 1, 'key' => 'bonus_pay_percent', 'value' => '50', 'created_at' => now(), 'updated_at' => now()],
                ['restaurant_id' => 1, 'key' => 'bonus_expire_days', 'value' => '365', 'created_at' => now(), 'updated_at' => now()],
                ['restaurant_id' => 1, 'key' => 'birthday_bonus_amount', 'value' => '500', 'created_at' => now(), 'updated_at' => now()],
                ['restaurant_id' => 1, 'key' => 'birthday_days_before', 'value' => '7', 'created_at' => now(), 'updated_at' => now()],
                ['restaurant_id' => 1, 'key' => 'birthday_days_after', 'value' => '7', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
        Schema::dropIfExists('bonus_transactions');
        Schema::dropIfExists('promo_code_usages');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('loyalty_levels');
    }
};
