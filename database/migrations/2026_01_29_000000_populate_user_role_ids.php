<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Заполнить role_id для пользователей, у которых он пустой.
     * Ищет Role по key = user.role в рамках того же ресторана.
     */
    public function up(): void
    {
        // Получаем всех пользователей без role_id, но с заполненным role
        $users = DB::table('users')
            ->whereNull('role_id')
            ->whereNotNull('role')
            ->where('role', '!=', '')
            ->get(['id', 'role', 'restaurant_id']);

        foreach ($users as $user) {
            $role = DB::table('roles')
                ->where('key', $user->role)
                ->where('restaurant_id', $user->restaurant_id)
                ->first();

            if ($role) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['role_id' => $role->id]);
            }
        }
    }

    /**
     * Откат: обнуляем role_id (нет способа отличить ранее заполненные от заполненных миграцией).
     */
    public function down(): void
    {
        // Не откатываем — данные не теряются, role остаётся
    }
};
