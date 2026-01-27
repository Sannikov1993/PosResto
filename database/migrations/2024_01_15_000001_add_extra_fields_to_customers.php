<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляем дополнительные поля для клиентов:
     * - gender: пол клиента
     * - source: источник привлечения
     * - preferences: предпочтения/аллергии
     * - tags: теги (JSON массив)
     * - sms_consent: согласие на SMS
     * - email_consent: согласие на Email
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('source', 50)->nullable();
            $table->text('preferences')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('sms_consent')->default(true);
            $table->boolean('email_consent')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'source',
                'preferences',
                'tags',
                'sms_consent',
                'email_consent'
            ]);
        });
    }
};
