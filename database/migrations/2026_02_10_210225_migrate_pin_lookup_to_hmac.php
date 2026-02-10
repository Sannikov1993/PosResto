<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;

return new class extends Migration
{
    /**
     * Конвертирует plaintext pin_lookup в HMAC-SHA256 хэш.
     * Увеличивает длину колонки с 10 до 64 символов (SHA-256 hex = 64 chars).
     */
    public function up(): void
    {
        // Увеличить длину колонки для HMAC хэша (64 символа hex)
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_lookup', 64)->nullable()->change();
        });

        // Конвертировать существующие plaintext PIN в HMAC
        $users = DB::table('users')
            ->whereNotNull('pin_lookup')
            ->where('pin_lookup', '!=', '')
            ->get(['id', 'pin_lookup']);

        $appKey = config('app.key');

        foreach ($users as $user) {
            // Пропускаем если уже HMAC (64 hex символа)
            if (strlen($user->pin_lookup) === 64 && ctype_xdigit($user->pin_lookup)) {
                continue;
            }

            // Конвертируем plaintext → HMAC
            $hmac = hash_hmac('sha256', $user->pin_lookup, $appKey);

            DB::table('users')
                ->where('id', $user->id)
                ->update(['pin_lookup' => $hmac]);
        }
    }

    /**
     * Откат невозможен — plaintext PIN не может быть восстановлен из HMAC.
     * При откате обнуляем pin_lookup, пользователям придётся переустановить PIN.
     */
    public function down(): void
    {
        DB::table('users')->update(['pin_lookup' => null]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_lookup', 10)->nullable()->change();
        });
    }
};
