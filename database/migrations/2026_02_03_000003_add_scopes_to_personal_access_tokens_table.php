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
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Тип токена: internal (POS/backoffice), api (public API)
            $table->string('token_type', 16)->default('internal')->after('abilities');

            // API scopes (отдельно от abilities для публичного API)
            $table->json('scopes')->nullable()->after('token_type');

            // Связь с API клиентом (для токенов, выданных через OAuth flow)
            $table->foreignId('api_client_id')->nullable()->after('scopes')
                ->constrained()->onDelete('cascade');

            // Refresh token для обновления access token
            $table->string('refresh_token', 64)->nullable()->unique()->after('api_client_id');
            $table->timestamp('refresh_token_expires_at')->nullable()->after('refresh_token');

            // IP-адрес создания токена
            $table->string('created_ip', 45)->nullable()->after('refresh_token_expires_at');

            // Индексы
            $table->index('token_type');
            $table->index('api_client_id');
            $table->index('refresh_token_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['token_type']);
            $table->dropIndex(['api_client_id']);
            $table->dropIndex(['refresh_token_expires_at']);

            $table->dropForeign(['api_client_id']);

            $table->dropColumn([
                'token_type',
                'scopes',
                'api_client_id',
                'refresh_token',
                'refresh_token_expires_at',
                'created_ip',
            ]);
        });
    }
};
