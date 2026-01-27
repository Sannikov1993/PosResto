<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_schedules', function (Blueprint $table) {
            $table->timestamp('reminder_24h_sent_at')->nullable();
            $table->timestamp('reminder_1h_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staff_schedules', function (Blueprint $table) {
            $table->dropColumn(['reminder_24h_sent_at', 'reminder_1h_sent_at']);
        });
    }
};
