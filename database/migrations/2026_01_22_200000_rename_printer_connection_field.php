<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Проверяем, что колонка ещё не переименована (могла быть переименована предыдущей миграцией)
        if (Schema::hasColumn('printers', 'connection') && !Schema::hasColumn('printers', 'connection_type')) {
            Schema::table('printers', function (Blueprint $table) {
                $table->renameColumn('connection', 'connection_type');
            });
        }
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->renameColumn('connection_type', 'connection');
        });
    }
};
