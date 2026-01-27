<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('pending_cancellation')->default(false);
            $table->text('cancel_request_reason')->nullable();
            $table->unsignedBigInteger('cancel_requested_by')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['pending_cancellation', 'cancel_request_reason', 'cancel_requested_by', 'cancel_requested_at']);
        });
    }
};
