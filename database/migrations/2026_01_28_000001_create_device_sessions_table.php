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
        if (!Schema::hasTable('device_sessions')) {
            Schema::create('device_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('device_fingerprint', 64)->index()->comment('Fingerprint устройства');
                $table->string('device_name')->nullable()->comment('Название устройства (UserAgent)');
                $table->string('app_type', 50)->index()->comment('pos, waiter, courier, backoffice');
                $table->string('token', 255)->unique()->comment('Токен для автовхода');
                $table->timestamp('last_activity_at')->nullable()->comment('Последняя активность');
                $table->timestamp('expires_at')->index()->comment('Срок действия токена');
                $table->timestamps();

                $table->index(['user_id', 'app_type']);
                $table->index(['device_fingerprint', 'app_type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_sessions');
    }
};
