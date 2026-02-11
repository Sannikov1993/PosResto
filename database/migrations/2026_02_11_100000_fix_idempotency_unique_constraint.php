<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix idempotency key unique constraint to include user_id.
     * Old index only covered (api_client_id, idempotency_key) which allowed
     * NULL api_client_id rows to bypass uniqueness in MySQL.
     */
    public function up(): void
    {
        Schema::table('api_idempotency_keys', function (Blueprint $table) {
            // Drop old unique index
            $table->dropUnique('unique_client_idempotency');

            // New composite unique: scope by both api_client_id and user_id
            $table->unique(
                ['api_client_id', 'user_id', 'idempotency_key'],
                'unique_scope_idempotency'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_idempotency_keys', function (Blueprint $table) {
            $table->dropUnique('unique_scope_idempotency');

            $table->unique(
                ['api_client_id', 'idempotency_key'],
                'unique_client_idempotency'
            );
        });
    }
};
