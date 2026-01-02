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
            $table->foreignId('restaurant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('phone', 20)->nullable()->after('email');
            $table->enum('role', [
                'super_admin', // Владелец системы
                'owner',       // Владелец ресторана
                'admin',       // Администратор
                'manager',     // Менеджер
                'waiter',      // Официант
                'cook',        // Повар
                'cashier',     // Кассир
                'courier',     // Курьер
                'hostess'      // Хостес
            ])->default('waiter')->after('phone');
            $table->string('avatar')->nullable()->after('role');
            $table->string('pin_code', 10)->nullable()->after('avatar'); // Быстрый вход
            $table->boolean('is_active')->default(true)->after('pin_code');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            
            $table->index(['restaurant_id', 'role', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn([
                'restaurant_id',
                'phone',
                'role',
                'avatar',
                'pin_code',
                'is_active',
                'last_login_at'
            ]);
        });
    }
};
