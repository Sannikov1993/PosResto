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

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE order_items MODIFY status ENUM('pending', 'cooking', 'ready', 'served', 'pending_cancel', 'cancelled') DEFAULT 'pending'");
        }
        // SQLite uses string type without enum constraints
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // First update any pending_cancel or cancelled to pending
        DB::table('order_items')
            ->whereIn('status', ['pending_cancel', 'cancelled'])
            ->update(['status' => 'pending']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE order_items MODIFY status ENUM('pending', 'cooking', 'ready', 'served') DEFAULT 'pending'");
        }
    }
};
