<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Добавляет restaurant_id в delivery_order_items для полной tenant-изоляции
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('delivery_order_items', 'restaurant_id')) {
            Schema::table('delivery_order_items', function (Blueprint $table) {
                $table->foreignId('restaurant_id')->nullable()->after('id')
                    ->constrained('restaurants')->nullOnDelete();
                $table->index('restaurant_id');
            });

            // Заполняем из delivery_orders (SQLite-совместимо)
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
    }

    public function down(): void
    {
        if (Schema::hasColumn('delivery_order_items', 'restaurant_id')) {
            Schema::table('delivery_order_items', function (Blueprint $table) {
                $table->dropForeign(['restaurant_id']);
                $table->dropColumn('restaurant_id');
            });
        }
    }
};
