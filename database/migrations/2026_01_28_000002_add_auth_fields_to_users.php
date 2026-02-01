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
        Schema::table('users', function (Blueprint $table) {
            // Логин для входа (альтернатива email)
            if (!Schema::hasColumn('users', 'login')) {
                $table->string('login', 50)->nullable()->unique()->after('email');
                $table->index('login');
            }

            // API токен для авторизации
            if (!Schema::hasColumn('users', 'api_token')) {
                $table->string('api_token', 64)->nullable()->unique()->after('password');
                $table->index('api_token');
            }

            // PIN lookup для быстрого поиска (plaintext)
            if (!Schema::hasColumn('users', 'pin_lookup')) {
                $table->string('pin_lookup', 10)->nullable()->after('pin_code');
                $table->index('pin_lookup');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'login')) {
                $table->dropIndex(['login']);
                $table->dropColumn('login');
            }
            if (Schema::hasColumn('users', 'api_token')) {
                $table->dropIndex(['api_token']);
                $table->dropColumn('api_token');
            }
            if (Schema::hasColumn('users', 'pin_lookup')) {
                $table->dropIndex(['pin_lookup']);
                $table->dropColumn('pin_lookup');
            }
        });
    }
};
