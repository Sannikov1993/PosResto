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
        Schema::table('restaurants', function (Blueprint $table) {
            // Telegram Guest Bot (white-label per restaurant)
            $table->string('telegram_bot_token')->nullable();
            $table->string('telegram_bot_username', 100)->nullable();
            $table->string('telegram_bot_id', 20)->nullable(); // For webhook routing
            $table->string('telegram_webhook_secret', 64)->nullable(); // For webhook verification
            $table->boolean('telegram_bot_active')->default(false);
            $table->timestamp('telegram_bot_verified_at')->nullable();

            // Index for webhook routing by bot_id
            $table->index('telegram_bot_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropIndex(['telegram_bot_id']);
            $table->dropColumn([
                'telegram_bot_token',
                'telegram_bot_username',
                'telegram_bot_id',
                'telegram_webhook_secret',
                'telegram_bot_active',
                'telegram_bot_verified_at',
            ]);
        });
    }
};
