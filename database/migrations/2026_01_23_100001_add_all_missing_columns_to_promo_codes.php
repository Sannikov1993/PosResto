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
        Schema::table('promo_codes', function (Blueprint $table) {
            // Основные поля
            if (!Schema::hasColumn('promo_codes', 'name')) {
                $table->string('name')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'internal_notes')) {
                $table->text('internal_notes')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'value')) {
                $table->decimal('value', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('promo_codes', 'max_discount')) {
                $table->decimal('max_discount', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'min_order_amount')) {
                $table->decimal('min_order_amount', 10, 2)->default(0);
            }

            // Условия применения
            if (!Schema::hasColumn('promo_codes', 'applies_to')) {
                $table->string('applies_to')->default('whole_order');
            }
            if (!Schema::hasColumn('promo_codes', 'applicable_categories')) {
                $table->json('applicable_categories')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'applicable_dishes')) {
                $table->json('applicable_dishes')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'excluded_dishes')) {
                $table->json('excluded_dishes')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'order_types')) {
                $table->json('order_types')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'payment_methods')) {
                $table->json('payment_methods')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'source_channels')) {
                $table->json('source_channels')->nullable();
            }

            // Лимиты использования
            if (!Schema::hasColumn('promo_codes', 'usage_limit')) {
                $table->integer('usage_limit')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'usage_per_customer')) {
                $table->integer('usage_per_customer')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'usage_count')) {
                $table->integer('usage_count')->default(0);
            }

            // Специальные условия
            if (!Schema::hasColumn('promo_codes', 'first_order_only')) {
                $table->boolean('first_order_only')->default(false);
            }
            if (!Schema::hasColumn('promo_codes', 'is_birthday_only')) {
                $table->boolean('is_birthday_only')->default(false);
            }
            if (!Schema::hasColumn('promo_codes', 'birthday_days_before')) {
                $table->integer('birthday_days_before')->default(0);
            }
            if (!Schema::hasColumn('promo_codes', 'birthday_days_after')) {
                $table->integer('birthday_days_after')->default(0);
            }

            // Фильтры
            if (!Schema::hasColumn('promo_codes', 'loyalty_levels')) {
                $table->json('loyalty_levels')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'zones')) {
                $table->json('zones')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'tables_list')) {
                $table->json('tables_list')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'schedule')) {
                $table->json('schedule')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'allowed_customer_ids')) {
                $table->json('allowed_customer_ids')->nullable();
            }

            // Совместимость
            if (!Schema::hasColumn('promo_codes', 'stackable')) {
                $table->boolean('stackable')->default(false);
            }
            if (!Schema::hasColumn('promo_codes', 'priority')) {
                $table->integer('priority')->default(0);
            }
            if (!Schema::hasColumn('promo_codes', 'is_exclusive')) {
                $table->boolean('is_exclusive')->default(false);
            }
            if (!Schema::hasColumn('promo_codes', 'single_use_with_promotions')) {
                $table->boolean('single_use_with_promotions')->default(false);
            }

            // Подарки и бонусы
            if (!Schema::hasColumn('promo_codes', 'gift_dish_id')) {
                $table->unsignedBigInteger('gift_dish_id')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'bonus_settings')) {
                $table->json('bonus_settings')->nullable();
            }

            // Даты
            if (!Schema::hasColumn('promo_codes', 'starts_at')) {
                $table->timestamp('starts_at')->nullable();
            }
            if (!Schema::hasColumn('promo_codes', 'expires_at')) {
                $table->timestamp('expires_at')->nullable();
            }

            // Статусы
            if (!Schema::hasColumn('promo_codes', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('promo_codes', 'is_public')) {
                $table->boolean('is_public')->default(false);
            }
            if (!Schema::hasColumn('promo_codes', 'is_automatic')) {
                $table->boolean('is_automatic')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Не удаляем колонки при откате
    }
};
