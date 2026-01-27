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
        // Таблица подписок на Web Push уведомления
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20)->nullable()->index();
            $table->string('endpoint', 500)->unique();
            $table->string('p256dh', 255)->nullable();
            $table->string('auth', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['customer_id', 'is_active']);
            $table->index(['phone', 'is_active']);
        });

        // Добавляем telegram_chat_id к клиентам
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'telegram_chat_id')) {
                $table->string('telegram_chat_id', 50)->nullable()->after('phone');
                $table->string('telegram_username', 100)->nullable()->after('telegram_chat_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'telegram_chat_id')) {
                $table->dropColumn(['telegram_chat_id', 'telegram_username']);
            }
        });
    }
};
