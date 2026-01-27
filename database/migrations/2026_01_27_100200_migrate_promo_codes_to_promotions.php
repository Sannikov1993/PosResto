<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Перенос данных из promo_codes в promotions
     * PromoCode становится Promotion с activation_type = 'by_code'
     */
    public function up(): void
    {
        // Проверяем, есть ли таблица promo_codes
        if (!Schema::hasTable('promo_codes')) {
            return;
        }

        $promoCodes = DB::table('promo_codes')->whereNull('deleted_at')->get();

        foreach ($promoCodes as $promoCode) {
            // Маппинг типа: percent -> discount_percent, fixed -> discount_fixed
            $typeMap = [
                'percent' => 'discount_percent',
                'fixed' => 'discount_fixed',
                'bonus' => 'bonus',
            ];
            $type = $typeMap[$promoCode->type] ?? $promoCode->type;

            // Если есть gift_dish_id - это подарок
            if ($promoCode->gift_dish_id) {
                $type = 'gift';
            }

            DB::table('promotions')->insert([
                'restaurant_id' => $promoCode->restaurant_id,
                'name' => $promoCode->name,
                'slug' => \Illuminate\Support\Str::slug($promoCode->name . '-' . $promoCode->code),
                'code' => $promoCode->code,
                'description' => $promoCode->description,
                'internal_notes' => $promoCode->internal_notes,
                'type' => $type,
                'reward_type' => $promoCode->gift_dish_id ? 'gift' : 'discount',
                'activation_type' => 'by_code',
                'applies_to' => $promoCode->applies_to ?? 'whole_order',
                'discount_value' => $promoCode->value,
                'max_discount' => $promoCode->max_discount,
                'min_order_amount' => $promoCode->min_order_amount,
                'applicable_categories' => $promoCode->applicable_categories,
                'applicable_dishes' => $promoCode->applicable_dishes,
                'excluded_dishes' => $promoCode->excluded_dishes,
                'excluded_categories' => $promoCode->excluded_categories,
                'gift_dish_id' => $promoCode->gift_dish_id,
                'starts_at' => $promoCode->starts_at,
                'ends_at' => $promoCode->expires_at,
                'schedule' => $promoCode->schedule,
                'bonus_settings' => $promoCode->bonus_settings,
                'usage_limit' => $promoCode->usage_limit,
                'usage_per_customer' => $promoCode->usage_per_customer,
                'usage_count' => $promoCode->usage_count,
                'order_types' => $promoCode->order_types,
                'payment_methods' => $promoCode->payment_methods,
                'source_channels' => $promoCode->source_channels,
                'stackable' => $promoCode->stackable,
                'is_exclusive' => $promoCode->is_exclusive,
                'single_use_with_promotions' => $promoCode->single_use_with_promotions,
                'priority' => $promoCode->priority,
                'is_active' => $promoCode->is_active,
                'is_public' => $promoCode->is_public ?? false,
                'is_automatic' => false, // Промокоды не автоматические
                'is_first_order_only' => $promoCode->first_order_only ?? false,
                'is_birthday_only' => $promoCode->is_birthday_only ?? false,
                'birthday_days_before' => $promoCode->birthday_days_before,
                'birthday_days_after' => $promoCode->birthday_days_after,
                'loyalty_levels' => $promoCode->loyalty_levels,
                'allowed_customer_ids' => $promoCode->allowed_customer_ids,
                'zones' => $promoCode->zones,
                'tables_list' => $promoCode->tables_list,
                'created_at' => $promoCode->created_at,
                'updated_at' => now(),
            ]);
        }

        // Переносим использования промокодов
        if (Schema::hasTable('promo_code_usages') && Schema::hasTable('promotion_usages')) {
            $usages = DB::table('promo_code_usages')->get();

            foreach ($usages as $usage) {
                // Находим соответствующий промокод
                $promoCode = DB::table('promo_codes')->where('id', $usage->promo_code_id)->first();
                if (!$promoCode) continue;

                // Находим созданную акцию по коду
                $promotion = DB::table('promotions')->where('code', $promoCode->code)->first();
                if (!$promotion) continue;

                DB::table('promotion_usages')->insert([
                    'promotion_id' => $promotion->id,
                    'customer_id' => $usage->customer_id,
                    'order_id' => $usage->order_id,
                    'discount_amount' => $usage->discount_amount,
                    'created_at' => $usage->created_at,
                    'updated_at' => $usage->updated_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем перенесённые промокоды (с activation_type = 'by_code')
        DB::table('promotions')->where('activation_type', 'by_code')->delete();
    }
};
