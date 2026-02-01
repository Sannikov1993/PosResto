<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            if (!Schema::hasColumn('dishes', 'is_stopped')) {
                $table->boolean('is_stopped')->default(false)->after('is_available');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dishes', function (Blueprint $table) {
            if (Schema::hasColumn('dishes', 'is_stopped')) {
                $table->dropColumn('is_stopped');
            }
        });
    }
};
