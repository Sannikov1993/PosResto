<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add reminder fields to staff_schedules
        Schema::table('staff_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_schedules', 'clock_in_reminder_sent_at')) {
                $table->timestamp('clock_in_reminder_sent_at')->nullable()->after('reminder_1h_sent_at');
            }
            if (!Schema::hasColumn('staff_schedules', 'clock_out_reminder_sent_at')) {
                $table->timestamp('clock_out_reminder_sent_at')->nullable()->after('clock_in_reminder_sent_at');
            }
        });

        // Add unclosed session reminder field to work_sessions
        Schema::table('work_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('work_sessions', 'unclosed_reminder_sent_at')) {
                $table->timestamp('unclosed_reminder_sent_at')->nullable()->after('correction_reason');
            }
        });

        // Create biometric credentials table for WebAuthn
        Schema::create('biometric_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('credential_id', 500)->unique();
            $table->text('public_key');
            $table->string('name', 100)->nullable(); // Device/credential name
            $table->string('device_type', 50)->nullable(); // fingerprint, face, etc.
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->string('aaguid', 36)->nullable(); // Authenticator AAGUID
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // Add biometric requirement settings to users
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'require_biometric_clock')) {
                $table->boolean('require_biometric_clock')->default(false)->after('notification_settings');
            }
        });

        // Add biometric verification to work_sessions
        Schema::table('work_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('work_sessions', 'clock_in_verified_by')) {
                $table->string('clock_in_verified_by', 50)->nullable()->after('clock_in_ip');
            }
            if (!Schema::hasColumn('work_sessions', 'clock_out_verified_by')) {
                $table->string('clock_out_verified_by', 50)->nullable()->after('clock_out_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_schedules', function (Blueprint $table) {
            $table->dropColumn(['clock_in_reminder_sent_at', 'clock_out_reminder_sent_at']);
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropColumn(['unclosed_reminder_sent_at', 'clock_in_verified_by', 'clock_out_verified_by']);
        });

        Schema::dropIfExists('biometric_credentials');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('require_biometric_clock');
        });
    }
};
