<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Для SQLite нужно пересоздать колонку
        // Меняем enum на string чтобы не было ограничений
        if (DB::getDriverName() === 'sqlite') {
            // SQLite не поддерживает ALTER COLUMN, поэтому убираем check constraint
            DB::statement('PRAGMA writable_schema = ON');

            // Получаем текущий SQL создания таблицы
            $sql = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='orders'");

            if ($sql) {
                // Заменяем старый enum на новый с 'served'
                $newSql = str_replace(
                    "CHECK (\"status\" IN ('new', 'confirmed', 'cooking', 'ready', 'delivering', 'completed', 'cancelled'))",
                    "CHECK (\"status\" IN ('new', 'confirmed', 'cooking', 'ready', 'served', 'delivering', 'completed', 'cancelled'))",
                    $sql->sql
                );

                // Также пробуем другой формат
                $newSql = str_replace(
                    "check (\"status\" in ('new', 'confirmed', 'cooking', 'ready', 'delivering', 'completed', 'cancelled'))",
                    "check (\"status\" in ('new', 'confirmed', 'cooking', 'ready', 'served', 'delivering', 'completed', 'cancelled'))",
                    $newSql
                );

                DB::statement("UPDATE sqlite_master SET sql = ? WHERE type='table' AND name='orders'", [$newSql]);
            }

            DB::statement('PRAGMA writable_schema = OFF');
            DB::statement('PRAGMA integrity_check');
        } else {
            // Для MySQL
            DB::statement("ALTER TABLE orders MODIFY status ENUM('new', 'confirmed', 'cooking', 'ready', 'served', 'delivering', 'completed', 'cancelled') DEFAULT 'new'");
        }
    }

    public function down(): void
    {
        // Для отката сначала нужно обновить все 'served' на 'ready'
        DB::table('orders')->where('status', 'served')->update(['status' => 'ready']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY status ENUM('new', 'confirmed', 'cooking', 'ready', 'delivering', 'completed', 'cancelled') DEFAULT 'new'");
        }
    }
};
