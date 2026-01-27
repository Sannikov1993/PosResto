<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('served_at');
            }
            if (!Schema::hasColumn('order_items', 'guest_id')) {
                $table->unsignedBigInteger('guest_id')->nullable()->after('guest_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'sent_at')) {
                $table->dropColumn('sent_at');
            }
            if (Schema::hasColumn('order_items', 'guest_id')) {
                $table->dropColumn('guest_id');
            }
        });
    }
};
