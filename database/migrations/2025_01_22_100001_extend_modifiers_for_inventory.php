<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Расширяем таблицу modifiers (группы)
        Schema::table('modifiers', function (Blueprint $table) {
            $table->boolean('is_global')->default(true); // глобальный шаблон
        });

        // Расширяем связующую таблицу dish_modifier
        Schema::table('dish_modifier', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        // Связь опции модификатора с ингредиентом (для учёта на складе)
        Schema::create('modifier_option_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_option_id')->constrained()->onDelete('cascade');
            $table->foreignId('ingredient_id')->constrained()->onDelete('cascade');
            $table->decimal('quantity', 10, 3); // количество ингредиента
            $table->enum('action', ['add', 'replace', 'remove'])->default('add');
            // add = добавить к рецепту блюда
            // replace = заменить количество ингредиента в рецепте
            // remove = убрать ингредиент из рецепта (для "без лука" и т.д.)
            $table->timestamps();

            $table->unique(['modifier_option_id', 'ingredient_id'], 'mod_opt_ing_unique');
        });

        // Выбранные модификаторы в позиции заказа
        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('modifier_option_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('quantity')->default(1); // количество (например, 2x сыр)
            $table->decimal('price', 10, 2); // цена на момент заказа
            $table->string('name')->nullable(); // название на момент заказа (для истории)
            $table->timestamps();

            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('modifier_option_ingredients');

        Schema::table('dish_modifier', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'is_active']);
        });

        Schema::table('modifiers', function (Blueprint $table) {
            $table->dropColumn('is_global');
        });
    }
};
