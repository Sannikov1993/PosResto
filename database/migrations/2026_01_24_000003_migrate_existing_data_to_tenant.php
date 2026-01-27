<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;
use App\Models\Restaurant;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Эта миграция создаёт первый тенант и привязывает к нему
     * все существующие рестораны и пользователей.
     */
    public function up(): void
    {
        // Проверяем, есть ли уже данные
        $restaurantsExist = DB::table('restaurants')->exists();
        $usersExist = DB::table('users')->exists();

        if (!$restaurantsExist && !$usersExist) {
            // Если данных нет, ничего не делаем - будет создано при регистрации
            return;
        }

        // Создаём первый тенант (для существующих данных)
        $tenantId = DB::table('tenants')->insertGetId([
            'name' => 'Мой ресторан',
            'slug' => 'my-restaurant',
            'email' => 'admin@example.com',
            'plan' => 'premium', // Владелец системы получает премиум
            'is_active' => true,
            'timezone' => 'Europe/Moscow',
            'currency' => 'RUB',
            'locale' => 'ru',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Привязываем все существующие рестораны к тенанту
        DB::table('restaurants')
            ->whereNull('tenant_id')
            ->update([
                'tenant_id' => $tenantId,
                'is_main' => false,
            ]);

        // Первый ресторан делаем главным
        DB::table('restaurants')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->limit(1)
            ->update(['is_main' => true]);

        // Привязываем всех пользователей к тенанту
        DB::table('users')
            ->whereNull('tenant_id')
            ->update(['tenant_id' => $tenantId]);

        // Первого пользователя (или владельца) делаем владельцем тенанта
        $ownerId = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->where('role', 'owner')
                    ->orWhere('role', 'super_admin')
                    ->orWhere('role', 'admin');
            })
            ->orderBy('id')
            ->value('id');

        if ($ownerId) {
            DB::table('users')
                ->where('id', $ownerId)
                ->update(['is_tenant_owner' => true]);
        } else {
            // Если нет владельца, делаем первого пользователя владельцем
            DB::table('users')
                ->where('tenant_id', $tenantId)
                ->orderBy('id')
                ->limit(1)
                ->update(['is_tenant_owner' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Убираем привязку к тенанту
        DB::table('restaurants')->update(['tenant_id' => null, 'is_main' => false]);
        DB::table('users')->update(['tenant_id' => null, 'is_tenant_owner' => false]);

        // Удаляем тенант с slug 'my-restaurant'
        DB::table('tenants')->where('slug', 'my-restaurant')->delete();
    }
};
