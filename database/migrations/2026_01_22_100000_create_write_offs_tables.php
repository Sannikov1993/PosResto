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
        // Основная таблица списаний
        Schema::create('write_offs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->enum('type', ['spoilage', 'expired', 'loss', 'staff_meal', 'promo', 'other']);
            $table->decimal('total_amount', 10, 2);
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->foreign('restaurant_id')->references('id')->on('restaurants')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['restaurant_id', 'created_at']);
            $table->index('type');
        });

        // Позиции списания
        Schema::create('write_off_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('write_off_id')->constrained()->cascadeOnDelete();
            $table->enum('item_type', ['dish', 'ingredient', 'manual']);
            $table->unsignedBigInteger('dish_id')->nullable();
            $table->unsignedBigInteger('ingredient_id')->nullable();
            $table->string('name');
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();

            $table->foreign('dish_id')->references('id')->on('dishes')->nullOnDelete();
            $table->foreign('ingredient_id')->references('id')->on('ingredients')->nullOnDelete();
        });

        // Добавляем порог для подтверждения менеджером
        if (!Schema::hasColumn('restaurants', 'write_off_approval_threshold')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->decimal('write_off_approval_threshold', 10, 2)->default(1000.00);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('write_off_items');
        Schema::dropIfExists('write_offs');

        if (Schema::hasColumn('restaurants', 'write_off_approval_threshold')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->dropColumn('write_off_approval_threshold');
            });
        }
    }
};
