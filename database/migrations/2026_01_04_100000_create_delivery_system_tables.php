<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Миграция для полноценной системы доставки
 */
return new class extends Migration
{
    public function up(): void
    {
        // Зоны доставки
        Schema::dropIfExists('delivery_zones');
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->string('name'); // "Зона 1 (до 3 км)"
            $table->decimal('min_distance', 5, 2)->default(0); // км
            $table->decimal('max_distance', 5, 2); // км
            $table->decimal('delivery_fee', 10, 2)->default(0); // Стоимость доставки
            $table->decimal('free_delivery_from', 10, 2)->nullable(); // Бесплатная от суммы
            $table->integer('estimated_time')->default(45); // Примерное время в минутах
            $table->string('color')->default('#3B82F6'); // Цвет зоны на карте
            $table->json('polygon')->nullable(); // GeoJSON полигон зоны
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Настройки доставки
        Schema::dropIfExists('delivery_settings');
        Schema::create('delivery_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'key']);
        });

        // Добавляем поля в orders для расширенной доставки
        Schema::table('orders', function (Blueprint $table) {
            // Расширенные поля адреса
            if (!Schema::hasColumn('orders', 'delivery_street')) {
                $table->string('delivery_street')->nullable()->after('delivery_address');
            }
            if (!Schema::hasColumn('orders', 'delivery_house')) {
                $table->string('delivery_house', 20)->nullable()->after('delivery_street');
            }
            if (!Schema::hasColumn('orders', 'delivery_building')) {
                $table->string('delivery_building', 20)->nullable()->after('delivery_house');
            }
            if (!Schema::hasColumn('orders', 'delivery_apartment')) {
                $table->string('delivery_apartment', 20)->nullable()->after('delivery_building');
            }
            if (!Schema::hasColumn('orders', 'delivery_entrance')) {
                $table->string('delivery_entrance', 10)->nullable()->after('delivery_apartment');
            }
            if (!Schema::hasColumn('orders', 'delivery_floor')) {
                $table->string('delivery_floor', 10)->nullable()->after('delivery_entrance');
            }
            if (!Schema::hasColumn('orders', 'delivery_intercom')) {
                $table->string('delivery_intercom', 20)->nullable()->after('delivery_floor');
            }
            // Зона и расстояние
            if (!Schema::hasColumn('orders', 'delivery_zone_id')) {
                $table->unsignedBigInteger('delivery_zone_id')->nullable()->after('delivery_intercom');
            }
            if (!Schema::hasColumn('orders', 'delivery_distance')) {
                $table->decimal('delivery_distance', 5, 2)->nullable()->after('delivery_zone_id');
            }
            // Время доставки
            if (!Schema::hasColumn('orders', 'desired_delivery_time')) {
                $table->timestamp('desired_delivery_time')->nullable()->after('delivery_time');
            }
            if (!Schema::hasColumn('orders', 'is_asap')) {
                $table->boolean('is_asap')->default(true)->after('desired_delivery_time');
            }
            // Сдача
            if (!Schema::hasColumn('orders', 'change_from')) {
                $table->decimal('change_from', 10, 2)->nullable()->after('change_amount');
            }
        });

        // Добавляем поля курьера в users
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_courier')) {
                $table->boolean('is_courier')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('users', 'courier_status')) {
                $table->enum('courier_status', ['offline', 'available', 'busy'])->default('offline')->after('is_courier');
            }
            if (!Schema::hasColumn('users', 'courier_last_location')) {
                $table->json('courier_last_location')->nullable()->after('courier_status');
            }
            if (!Schema::hasColumn('users', 'courier_last_seen')) {
                $table->timestamp('courier_last_seen')->nullable()->after('courier_last_location');
            }
            if (!Schema::hasColumn('users', 'courier_today_orders')) {
                $table->integer('courier_today_orders')->default(0)->after('courier_last_seen');
            }
            if (!Schema::hasColumn('users', 'courier_today_earnings')) {
                $table->decimal('courier_today_earnings', 10, 2)->default(0)->after('courier_today_orders');
            }
        });

        // Адреса клиентов (если не существует)
        if (!Schema::hasTable('customer_addresses')) {
            Schema::create('customer_addresses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->string('title')->nullable(); // "Дом", "Работа"
                $table->string('street');
                $table->string('house', 20)->nullable();
                $table->string('building', 20)->nullable();
                $table->string('apartment', 20)->nullable();
                $table->string('entrance', 10)->nullable();
                $table->string('floor', 10)->nullable();
                $table->string('intercom', 20)->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->text('comment')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('delivery_settings');

        Schema::table('orders', function (Blueprint $table) {
            $columns = [
                'delivery_street', 'delivery_house', 'delivery_building',
                'delivery_apartment', 'delivery_entrance', 'delivery_floor',
                'delivery_intercom', 'delivery_zone_id', 'delivery_distance',
                'desired_delivery_time', 'is_asap', 'change_from'
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'is_courier', 'courier_status', 'courier_last_location',
                'courier_last_seen', 'courier_today_orders', 'courier_today_earnings'
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
