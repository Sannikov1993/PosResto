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
        Schema::create('dishes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('old_price', 10, 2)->nullable(); // Для скидок
            $table->decimal('cost_price', 10, 2)->nullable(); // Себестоимость
            $table->unsignedInteger('weight')->nullable(); // Граммы
            $table->unsignedInteger('calories')->nullable();
            $table->decimal('proteins', 5, 2)->nullable();
            $table->decimal('fats', 5, 2)->nullable();
            $table->decimal('carbs', 5, 2)->nullable();
            $table->unsignedInteger('cooking_time')->nullable(); // Минуты
            $table->string('sku', 50)->nullable(); // Артикул
            $table->boolean('is_available')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_spicy')->default(false);
            $table->boolean('is_vegetarian')->default(false);
            $table->boolean('is_vegan')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['restaurant_id', 'category_id', 'is_available']);
            $table->index(['restaurant_id', 'is_available', 'sort_order']);
            $table->unique(['restaurant_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dishes');
    }
};
