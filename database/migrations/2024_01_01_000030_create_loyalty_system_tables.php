<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Акции и спецпредложения
        if (!Schema::hasTable('promotions')) {
            Schema::create('promotions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('image')->nullable();

                // Тип акции
                $table->enum('type', [
                    'discount_percent',
                    'discount_fixed',
                    'buy_x_get_y',
                    'free_delivery',
                    'gift',
                    'combo',
                    'happy_hour',
                    'first_order',
                    'birthday',
                ])->default('discount_percent');

                // Значение скидки
                $table->decimal('discount_value', 10, 2)->default(0);
                $table->decimal('max_discount', 10, 2)->nullable();

                // Условия применения
                $table->decimal('min_order_amount', 10, 2)->default(0);
                $table->integer('min_items_count')->default(0);
                $table->json('applicable_categories')->nullable();
                $table->json('applicable_dishes')->nullable();
                $table->json('excluded_dishes')->nullable();

                // Для buy_x_get_y
                $table->integer('buy_quantity')->nullable();
                $table->integer('get_quantity')->nullable();
                $table->unsignedBigInteger('gift_dish_id')->nullable();

                // Время действия
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();

                // Расписание (для happy_hour)
                $table->json('schedule')->nullable();

                // Ограничения
                $table->integer('usage_limit')->nullable();
                $table->integer('usage_per_customer')->nullable();
                $table->integer('usage_count')->default(0);

                // Применимость
                $table->json('order_types')->nullable();
                $table->boolean('stackable')->default(false);
                $table->integer('priority')->default(0);

                $table->boolean('is_active')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->integer('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Промокоды - модифицируем существующую таблицу
        if (Schema::hasTable('promo_codes')) {
            // Добавляем недостающие поля если их нет
            Schema::table('promo_codes', function (Blueprint $table) {
                if (!Schema::hasColumn('promo_codes', 'promotion_id')) {
                    $table->unsignedBigInteger('promotion_id')->nullable()->after('restaurant_id');
                }
                if (!Schema::hasColumn('promo_codes', 'allowed_customer_ids')) {
                    $table->json('allowed_customer_ids')->nullable();
                }
                if (!Schema::hasColumn('promo_codes', 'starts_at')) {
                    $table->timestamp('starts_at')->nullable();
                }
                if (!Schema::hasColumn('promo_codes', 'is_public')) {
                    $table->boolean('is_public')->default(false);
                }
                if (!Schema::hasColumn('promo_codes', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        } else {
            Schema::create('promo_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
                $table->unsignedBigInteger('promotion_id')->nullable();

                $table->string('code', 50);
                $table->string('name');
                $table->text('description')->nullable();

                $table->enum('type', [
                    'discount_percent',
                    'discount_fixed',
                    'free_delivery',
                    'gift',
                    'bonus_multiply',
                    'bonus_add',
                    'cashback',
                ])->default('discount_percent');

                $table->decimal('value', 10, 2)->default(0);
                $table->decimal('max_discount', 10, 2)->nullable();
                $table->decimal('min_order_amount', 10, 2)->default(0);

                $table->integer('usage_limit')->nullable();
                $table->integer('usage_per_customer')->default(1);
                $table->integer('usage_count')->default(0);

                $table->boolean('first_order_only')->default(false);
                $table->json('allowed_customer_ids')->nullable();

                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();

                $table->boolean('is_active')->default(true);
                $table->boolean('is_public')->default(false);

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['restaurant_id', 'code']);
            });
        }

        // Использование промокодов
        if (!Schema::hasTable('promo_code_usages')) {
            Schema::create('promo_code_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('promo_code_id')->constrained()->onDelete('cascade');
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->decimal('discount_amount', 10, 2)->default(0);
                $table->timestamps();
            });
        }

        // Бонусные транзакции
        if (!Schema::hasTable('bonus_transactions')) {
            Schema::create('bonus_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
                $table->foreignId('customer_id')->constrained()->onDelete('cascade');
                $table->unsignedBigInteger('order_id')->nullable();

                $table->enum('type', [
                    'earn',
                    'spend',
                    'bonus',
                    'refund',
                    'expire',
                    'manual',
                    'referral',
                    'birthday',
                    'registration',
                    'promo',
                ]);

                $table->decimal('amount', 10, 2);
                $table->decimal('balance_after', 10, 2);
                $table->string('description')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();

                $table->timestamps();
            });
        }

        // Настройки бонусной программы
        if (!Schema::hasTable('bonus_settings')) {
            Schema::create('bonus_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');

                $table->boolean('is_enabled')->default(true);
                $table->string('currency_name')->default('бонусов');
                $table->string('currency_symbol')->default('B');

                $table->decimal('earn_rate', 5, 2)->default(5);
                $table->decimal('min_order_for_earn', 10, 2)->default(0);
                $table->integer('earn_rounding')->default(1);

                $table->decimal('spend_rate', 5, 2)->default(100);
                $table->decimal('min_spend_amount', 10, 2)->default(100);
                $table->decimal('bonus_to_ruble', 5, 2)->default(1);

                $table->integer('expiry_days')->nullable();
                $table->boolean('notify_before_expiry')->default(true);
                $table->integer('notify_days_before')->default(7);

                $table->decimal('registration_bonus', 10, 2)->default(0);
                $table->decimal('birthday_bonus', 10, 2)->default(0);
                $table->decimal('referral_bonus', 10, 2)->default(0);
                $table->decimal('referral_friend_bonus', 10, 2)->default(0);

                $table->timestamps();
            });
        }

        // Добавим поля бонусов к клиентам если их нет
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'bonus_balance')) {
                $table->decimal('bonus_balance', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('customers', 'birthday')) {
                $table->date('birthday')->nullable();
            }
            if (!Schema::hasColumn('customers', 'referral_code')) {
                $table->string('referral_code', 20)->nullable();
            }
            if (!Schema::hasColumn('customers', 'referred_by')) {
                $table->unsignedBigInteger('referred_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('customers', 'bonus_balance')) $columns[] = 'bonus_balance';
            if (Schema::hasColumn('customers', 'birthday')) $columns[] = 'birthday';
            if (Schema::hasColumn('customers', 'referral_code')) $columns[] = 'referral_code';
            if (Schema::hasColumn('customers', 'referred_by')) $columns[] = 'referred_by';
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('bonus_settings');
        Schema::dropIfExists('bonus_transactions');
        Schema::dropIfExists('promo_code_usages');
        Schema::dropIfExists('promotions');
    }
};
