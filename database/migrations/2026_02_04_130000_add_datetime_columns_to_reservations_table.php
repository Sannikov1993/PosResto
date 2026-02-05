<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to add datetime-based columns to reservations table.
 *
 * This is part of the enterprise reservation datetime refactoring:
 * - Adds starts_at/ends_at DATETIME columns for proper midnight-crossing support
 * - Adds duration_minutes for denormalization
 * - Adds timezone for timezone-aware storage
 * - Adds indexes for efficient conflict detection queries
 *
 * Legacy columns (date, time_from, time_to) are preserved for the transition period.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('reservations', 'starts_at')) {
            return;
        }
        Schema::table('reservations', function (Blueprint $table) {
            // New datetime columns for proper midnight-crossing support
            $table->datetime('starts_at')->nullable()->after('time_to')
                  ->comment('Reservation start datetime in UTC');

            $table->datetime('ends_at')->nullable()->after('starts_at')
                  ->comment('Reservation end datetime in UTC');

            // Denormalized duration for quick queries
            $table->integer('duration_minutes')->nullable()->after('ends_at')
                  ->comment('Duration in minutes (denormalized for performance)');

            // Timezone for the original reservation time
            $table->string('timezone', 64)->default('UTC')->after('duration_minutes')
                  ->comment('Original timezone of the reservation');

            // Indexes for efficient conflict detection
            // Composite index for restaurant-scoped datetime range queries
            $table->index(['restaurant_id', 'starts_at', 'ends_at'], 'reservations_restaurant_datetime_idx');

            // Composite index for table-scoped datetime range queries
            $table->index(['table_id', 'starts_at', 'ends_at'], 'reservations_table_datetime_idx');

            // Index for status + datetime filtering (common query pattern)
            $table->index(['status', 'starts_at'], 'reservations_status_starts_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('reservations_restaurant_datetime_idx');
            $table->dropIndex('reservations_table_datetime_idx');
            $table->dropIndex('reservations_status_starts_at_idx');

            // Drop columns
            $table->dropColumn([
                'starts_at',
                'ends_at',
                'duration_minutes',
                'timezone',
            ]);
        });
    }
};
