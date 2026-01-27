<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Устройства учета времени (терминалы биометрии)
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100); // "Терминал у входа"
            $table->string('type', 50); // anviz, zkteco, hikvision, generic
            $table->string('model', 100)->nullable(); // "Facepass 7"
            $table->string('serial_number', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('api_key', 255)->nullable(); // для авторизации webhook
            $table->json('settings')->nullable(); // дополнительные настройки устройства
            $table->enum('status', ['active', 'inactive', 'offline'])->default('active');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
        });

        // События учета времени (от устройств и QR)
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('attendance_devices')->nullOnDelete();
            $table->foreignId('work_session_id')->nullable()->constrained('work_sessions')->nullOnDelete();
            $table->enum('event_type', ['clock_in', 'clock_out']);
            $table->enum('source', ['device', 'qr_code', 'manual', 'api']); // откуда пришло событие
            $table->string('device_event_id', 100)->nullable(); // ID события на устройстве
            $table->decimal('confidence', 5, 2)->nullable(); // уверенность распознавания (0-100%)
            $table->string('verification_method', 50)->nullable(); // face, fingerprint, card, qr
            $table->decimal('latitude', 10, 7)->nullable(); // для QR с геолокацией
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('raw_data')->nullable(); // сырые данные от устройства
            $table->timestamp('event_time'); // время события на устройстве
            $table->timestamps();

            $table->index(['restaurant_id', 'user_id', 'event_time']);
            $table->index(['device_id', 'event_time']);
            $table->unique(['device_id', 'device_event_id']); // защита от дублей
        });

        // QR-коды для ресторанов
        Schema::create('attendance_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64)->unique(); // уникальный код
            $table->string('secret', 64); // секрет для подписи
            $table->enum('type', ['static', 'dynamic'])->default('dynamic');
            $table->boolean('require_geolocation')->default(true);
            $table->unsignedInteger('max_distance_meters')->default(100); // макс расстояние от ресторана
            $table->unsignedInteger('refresh_interval_minutes')->default(5); // для dynamic
            $table->timestamp('expires_at')->nullable(); // для dynamic
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['restaurant_id', 'is_active']);
        });

        // Привязка сотрудников к устройствам (для синхронизации)
        Schema::create('attendance_device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('attendance_devices')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_user_id', 100)->nullable(); // ID пользователя на устройстве
            $table->boolean('is_synced')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'user_id']);
            $table->index(['device_id', 'is_synced']);
        });

        // Добавляем настройки учета времени в рестораны
        Schema::table('restaurants', function (Blueprint $table) {
            $table->enum('attendance_mode', ['disabled', 'device_only', 'qr_only', 'device_or_qr'])
                  ->default('disabled')
                  ->after('timezone');
            $table->unsignedInteger('attendance_early_minutes')->default(30)->after('attendance_mode'); // за сколько минут до смены можно отметиться
            $table->unsignedInteger('attendance_late_minutes')->default(120)->after('attendance_early_minutes'); // через сколько минут после начала нельзя отметиться
            $table->decimal('latitude', 10, 7)->nullable()->after('attendance_late_minutes');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        // Добавляем поля в work_sessions для связи с attendance
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->foreignId('clock_in_event_id')->nullable()->after('clock_in_verified_by')
                  ->constrained('attendance_events')->nullOnDelete();
            $table->foreignId('clock_out_event_id')->nullable()->after('clock_in_event_id')
                  ->constrained('attendance_events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clock_in_event_id');
            $table->dropConstrainedForeignId('clock_out_event_id');
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn([
                'attendance_mode',
                'attendance_early_minutes',
                'attendance_late_minutes',
                'latitude',
                'longitude',
            ]);
        });

        Schema::dropIfExists('attendance_device_users');
        Schema::dropIfExists('attendance_qr_codes');
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('attendance_devices');
    }
};
