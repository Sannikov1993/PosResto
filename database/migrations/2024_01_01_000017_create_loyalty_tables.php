<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Уровни лояльности
        Schema::create('loyalty_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->string('name', 50);                    // Бронза, Серебро, Золото
            $table->string('icon', 10)->nullable();        // 🥉🥈🥇
            $table->string('color', 20)->default('#666');  // цвет для UI
            $table->decimal('min_total', 12, 2)->default(0);    // мин. сумма покупок для уровня
            $table->decimal('discount_percent', 5, 2)->default(0); // скидка %
            $table->decimal('cashback_percent', 5, 2)->default(0); // кэшбэк в бонусы %
            $table->decimal('bonus_multiplier', 3, 2)->default(1); // множитель бонусов
            $table->boolean('birthday_bonus')->default(false);     // бонус в день рождения
            $table->decimal('birthday_discount', 5, 2)->default(0); // скидка в ДР
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('restaurant_id');
        });

        // Базовые уровни
        DB::table('loyalty_levels')->insert([
            [
                'restaurant_id' => 1,
                'name' => 'Новичок',
                'icon' => '⭐',
                'color' => '#9ca3af',
                'min_total' => 0,
                'discount_percent' => 0,
                'cashback_percent' => 3,
                'bonus_multiplier' => 1,
                'birthday_bonus' => false,
                'birthday_discount' => 0,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'restaurant_id' => 1,
                'name' => 'Бронза',
                'icon' => '🥉',
                'color' => '#cd7f32',
                'min_total' => 5000,
                'discount_percent' => 3,
                'cashback_percent' => 5,
                'bonus_multiplier' => 1,
                'birthday_bonus' => true,
                'birthday_discount' => 10,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'restaurant_id' => 1,
                'name' => 'Серебро',
                'icon' => '🥈',
                'color' => '#c0c0c0',
                'min_total' => 15000,
                'discount_percent' => 5,
                'cashback_percent' => 7,
                'bonus_multiplier' => 1.5,
                'birthday_bonus' => true,
                'birthday_discount' => 15,
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'restaurant_id' => 1,
                'name' => 'Золото',
                'icon' => '🥇',
                'color' => '#ffd700',
                'min_total' => 50000,
                'discount_percent' => 10,
                'cashback_percent' => 10,
                'bonus_multiplier' => 2,
                'birthday_bonus' => true,
                'birthday_discount' => 20,
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Промокоды
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->string('code', 30)->unique();
            $table->string('name', 100);                    // Название/описание
            $table->enum('type', ['percent', 'fixed', 'bonus'])->default('percent');
            $table->decimal('value', 10, 2);                // значение скидки
            $table->decimal('min_order', 10, 2)->default(0); // мин. сумма заказа
            $table->decimal('max_discount', 10, 2)->nullable(); // макс. скидка (для %)
            $table->integer('usage_limit')->nullable();      // лимит использований
            $table->integer('usage_count')->default(0);      // сколько раз использован
            $table->integer('per_customer_limit')->nullable(); // лимит на клиента
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('first_order_only')->default(false); // только первый заказ
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['restaurant_id', 'code']);
            $table->index(['restaurant_id', 'is_active']);
        });

        // История использования промокодов
        Schema::create('promo_code_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promo_code_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('order_id');
            $table->decimal('discount_amount', 10, 2);
            $table->timestamps();
            
            $table->foreign('promo_code_id')->references('id')->on('promo_codes')->onDelete('cascade');
            $table->index(['promo_code_id', 'customer_id']);
        });

        // Бонусные транзакции
        Schema::create('bonus_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->enum('type', ['earn', 'spend', 'expire', 'manual', 'birthday', 'promo']);
            $table->decimal('amount', 10, 2);               // + начисление, - списание
            $table->decimal('balance_after', 10, 2);        // баланс после
            $table->string('description', 255)->nullable();
            $table->date('expires_at')->nullable();         // срок действия бонусов
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'created_at']);
            $table->index(['restaurant_id', 'type']);
        });

        // Добавляем поля в customers если их нет
        if (!Schema::hasColumn('customers', 'loyalty_level_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedBigInteger('loyalty_level_id')->nullable()->after('restaurant_id');
                $table->decimal('bonus_balance', 10, 2)->default(0)->after('total_spent');
                $table->date('birthday')->nullable()->after('email');
                $table->boolean('birthday_used_this_year')->default(false);
            });
        }

        // Настройки программы лояльности
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->string('key', 50);
            $table->text('value')->nullable();
            $table->timestamps();
            
            $table->unique(['restaurant_id', 'key']);
        });

        // Базовые настройки
        DB::table('loyalty_settings')->insert([
            ['restaurant_id' => 1, 'key' => 'bonus_rate', 'value' => '1', 'created_at' => now(), 'updated_at' => now()], // 1₽ = 1 бонус
            ['restaurant_id' => 1, 'key' => 'bonus_pay_percent', 'value' => '50', 'created_at' => now(), 'updated_at' => now()], // макс % оплаты бонусами
            ['restaurant_id' => 1, 'key' => 'bonus_expire_days', 'value' => '365', 'created_at' => now(), 'updated_at' => now()], // срок действия
            ['restaurant_id' => 1, 'key' => 'birthday_bonus_amount', 'value' => '500', 'created_at' => now(), 'updated_at' => now()], // бонусы в ДР
            ['restaurant_id' => 1, 'key' => 'birthday_days_before', 'value' => '7', 'created_at' => now(), 'updated_at' => now()], // дней до ДР
            ['restaurant_id' => 1, 'key' => 'birthday_days_after', 'value' => '7', 'created_at' => now(), 'updated_at' => now()], // дней после ДР
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
        Schema::dropIfExists('bonus_transactions');
        Schema::dropIfExists('promo_code_usages');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('loyalty_levels');
        
        if (Schema::hasColumn('customers', 'loyalty_level_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn(['loyalty_level_id', 'bonus_balance', 'birthday', 'birthday_used_this_year']);
            });
        }
    }
};
