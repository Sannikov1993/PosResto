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
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();

            // Идентификация
            $table->uuid('request_id')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('api_client_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            // HTTP данные
            $table->string('method', 10);
            $table->string('path', 512);
            $table->string('full_url', 2048)->nullable();
            $table->json('query_params')->nullable();

            // Request
            $table->json('request_headers')->nullable();
            $table->mediumText('request_body')->nullable();
            $table->integer('request_size')->nullable(); // bytes

            // Response
            $table->smallInteger('status_code');
            $table->json('response_headers')->nullable();
            $table->mediumText('response_body')->nullable();
            $table->integer('response_size')->nullable(); // bytes

            // Производительность
            $table->decimal('response_time_ms', 10, 2); // milliseconds
            $table->decimal('db_queries_time_ms', 10, 2)->nullable();
            $table->integer('db_queries_count')->nullable();
            $table->integer('memory_peak_mb')->nullable();

            // Клиент
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('region', 64)->nullable();

            // Rate Limiting
            $table->integer('rate_limit')->nullable();
            $table->integer('rate_remaining')->nullable();
            $table->boolean('rate_limited')->default(false);

            // Ошибки
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();

            // API версия
            $table->string('api_version', 8)->nullable();

            // Временные метки
            $table->timestamp('created_at')->useCurrent();

            // Индексы для аналитики
            $table->index('created_at');
            $table->index(['tenant_id', 'created_at']);
            $table->index(['api_client_id', 'created_at']);
            $table->index(['status_code', 'created_at']);
            $table->index(['path', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index('rate_limited');
            $table->index('error_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
