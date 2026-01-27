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
            $table->enum('gender', ['male', 'female'])->nullable()->after('name');
            $table->string('source', 50)->nullable()->after('birth_date');
            $table->text('preferences')->nullable()->after('notes');
            $table->json('tags')->nullable()->after('preferences');
            $table->boolean('sms_consent')->default(true)->after('is_blacklisted');
            $table->boolean('email_consent')->default(false)->after('sms_consent');
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
