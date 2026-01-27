<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optimize work_sessions for active session lookups
        Schema::table('work_sessions', function (Blueprint $table) {
            // For finding active sessions: whereNull('clock_out')->where('status', 'active')
            $table->index(['user_id', 'restaurant_id', 'status', 'clock_out'], 'ws_active_session_idx');
        });

        // Optimize attendance_events for user history queries
        Schema::table('attendance_events', function (Blueprint $table) {
            // For user history with date filtering
            $table->index(['user_id', 'event_time'], 'ae_user_history_idx');
        });
    }

    public function down(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropIndex('ws_active_session_idx');
        });

        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropIndex('ae_user_history_idx');
        });
    }
};
