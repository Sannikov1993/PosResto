<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // Add 'new', 'sent', 'voided', 'pending_cancel' to order_items.status enum
            DB::statement("ALTER TABLE order_items MODIFY status ENUM('new', 'pending', 'sent', 'cooking', 'ready', 'served', 'pending_cancel', 'cancelled', 'voided') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE order_items MODIFY status ENUM('pending', 'cooking', 'ready', 'served', 'pending_cancel', 'cancelled') DEFAULT 'pending'");
        }
    }
};
