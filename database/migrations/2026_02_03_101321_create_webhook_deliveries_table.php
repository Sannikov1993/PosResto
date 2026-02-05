<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Webhook delivery tracking with retry support.
     * Stores each webhook event and delivery attempts.
     */
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('api_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();

            // Event info
            $table->string('event_type', 100)->index();
            $table->json('payload');
            $table->string('signature', 64);

            // Delivery status
            $table->enum('status', ['pending', 'delivered', 'failed', 'expired'])->default('pending')->index();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(8);

            // Last attempt info
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->text('last_response')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('last_response_time_ms')->nullable();

            // Timing
            $table->timestamp('created_at');
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('expires_at')->index();

            // Indexes
            $table->index(['api_client_id', 'status']);
            $table->index(['api_client_id', 'event_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
