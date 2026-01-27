<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('pending_cancellation')->default(false)->after('is_write_off');
            $table->text('cancel_request_reason')->nullable()->after('pending_cancellation');
            $table->unsignedBigInteger('cancel_requested_by')->nullable()->after('cancel_request_reason');
            $table->timestamp('cancel_requested_at')->nullable()->after('cancel_requested_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['pending_cancellation', 'cancel_request_reason', 'cancel_requested_by', 'cancel_requested_at']);
        });
    }
};
