<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_day_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->enum('type', [
                'shift',      // Полная смена (ручная)
                'day_off',    // Выходной
                'vacation',   // Отпуск
                'sick_leave', // Больничный
                'absence',    // Прогул
            ])->default('shift');
            $table->time('start_time')->nullable(); // Для типа shift
            $table->time('end_time')->nullable();   // Для типа shift
            $table->decimal('hours', 5, 2)->default(0); // Итоговые часы
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Уникальный индекс - один override на день на сотрудника
            $table->unique(['user_id', 'date']);
            $table->index(['restaurant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_day_overrides');
    }
};
