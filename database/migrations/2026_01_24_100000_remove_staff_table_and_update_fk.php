<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Удаляем дублирующуюся таблицу staff.
     * Все ссылки на staff меняем на users.
     */
    public function up(): void
    {
        // Проверяем, есть ли таблица staff
        if (!Schema::hasTable('staff')) {
            // Если таблицы staff нет, просто обновляем FK
            $this->updateForeignKeys();
            return;
        }

        // 1. Обновляем cash_shifts: меняем значение cashier_id на user_id из staff
        // Используем совместимый с SQLite синтаксис
        $staffMapping = DB::table('staff')
            ->whereNotNull('user_id')
            ->pluck('user_id', 'id');

        foreach ($staffMapping as $staffId => $userId) {
            DB::table('cash_shifts')
                ->where('cashier_id', $staffId)
                ->update(['cashier_id' => $userId]);
        }

        // 2. Обновляем cash_operations: меняем staff_id на user_id
        foreach ($staffMapping as $staffId => $userId) {
            DB::table('cash_operations')
                ->where('staff_id', $staffId)
                ->update(['staff_id' => $userId]);
        }

        // 3. Обновляем FK
        $this->updateForeignKeys();

        // 4. Удаляем таблицу staff
        Schema::dropIfExists('staff');
    }

    /**
     * Обновить foreign keys
     */
    private function updateForeignKeys(): void
    {
        // Для SQLite нужно пересоздать таблицу
        // Проверяем тип базы данных
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite не поддерживает ALTER TABLE DROP FOREIGN KEY
            // и ALTER TABLE RENAME COLUMN напрямую
            // Но Laravel может делать это через свой механизм

            // Переименовываем колонку staff_id в user_id в cash_operations
            if (Schema::hasColumn('cash_operations', 'staff_id')) {
                Schema::table('cash_operations', function (Blueprint $table) {
                    $table->renameColumn('staff_id', 'user_id');
                });
            }
        } else {
            // MySQL/PostgreSQL
            Schema::table('cash_shifts', function (Blueprint $table) {
                try {
                    $table->dropForeign(['cashier_id']);
                } catch (\Exception $e) {
                    // FK может не существовать
                }
            });

            Schema::table('cash_shifts', function (Blueprint $table) {
                $table->foreign('cashier_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            });

            // Проверяем, нужно ли переименовывать staff_id в user_id
            if (Schema::hasColumn('cash_operations', 'staff_id')) {
                // Пробуем удалить FK если он есть
                try {
                    Schema::table('cash_operations', function (Blueprint $table) {
                        $table->dropForeign(['staff_id']);
                    });
                } catch (\Exception $e) {
                    // FK может не существовать
                }

                Schema::table('cash_operations', function (Blueprint $table) {
                    $table->renameColumn('staff_id', 'user_id');
                });
            }

            // Добавляем FK к users если ещё нет
            if (Schema::hasColumn('cash_operations', 'user_id')) {
                try {
                    Schema::table('cash_operations', function (Blueprint $table) {
                        $table->foreign('user_id')
                            ->references('id')
                            ->on('users')
                            ->onDelete('set null');
                    });
                } catch (\Exception $e) {
                    // FK может уже существовать
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Восстанавливаем таблицу staff
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('position')->nullable();
            $table->string('role')->default('waiter');
            $table->string('pin', 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Переименовываем user_id обратно в staff_id
        if (Schema::hasColumn('cash_operations', 'user_id')) {
            Schema::table('cash_operations', function (Blueprint $table) {
                $table->renameColumn('user_id', 'staff_id');
            });
        }
    }
};
