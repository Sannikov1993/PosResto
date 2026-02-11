<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen api_secret column for bcrypt hashes and migrate existing plaintext secrets.
     */
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            $table->string('api_secret', 255)->change();
        });

        // Hash existing plaintext secrets
        DB::table('api_clients')->whereNotNull('api_secret')->orderBy('id')->chunk(100, function ($clients) {
            foreach ($clients as $client) {
                if (!str_starts_with($client->api_secret, '$2y$')) { // Not already hashed
                    DB::table('api_clients')->where('id', $client->id)->update([
                        'api_secret' => Hash::make($client->api_secret),
                    ]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse hashing — column size change only
        Schema::table('api_clients', function (Blueprint $table) {
            $table->string('api_secret', 255)->change();
        });
    }
};
