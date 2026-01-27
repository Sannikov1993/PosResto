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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название организации
            $table->string('slug', 100)->unique(); // Уникальный идентификатор (для URL)
            $table->string('email')->unique(); // Email владельца
            $table->string('phone', 20)->nullable(); // Телефон

            // Юридические данные
            $table->string('inn', 12)->nullable(); // ИНН
            $table->string('legal_name')->nullable(); // Юридическое название
            $table->text('legal_address')->nullable(); // Юридический адрес

            // Подписка и биллинг (базовые поля, расширим потом)
            $table->string('plan')->default('trial'); // trial, start, business, premium
            $table->timestamp('trial_ends_at')->nullable(); // Окончание триала
            $table->timestamp('subscription_ends_at')->nullable(); // Окончание подписки

            // Настройки
            $table->json('settings')->nullable(); // Настройки организации
            $table->string('timezone')->default('Europe/Moscow');
            $table->string('currency', 3)->default('RUB');
            $table->string('locale', 5)->default('ru');

            // Статус
            $table->boolean('is_active')->default(true);
            $table->timestamp('blocked_at')->nullable(); // Когда заблокирован
            $table->string('blocked_reason')->nullable(); // Причина блокировки

            $table->timestamps();
            $table->softDeletes();

            // Индексы
            $table->index('is_active');
            $table->index('plan');
            $table->index(['is_active', 'plan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
