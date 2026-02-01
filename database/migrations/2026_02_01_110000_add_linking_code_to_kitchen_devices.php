<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchen_devices', function (Blueprint $table) {
            $table->string('linking_code', 6)->nullable()->after('device_id');
            $table->timestamp('linking_code_expires_at')->nullable()->after('linking_code');
        });
    }

    public function down(): void
    {
        Schema::table('kitchen_devices', function (Blueprint $table) {
            $table->dropColumn(['linking_code', 'linking_code_expires_at']);
        });
    }
};
