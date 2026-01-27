<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            // Добавляем поддержку сотрудников (user_id)
            if (!Schema::hasColumn('push_subscriptions', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            }

            // Дополнительные поля для push
            if (!Schema::hasColumn('push_subscriptions', 'content_encoding')) {
                $table->string('content_encoding', 20)->default('aesgcm')->after('auth');
            }

            if (!Schema::hasColumn('push_subscriptions', 'device_name')) {
                $table->string('device_name', 100)->nullable()->after('content_encoding');
            }

            if (!Schema::hasColumn('push_subscriptions', 'user_agent')) {
                $table->string('user_agent', 500)->nullable()->after('device_name');
            }

            if (!Schema::hasColumn('push_subscriptions', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('is_active');
            }
        });

        // Добавляем индекс для user_id
        Schema::table('push_subscriptions', function (Blueprint $table) {
            if (!Schema::hasIndex('push_subscriptions', 'push_subscriptions_user_id_is_active_index')) {
                $table->index(['user_id', 'is_active']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id',
                'content_encoding',
                'device_name',
                'user_agent',
                'last_used_at',
            ]);
        });
    }
};
