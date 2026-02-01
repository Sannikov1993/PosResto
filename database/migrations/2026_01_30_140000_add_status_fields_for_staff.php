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

        // Add status to work_sessions if not exists
        if (!Schema::hasColumn('work_sessions', 'status')) {
            Schema::table('work_sessions', function (Blueprint $table) use ($driver) {
                if ($driver === 'mysql') {
                    $table->enum('status', ['active', 'completed', 'auto_closed'])->default('active')->after('clock_out_ip');
                } else {
                    $table->string('status', 20)->default('active')->after('clock_out_ip');
                }
            });
        } elseif ($driver === 'mysql') {
            // Update enum if column exists
            DB::statement("ALTER TABLE work_sessions MODIFY status ENUM('active', 'completed', 'auto_closed') DEFAULT 'active'");
        }

        // Update salary_payments status enum for MySQL
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE salary_payments MODIFY status ENUM('pending', 'paid', 'completed', 'cancelled') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (Schema::hasColumn('work_sessions', 'status')) {
            Schema::table('work_sessions', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if ($driver === 'mysql') {
            DB::table('salary_payments')->where('status', 'completed')->update(['status' => 'paid']);
            DB::statement("ALTER TABLE salary_payments MODIFY status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending'");
        }
    }
};
