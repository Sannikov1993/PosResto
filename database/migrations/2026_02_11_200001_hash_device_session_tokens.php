<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1/Fix 10: Device Session Token Hashing
 *
 * Добавляем token_hash, сохраняем token для graceful migration (30 дней dual-lookup)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_sessions', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable()->after('token')->index();
        });

        // Хешируем все существующие токены
        $sessions = DB::table('device_sessions')->whereNotNull('token')->get();
        foreach ($sessions as $session) {
            DB::table('device_sessions')
                ->where('id', $session->id)
                ->update(['token_hash' => hash('sha256', $session->token)]);
        }
    }

    public function down(): void
    {
        Schema::table('device_sessions', function (Blueprint $table) {
            $table->dropColumn('token_hash');
        });
    }
};
