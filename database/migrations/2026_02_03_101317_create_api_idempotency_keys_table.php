<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotency keys for safe API request retries.
     * Stores response for duplicate requests with same key.
     */
    public function up(): void
    {
        Schema::create('api_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 64);
            $table->foreignId('api_client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->string('request_hash', 64)->comment('SHA256 of request body');
            $table->unsignedSmallInteger('status_code');
            $table->longText('response_body');
            $table->json('response_headers')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('expires_at')->index();

            // Unique per client + key
            $table->unique(['api_client_id', 'idempotency_key'], 'unique_client_idempotency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_keys');
    }
};
