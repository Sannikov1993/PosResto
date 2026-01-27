<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('prepayment', 10, 2)->default(0)->after('paid_amount');
            $table->string('prepayment_method', 20)->nullable()->after('prepayment');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['prepayment', 'prepayment_method']);
        });
    }
};
