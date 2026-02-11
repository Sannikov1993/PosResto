<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1/Fix 13: Audit Trail
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('event_type', 100)->index(); // login, login_failed, device_linked, webhook_received, etc.
            $table->string('severity', 20)->default('info')->index(); // info, warning, critical
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('resource_type', 100)->nullable(); // User, KitchenDevice, Order, etc.
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['resource_type', 'resource_id']);
            $table->index(['tenant_id', 'event_type']);
            $table->index(['created_at', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
