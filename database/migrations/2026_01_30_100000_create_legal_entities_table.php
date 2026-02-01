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
        Schema::create('legal_entities', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();

            // Основные данные
            $table->string('name', 255); // "ООО Ресторан" / "ИП Иванов"
            $table->string('short_name', 50)->nullable(); // "ООО" / "ИП" (для чека)
            $table->enum('type', ['llc', 'ie'])->default('llc'); // llc = ООО, ie = ИП

            // Реквизиты
            $table->string('inn', 12); // ИНН (10 для ООО, 12 для ИП)
            $table->string('kpp', 9)->nullable(); // КПП (только для ООО)
            $table->string('ogrn', 15)->nullable(); // ОГРН (13 знаков) или ОГРНИП (15 знаков)

            // Адреса
            $table->text('legal_address')->nullable(); // Юридический адрес
            $table->text('actual_address')->nullable(); // Фактический адрес

            // Руководитель
            $table->string('director_name')->nullable(); // ФИО директора/ИП
            $table->string('director_position')->nullable(); // "Генеральный директор"

            // Банковские реквизиты
            $table->string('bank_name')->nullable(); // Название банка
            $table->string('bank_bik', 9)->nullable(); // БИК (9 знаков)
            $table->string('bank_account', 20)->nullable(); // Расчётный счёт (20 знаков)
            $table->string('bank_corr_account', 20)->nullable(); // Корр. счёт (20 знаков)

            // Налогообложение
            $table->enum('taxation_system', ['osn', 'usn_income', 'usn_income_expense', 'patent'])
                ->default('usn_income'); // osn=ОСН, usn_income=УСН доходы, usn_income_expense=УСН доходы-расходы, patent=Патент
            $table->decimal('vat_rate', 5, 2)->nullable(); // Ставка НДС (0, 10, 20, null=без НДС)

            // Лицензия на алкоголь
            $table->boolean('has_alcohol_license')->default(false);
            $table->string('alcohol_license_number')->nullable();
            $table->date('alcohol_license_expires_at')->nullable();

            // Статус
            $table->boolean('is_default')->default(false); // По умолчанию для категорий без юрлица
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Индексы
            $table->index(['restaurant_id', 'is_active']);
            $table->index(['restaurant_id', 'is_default']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_entities');
    }
};
