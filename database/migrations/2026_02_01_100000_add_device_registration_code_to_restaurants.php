<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('device_registration_code', 6)->nullable()->after('is_active');
            $table->timestamp('device_registration_code_expires_at')->nullable()->after('device_registration_code');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['device_registration_code', 'device_registration_code_expires_at']);
        });
    }
};
