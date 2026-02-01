<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Column now added in the main orders table migration
        // This migration exists for backwards compatibility
        if (!Schema::hasColumn('orders', 'paid_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('paid_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Don't drop - it's now part of main migration
    }
};
