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
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('legal_entity_id')
                ->nullable()
                ->after('restaurant_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['restaurant_id', 'legal_entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['legal_entity_id']);
            $table->dropIndex(['restaurant_id', 'legal_entity_id']);
            $table->dropColumn('legal_entity_id');
        });
    }
};
