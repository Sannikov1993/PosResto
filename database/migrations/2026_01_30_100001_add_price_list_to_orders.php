<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('price_list_id')->nullable()->after('restaurant_id')
                ->constrained('price_lists')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('price_list_id')->nullable()->after('order_id')
                ->constrained('price_lists')->nullOnDelete();
            $table->decimal('base_price', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_list_id');
            $table->dropColumn('base_price');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_list_id');
        });
    }
};
