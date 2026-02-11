<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2/Fix 17: Kitchen Device HMAC Request Signing
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchen_devices', function (Blueprint $table) {
            $table->string('hmac_secret', 64)->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('kitchen_devices', function (Blueprint $table) {
            $table->dropColumn('hmac_secret');
        });
    }
};
