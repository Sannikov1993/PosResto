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
        Schema::create('channel_link_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Channel being linked
            $table->string('channel', 20); // telegram, email, sms

            // Secure token (random part only, signature computed on verification)
            $table->string('token', 64)->unique();

            // Token lifecycle
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            // Result of linking (stored after successful link)
            $table->string('linked_identifier')->nullable(); // e.g., telegram chat_id

            // Audit fields
            $table->string('created_ip', 45)->nullable();
            $table->string('created_user_agent')->nullable();
            $table->string('used_ip', 45)->nullable();
            $table->string('used_user_agent')->nullable();

            // Context (e.g., reservation_id that triggered the link request)
            $table->string('context_type')->nullable();
            $table->unsignedBigInteger('context_id')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['customer_id', 'channel']);
            $table->index(['token', 'expires_at']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_link_tokens');
    }
};
