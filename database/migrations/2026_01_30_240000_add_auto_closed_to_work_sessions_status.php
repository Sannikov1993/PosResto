<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // Extend work_sessions.status enum to include 'auto_closed'
            DB::statement("ALTER TABLE work_sessions MODIFY status ENUM('active', 'completed', 'corrected', 'auto_closed') DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE work_sessions MODIFY status ENUM('active', 'completed', 'corrected') DEFAULT 'active'");
        }
    }
};
