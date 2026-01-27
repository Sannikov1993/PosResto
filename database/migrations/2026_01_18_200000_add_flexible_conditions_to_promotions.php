<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'reward_type' => fn($t) => $t->string('reward_type')->default('discount'),
            'applies_to' => fn($t) => $t->string('applies_to')->default('whole_order'),
            'conditions' => fn($t) => $t->json('conditions')->nullable(),
            'bonus_settings' => fn($t) => $t->json('bonus_settings')->nullable(),
            'payment_methods' => fn($t) => $t->json('payment_methods')->nullable(),
            'source_channels' => fn($t) => $t->json('source_channels')->nullable(),
            'is_first_order_only' => fn($t) => $t->boolean('is_first_order_only')->default(false),
            'is_birthday_only' => fn($t) => $t->boolean('is_birthday_only')->default(false),
            'requires_promo_code' => fn($t) => $t->boolean('requires_promo_code')->default(false),
            'loyalty_levels' => fn($t) => $t->json('loyalty_levels')->nullable(),
            'excluded_customers' => fn($t) => $t->json('excluded_customers')->nullable(),
            'zones' => fn($t) => $t->json('zones')->nullable(),
            'tables_list' => fn($t) => $t->json('tables_list')->nullable(),
            'auto_apply' => fn($t) => $t->boolean('auto_apply')->default(true),
            'is_exclusive' => fn($t) => $t->boolean('is_exclusive')->default(false),
            'promo_text' => fn($t) => $t->text('promo_text')->nullable(),
            'internal_notes' => fn($t) => $t->text('internal_notes')->nullable(),
        ];

        foreach ($columns as $column => $definition) {
            if (!Schema::hasColumn('promotions', $column)) {
                Schema::table('promotions', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }
    }

    public function down(): void
    {
        $columns = [
            'reward_type', 'applies_to', 'conditions', 'bonus_settings',
            'payment_methods', 'source_channels', 'is_first_order_only',
            'is_birthday_only', 'requires_promo_code', 'loyalty_levels',
            'excluded_customers', 'zones', 'tables_list', 'auto_apply',
            'is_exclusive', 'promo_text', 'internal_notes',
        ];

        foreach ($columns as $col) {
            if (Schema::hasColumn('promotions', $col)) {
                Schema::table('promotions', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
