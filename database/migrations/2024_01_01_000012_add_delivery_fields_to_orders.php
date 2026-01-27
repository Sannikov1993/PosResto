<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Delivery fields
            if (!Schema::hasColumn('orders', 'delivery_address')) {
                $table->string('delivery_address')->nullable();
            }
            if (!Schema::hasColumn('orders', 'delivery_notes')) {
                $table->text('delivery_notes')->nullable();
            }
            if (!Schema::hasColumn('orders', 'delivery_status')) {
                $table->enum('delivery_status', ['pending', 'preparing', 'picked_up', 'in_transit', 'delivered'])
                      ->nullable();
            }
            if (!Schema::hasColumn('orders', 'courier_id')) {
                $table->unsignedBigInteger('courier_id')->nullable();
            }
            if (!Schema::hasColumn('orders', 'picked_up_at')) {
                $table->timestamp('picked_up_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'phone')) {
                $table->string('phone')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_address', 'delivery_notes', 'delivery_status',
                'courier_id', 'picked_up_at', 'delivered_at', 'phone'
            ]);
        });
    }
};
