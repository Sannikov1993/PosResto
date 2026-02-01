<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Добавляет restaurant_id в таблицы для полной tenant-изоляции:
 * - order_items (через order.restaurant_id)
 * - delivery_orders (новая колонка)
 * - delivery_order_items (через delivery_order.restaurant_id)
 * - ingredient_stocks (через warehouse.restaurant_id)
 * - modifier_options (через modifier.restaurant_id)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. order_items
        if (!Schema::hasColumn('order_items', 'restaurant_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreignId('restaurant_id')->nullable()->after('id')
                    ->constrained('restaurants')->nullOnDelete();
                $table->index('restaurant_id');
            });

            // Заполняем из orders (совместимо с SQLite и MySQL)
            $items = DB::table('order_items')
                ->whereNull('restaurant_id')
                ->select('id', 'order_id')
                ->get();

            foreach ($items as $item) {
                $order = DB::table('orders')->where('id', $item->order_id)->first();
                if ($order) {
                    DB::table('order_items')
                        ->where('id', $item->id)
                        ->update(['restaurant_id' => $order->restaurant_id]);
                }
            }
        }

        // 2. delivery_orders
        if (!Schema::hasColumn('delivery_orders', 'restaurant_id')) {
            Schema::table('delivery_orders', function (Blueprint $table) {
                $table->foreignId('restaurant_id')->nullable()->after('id')
                    ->constrained('restaurants')->nullOnDelete();
                $table->index('restaurant_id');
            });

            // Заполняем из delivery_zones или customers
            $orders = DB::table('delivery_orders')
                ->whereNull('restaurant_id')
                ->select('id', 'delivery_zone_id', 'customer_id')
                ->get();

            foreach ($orders as $order) {
                $restaurantId = null;
                if ($order->delivery_zone_id) {
                    $zone = DB::table('delivery_zones')->where('id', $order->delivery_zone_id)->first();
                    $restaurantId = $zone?->restaurant_id;
                }
                if (!$restaurantId && $order->customer_id) {
                    $customer = DB::table('customers')->where('id', $order->customer_id)->first();
                    $restaurantId = $customer?->restaurant_id;
                }
                if ($restaurantId) {
                    DB::table('delivery_orders')
                        ->where('id', $order->id)
                        ->update(['restaurant_id' => $restaurantId]);
                }
            }
        }

        // 3. delivery_order_items
        if (!Schema::hasColumn('delivery_order_items', 'restaurant_id')) {
            Schema::table('delivery_order_items', function (Blueprint $table) {
                $table->foreignId('restaurant_id')->nullable()->after('id')
                    ->constrained('restaurants')->nullOnDelete();
                $table->index('restaurant_id');
            });

            // Заполняем из delivery_orders
            $items = DB::table('delivery_order_items')
                ->whereNull('restaurant_id')
                ->select('id', 'delivery_order_id')
                ->get();

            foreach ($items as $item) {
                $order = DB::table('delivery_orders')->where('id', $item->delivery_order_id)->first();
                if ($order) {
                    DB::table('delivery_order_items')
                        ->where('id', $item->id)
                        ->update(['restaurant_id' => $order->restaurant_id]);
                }
            }
        }

        // 4. ingredient_stocks (renumbered)
        if (!Schema::hasColumn('ingredient_stocks', 'restaurant_id')) {
            Schema::table('ingredient_stocks', function (Blueprint $table) {
                $table->foreignId('restaurant_id')->nullable()->after('id')
                    ->constrained('restaurants')->nullOnDelete();
                $table->index('restaurant_id');
            });

            // Заполняем из warehouses
            $stocks = DB::table('ingredient_stocks')
                ->whereNull('restaurant_id')
                ->select('id', 'warehouse_id')
                ->get();

            foreach ($stocks as $stock) {
                $warehouse = DB::table('warehouses')->where('id', $stock->warehouse_id)->first();
                if ($warehouse) {
                    DB::table('ingredient_stocks')
                        ->where('id', $stock->id)
                        ->update(['restaurant_id' => $warehouse->restaurant_id]);
                }
            }
        }

        // 5. modifier_options
        if (!Schema::hasColumn('modifier_options', 'restaurant_id')) {
            Schema::table('modifier_options', function (Blueprint $table) {
                $table->foreignId('restaurant_id')->nullable()->after('tenant_id')
                    ->constrained('restaurants')->nullOnDelete();
                $table->index('restaurant_id');
            });

            // Заполняем из modifiers
            $options = DB::table('modifier_options')
                ->whereNull('restaurant_id')
                ->select('id', 'modifier_id')
                ->get();

            foreach ($options as $option) {
                $modifier = DB::table('modifiers')->where('id', $option->modifier_id)->first();
                if ($modifier) {
                    DB::table('modifier_options')
                        ->where('id', $option->id)
                        ->update(['restaurant_id' => $modifier->restaurant_id]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn('restaurant_id');
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn('restaurant_id');
        });

        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn('restaurant_id');
        });

        Schema::table('ingredient_stocks', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn('restaurant_id');
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn('restaurant_id');
        });
    }
};
