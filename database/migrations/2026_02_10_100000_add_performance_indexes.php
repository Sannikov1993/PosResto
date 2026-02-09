<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('payment_status');
            $table->index(['restaurant_id', 'status', 'created_at'], 'orders_restaurant_status_created_idx');
            $table->index(['restaurant_id', 'customer_id', 'status'], 'orders_restaurant_customer_status_idx');
            $table->index(['restaurant_id', 'user_id', 'status', 'created_at'], 'orders_restaurant_user_status_created_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['restaurant_id', 'last_order_at'], 'customers_restaurant_last_order_idx');
            $table->index(['restaurant_id', 'is_blacklisted', 'total_orders'], 'customers_restaurant_blacklist_orders_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['dish_id', 'order_id'], 'order_items_dish_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_restaurant_status_created_idx');
            $table->dropIndex('orders_restaurant_customer_status_idx');
            $table->dropIndex('orders_restaurant_user_status_created_idx');
            $table->dropIndex(['payment_status']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_restaurant_last_order_idx');
            $table->dropIndex('customers_restaurant_blacklist_orders_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_dish_order_idx');
        });
    }
};
