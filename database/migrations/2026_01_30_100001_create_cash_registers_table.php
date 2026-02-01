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
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained()->cascadeOnDelete();

            // Основные данные
            $table->string('name', 100); // "Касса 1 (ИП)" / "Касса 2 (ООО)"

            // Регистрационные данные ККТ
            $table->string('serial_number', 50)->nullable(); // Серийный номер ККТ
            $table->string('registration_number', 20)->nullable(); // Регистрационный номер в ФНС

            // Фискальный накопитель
            $table->string('fn_number', 20)->nullable(); // Номер фискального накопителя
            $table->date('fn_expires_at')->nullable(); // Срок действия ФН

            // ОФД
            $table->string('ofd_name')->nullable(); // Название ОФД
            $table->string('ofd_inn', 12)->nullable(); // ИНН ОФД

            // Статус
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Касса по умолчанию для юрлица
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Индексы
            $table->index(['restaurant_id', 'is_active']);
            $table->index(['legal_entity_id', 'is_active']);
            $table->index(['legal_entity_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
