<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Таблица разрешений (permissions)
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('key', 50)->comment('Ключ разрешения, например: menu.create');
                $table->string('name', 100)->comment('Название разрешения');
                $table->string('group', 50)->comment('Группа: staff, menu, orders, reports, settings, loyalty, finance');
                $table->string('description')->nullable();
                $table->boolean('is_system')->default(false)->comment('Системное разрешение (нельзя удалить)');
                $table->timestamps();

                $table->unique(['restaurant_id', 'key']);
            });
        }

        // Таблица ролей
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('key', 50)->comment('Ключ роли: admin, manager, waiter...');
                $table->string('name', 100)->comment('Название роли');
                $table->string('description')->nullable();
                $table->string('color', 20)->default('#6b7280')->comment('Цвет для отображения');
                $table->string('icon', 10)->default('👤')->comment('Иконка роли');
                $table->boolean('is_system')->default(false)->comment('Системная роль (нельзя удалить)');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['restaurant_id', 'key']);
            });
        }

        // Связь ролей и разрешений
        if (!Schema::hasTable('role_permission')) {
            Schema::create('role_permission', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['role_id', 'permission_id']);
            });
        }

        // Добавляем поле role_id в users если его нет
        if (!Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            });
        }

        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
