<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Инвентаризации
        if (!Schema::hasTable('inventory_checks')) {
            Schema::create('inventory_checks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained();
                $table->foreignId('created_by')->constrained('users');
                $table->string('number', 50);
                $table->string('status', 20)->default('draft'); // draft, in_progress, completed, cancelled
                $table->date('date');
                $table->text('notes')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('completed_by')->nullable()->constrained('users');
                $table->timestamps();

                $table->index(['restaurant_id', 'status']);
            });
        }

        // Позиции инвентаризации
        if (!Schema::hasTable('inventory_check_items')) {
            Schema::create('inventory_check_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_check_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained();
                $table->decimal('expected_quantity', 12, 3); // Ожидаемое количество (из системы)
                $table->decimal('actual_quantity', 12, 3)->nullable(); // Фактическое количество
                $table->decimal('difference', 12, 3)->nullable(); // Разница
                $table->decimal('cost_price', 10, 2)->default(0);
                $table->text('notes')->nullable();

                $table->unique(['inventory_check_id', 'ingredient_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_check_items');
        Schema::dropIfExists('inventory_checks');
    }
};
