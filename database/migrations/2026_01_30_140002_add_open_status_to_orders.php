<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'mysql') {
            return;
        }

        // Add 'open' to orders.status enum
        DB::statement("ALTER TABLE orders MODIFY status ENUM(
            'new',
            'open',
            'confirmed',
            'cooking',
            'ready',
            'served',
            'delivering',
            'completed',
            'cancelled'
        ) DEFAULT 'new'");
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'mysql') {
            return;
        }

        // Revert
        DB::table('orders')->where('status', 'open')->update(['status' => 'new']);
        
        DB::statement("ALTER TABLE orders MODIFY status ENUM(
            'new',
            'confirmed',
            'cooking',
            'ready',
            'served',
            'delivering',
            'completed',
            'cancelled'
        ) DEFAULT 'new'");
    }
};
