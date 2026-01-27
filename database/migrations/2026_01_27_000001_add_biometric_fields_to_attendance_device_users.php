<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляем поля для отслеживания статуса биометрии
     * (Face ID, Fingerprint, Card) в таблицу attendance_device_users
     */
    public function up(): void
    {
        Schema::table('attendance_device_users', function (Blueprint $table) {
            // Face ID статус
            $table->enum('face_status', ['none', 'pending', 'enrolled', 'failed'])
                  ->default('none')
                  ;
            $table->timestamp('face_enrolled_at')->nullable();
            $table->unsignedTinyInteger('face_templates_count')->default(0);

            // Fingerprint статус
            $table->enum('fingerprint_status', ['none', 'pending', 'enrolled', 'failed'])
                  ->default('none')
                  ;
            $table->timestamp('fingerprint_enrolled_at')->nullable();

            // RFID карта
            $table->string('card_number', 20)->nullable();

            // Ошибка синхронизации
            $table->string('sync_error', 255)->nullable();

            // Индексы для быстрого поиска
            $table->index(['device_id', 'face_status']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_device_users', function (Blueprint $table) {
            $table->dropIndex(['device_id', 'face_status']);

            $table->dropColumn([
                'face_status',
                'face_enrolled_at',
                'face_templates_count',
                'fingerprint_status',
                'fingerprint_enrolled_at',
                'card_number',
                'sync_error',
            ]);
        });
    }
};
