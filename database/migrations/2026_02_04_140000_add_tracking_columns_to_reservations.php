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
        Schema::table('reservations', function (Blueprint $table) {
            // Add columns only if they don't exist
            if (!Schema::hasColumn('reservations', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('source');
            }
            if (!Schema::hasColumn('reservations', 'confirmed_by')) {
                $table->unsignedBigInteger('confirmed_by')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('reservations', 'confirmed_at')) {
                $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
            }
            if (!Schema::hasColumn('reservations', 'seated_at')) {
                $table->timestamp('seated_at')->nullable()->after('confirmed_at');
            }
            if (!Schema::hasColumn('reservations', 'seated_by')) {
                $table->unsignedBigInteger('seated_by')->nullable()->after('seated_at');
            }
            if (!Schema::hasColumn('reservations', 'unseated_at')) {
                $table->timestamp('unseated_at')->nullable()->after('seated_by');
            }
            if (!Schema::hasColumn('reservations', 'unseated_by')) {
                $table->unsignedBigInteger('unseated_by')->nullable()->after('unseated_at');
            }
            if (!Schema::hasColumn('reservations', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('unseated_by');
            }
            if (!Schema::hasColumn('reservations', 'completed_by')) {
                $table->unsignedBigInteger('completed_by')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('reservations', 'cancellation_reason')) {
                $table->string('cancellation_reason', 500)->nullable()->after('completed_by');
            }
            if (!Schema::hasColumn('reservations', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
            }
            if (!Schema::hasColumn('reservations', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('reservations', 'no_show_at')) {
                $table->timestamp('no_show_at')->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('reservations', 'no_show_by')) {
                $table->unsignedBigInteger('no_show_by')->nullable()->after('no_show_at');
            }
            if (!Schema::hasColumn('reservations', 'reminder_sent')) {
                $table->boolean('reminder_sent')->default(false)->after('no_show_by');
            }
            if (!Schema::hasColumn('reservations', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('reminder_sent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $columns = [
                'created_by',
                'confirmed_by',
                'confirmed_at',
                'seated_at',
                'seated_by',
                'unseated_at',
                'unseated_by',
                'completed_at',
                'completed_by',
                'cancellation_reason',
                'cancelled_at',
                'cancelled_by',
                'no_show_at',
                'no_show_by',
                'reminder_sent',
                'reminder_sent_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('reservations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
