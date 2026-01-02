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
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 10);
            $table->string('name', 50)->nullable();
            $table->unsignedTinyInteger('seats')->default(4);
            $table->decimal('min_order', 10, 2)->default(0);
            $table->enum('shape', ['round', 'square', 'rectangle', 'oval'])->default('square');
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->integer('width')->default(80);
            $table->integer('height')->default(80);
            $table->integer('rotation')->default(0);
            $table->enum('status', ['free', 'occupied', 'reserved', 'bill'])->default('free');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['restaurant_id', 'zone_id']);
            $table->index(['restaurant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
