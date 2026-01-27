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
        $columns = [
            'applies_to' => fn($t) => $t->string('applies_to')->default('whole_order'),
            'applicable_categories' => fn($t) => $t->json('applicable_categories')->nullable(),
            'applicable_dishes' => fn($t) => $t->json('applicable_dishes')->nullable(),
            'excluded_dishes' => fn($t) => $t->json('excluded_dishes')->nullable(),
            'order_types' => fn($t) => $t->json('order_types')->nullable(),
            'payment_methods' => fn($t) => $t->json('payment_methods')->nullable(),
            'source_channels' => fn($t) => $t->json('source_channels')->nullable(),
            'is_birthday_only' => fn($t) => $t->boolean('is_birthday_only')->default(false),
            'birthday_days_before' => fn($t) => $t->integer('birthday_days_before')->default(0),
            'birthday_days_after' => fn($t) => $t->integer('birthday_days_after')->default(0),
            'loyalty_levels' => fn($t) => $t->json('loyalty_levels')->nullable(),
            'zones' => fn($t) => $t->json('zones')->nullable(),
            'tables_list' => fn($t) => $t->json('tables_list')->nullable(),
            'schedule' => fn($t) => $t->json('schedule')->nullable(),
            'stackable' => fn($t) => $t->boolean('stackable')->default(false),
            'priority' => fn($t) => $t->integer('priority')->default(0),
            'is_exclusive' => fn($t) => $t->boolean('is_exclusive')->default(false),
            'single_use_with_promotions' => fn($t) => $t->boolean('single_use_with_promotions')->default(false),
            'gift_dish_id' => fn($t) => $t->unsignedBigInteger('gift_dish_id')->nullable(),
            'bonus_settings' => fn($t) => $t->json('bonus_settings')->nullable(),
            'internal_notes' => fn($t) => $t->text('internal_notes')->nullable(),
            'description' => fn($t) => $t->text('description')->nullable(),
        ];

        foreach ($columns as $column => $definition) {
            if (!Schema::hasColumn('promo_codes', $column)) {
                Schema::table('promo_codes', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $columns = [
                'applies_to', 'applicable_categories', 'applicable_dishes', 'excluded_dishes',
                'order_types', 'payment_methods', 'source_channels', 'is_birthday_only',
                'birthday_days_before', 'birthday_days_after', 'loyalty_levels', 'zones',
                'tables_list', 'schedule', 'stackable', 'priority', 'is_exclusive',
                'single_use_with_promotions', 'gift_dish_id', 'bonus_settings', 'internal_notes',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('promo_codes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
