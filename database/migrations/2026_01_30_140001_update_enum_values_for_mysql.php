<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'mysql') {
            return; // SQLite uses string types, no enum constraints
        }

        // Update promotions.type enum
        DB::statement("ALTER TABLE promotions MODIFY type ENUM(
            'discount_percent',
            'discount_fixed',
            'buy_x_get_y',
            'free_delivery',
            'gift',
            'combo',
            'happy_hour',
            'first_order',
            'birthday',
            'bonus_multiplier',
            'progressive_discount'
        ) DEFAULT 'discount_percent'");

        // Update users.role enum
        DB::statement("ALTER TABLE users MODIFY role ENUM(
            'super_admin',
            'owner',
            'admin',
            'manager',
            'waiter',
            'cook',
            'cashier',
            'courier',
            'hostess',
            'limited'
        ) DEFAULT 'waiter'");
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'mysql') {
            return;
        }

        // Revert to original enums
        DB::statement("ALTER TABLE promotions MODIFY type ENUM(
            'discount_percent',
            'discount_fixed',
            'buy_x_get_y',
            'free_delivery',
            'gift',
            'combo',
            'happy_hour',
            'first_order',
            'birthday'
        ) DEFAULT 'discount_percent'");

        DB::statement("ALTER TABLE users MODIFY role ENUM(
            'super_admin',
            'owner',
            'admin',
            'manager',
            'waiter',
            'cook',
            'cashier',
            'courier',
            'hostess'
        ) DEFAULT 'waiter'");
    }
};
