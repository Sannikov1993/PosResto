<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_shift_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['opened', 'closed']); // тип события
            $table->decimal('amount', 12, 2)->default(0); // сумма при открытии/закрытии
            $table->foreignId('user_id')->nullable(); // кто открыл/закрыл
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_events');
    }
};
