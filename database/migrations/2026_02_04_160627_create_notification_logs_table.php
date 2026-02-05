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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();

            // Recipient (polymorphic - Customer, User, or null for guests)
            $table->nullableMorphs('notifiable');

            // Guest fallback (when no notifiable model)
            $table->string('recipient_phone', 20)->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_name')->nullable();

            // Notification details
            $table->string('notification_type', 100); // e.g., 'reservation_created'
            $table->string('channel', 50); // email, telegram, sms, database
            $table->string('subject')->nullable(); // email subject or notification title

            // Related entity (polymorphic - Reservation, Order, etc.)
            $table->nullableMorphs('related');

            // Delivery status
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'bounced'])->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Channel-specific data (message_id, etc.)
            $table->json('channel_data')->nullable();

            $table->timestamps();

            // Indexes for querying
            $table->index(['restaurant_id', 'created_at']);
            $table->index(['notification_type', 'status']);
            $table->index(['channel', 'status']);
            $table->index('recipient_phone');
            $table->index('recipient_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
