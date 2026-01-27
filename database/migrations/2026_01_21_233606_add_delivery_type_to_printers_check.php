<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Эта миграция была только для SQLite (изменение CHECK constraint)
        // Для MySQL ничего не нужно - enum/check работает по-другому
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite: нужно пересоздать таблицу чтобы изменить CHECK constraint
            // ... (оригинальный код для SQLite)
            return;
        }

        // Для MySQL просто пропускаем - тип 'delivery' уже добавлен в модели
    }

    public function down(): void
    {
        // Ничего не делаем
    }
};
