<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'is_asap')) {
                $table->boolean('is_asap')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'scheduled_at')) {
                $table->dropColumn('scheduled_at');
            }
            if (Schema::hasColumn('orders', 'is_asap')) {
                $table->dropColumn('is_asap');
            }
        });
    }
};
