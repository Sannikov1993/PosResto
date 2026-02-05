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
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_id')->nullable()->constrained()->onDelete('cascade');

            // Основная информация
            $table->string('name');
            $table->text('description')->nullable();

            // Аутентификация
            $table->string('api_key', 64)->unique();
            $table->string('api_secret', 128);
            $table->string('api_key_prefix', 16)->index(); // Для быстрого поиска по префиксу

            // Права доступа
            $table->json('scopes')->nullable(); // ['menu:read', 'orders:write', ...]

            // Rate Limiting
            $table->string('rate_plan', 32)->default('free'); // free, business, enterprise
            $table->integer('custom_rate_limit')->nullable(); // Кастомный лимит (requests/min)

            // Статус
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivation_reason')->nullable();

            // Метаданные
            $table->string('type', 32)->default('integration'); // integration, website, mobile, kiosk, aggregator
            $table->string('environment', 16)->default('production'); // production, sandbox
            $table->json('allowed_ips')->nullable(); // IP whitelist
            $table->json('allowed_origins')->nullable(); // CORS origins
            $table->json('metadata')->nullable(); // Дополнительные данные

            // Webhooks
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret', 64)->nullable();
            $table->json('webhook_events')->nullable(); // Подписанные события

            // Статистика
            $table->timestamp('last_used_at')->nullable();
            $table->bigInteger('total_requests')->default(0);
            $table->bigInteger('total_errors')->default(0);

            // Аудит
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Индексы
            $table->index(['tenant_id', 'is_active']);
            $table->index(['restaurant_id', 'is_active']);
            $table->index('rate_plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
