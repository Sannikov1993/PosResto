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
        Schema::table('customers', function (Blueprint $table) {
            // Telegram linked timestamp (telegram_chat_id and telegram_username already exist)
            if (!Schema::hasColumn('customers', 'telegram_linked_at')) {
                $table->timestamp('telegram_linked_at')->nullable();
            }

            // Notification preferences per channel and type
            // Format: { "reservation": ["telegram", "email"], "marketing": [], "reminders": ["sms"] }
            if (!Schema::hasColumn('customers', 'notification_preferences')) {
                $table->json('notification_preferences')->nullable();
            }

            // Preferred notification channel (fallback when no specific preference)
            if (!Schema::hasColumn('customers', 'preferred_channel')) {
                $table->string('preferred_channel', 20)->nullable();
            }

            // Consent for Telegram notifications
            if (!Schema::hasColumn('customers', 'telegram_consent')) {
                $table->boolean('telegram_consent')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('customers', 'telegram_linked_at')) {
                $columns[] = 'telegram_linked_at';
            }
            if (Schema::hasColumn('customers', 'notification_preferences')) {
                $columns[] = 'notification_preferences';
            }
            if (Schema::hasColumn('customers', 'preferred_channel')) {
                $columns[] = 'preferred_channel';
            }
            if (Schema::hasColumn('customers', 'telegram_consent')) {
                $columns[] = 'telegram_consent';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
