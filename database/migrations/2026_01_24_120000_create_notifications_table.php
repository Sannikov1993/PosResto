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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('type'); // shift_reminder, schedule_change, salary_paid, etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Additional data
            $table->json('channels')->nullable(); // ['email', 'telegram', 'push']
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('delivery_status')->nullable(); // Status per channel
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'type']);
        });

        // Add notification settings to users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->string('telegram_chat_id')->nullable();
            }
            if (!Schema::hasColumn('users', 'telegram_username')) {
                $table->string('telegram_username')->nullable();
            }
            if (!Schema::hasColumn('users', 'notification_settings')) {
                $table->json('notification_settings')->nullable();
            }
            if (!Schema::hasColumn('users', 'push_token')) {
                $table->string('push_token')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_username', 'notification_settings', 'push_token']);
        });
    }
};
