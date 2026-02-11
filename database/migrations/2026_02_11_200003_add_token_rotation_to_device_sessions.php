<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1/Fix 14: Token Rotation/Revocation
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_sessions', function (Blueprint $table) {
            $table->string('rotation_token_hash', 64)->nullable()->after('token_hash');
            $table->timestamp('max_lifetime_at')->nullable()->after('expires_at');
            $table->timestamp('rotated_at')->nullable()->after('max_lifetime_at');
        });
    }

    public function down(): void
    {
        Schema::table('device_sessions', function (Blueprint $table) {
            $table->dropColumn(['rotation_token_hash', 'max_lifetime_at', 'rotated_at']);
        });
    }
};
