<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            // Retry scheduling
            $table->timestamp('next_retry_at')->nullable()->after('last_attempt_at');
            $table->unsignedTinyInteger('max_attempts')->default(3)->after('attempts');

            // Queue job tracking
            $table->string('job_id')->nullable()->after('channel_data');
            $table->string('job_queue')->nullable()->after('job_id');

            // Index for retry command
            $table->index(['status', 'next_retry_at'], 'notification_logs_retry_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex('notification_logs_retry_index');
            $table->dropColumn(['next_retry_at', 'max_attempts', 'job_id', 'job_queue']);
        });
    }
};
