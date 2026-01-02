<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // QR-коды для столов
        Schema::create('table_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->unsignedBigInteger('table_id');
            $table->string('code', 32)->unique();           // уникальный код
            $table->string('short_url', 100)->nullable();   // короткая ссылка
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scanned_at')->nullable();
            $table->integer('scan_count')->default(0);
            $table->timestamps();
            
            $table->foreign('table_id')->references('id')->on('tables')->onDelete('cascade');
            $table->index(['restaurant_id', 'code']);
        });

        // Вызовы официанта
        Schema::create('waiter_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->unsignedBigInteger('table_id');
            $table->enum('type', ['waiter', 'bill', 'help'])->default('waiter');
            $table->enum('status', ['pending', 'accepted', 'completed', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('accepted_by')->nullable(); // официант
            $table->text('message')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['restaurant_id', 'status']);
            $table->index(['table_id', 'status']);
        });

        // Отзывы
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('table_id')->nullable();
            $table->string('guest_name', 100)->nullable();
            $table->string('guest_phone', 20)->nullable();
            $table->integer('rating')->default(5);          // 1-5 звёзд
            $table->integer('food_rating')->nullable();     // еда
            $table->integer('service_rating')->nullable();  // сервис
            $table->integer('atmosphere_rating')->nullable(); // атмосфера
            $table->text('comment')->nullable();
            $table->text('admin_response')->nullable();     // ответ администратора
            $table->boolean('is_published')->default(false);
            $table->string('source', 20)->default('qr');    // qr, web, app
            $table->timestamps();
            
            $table->index(['restaurant_id', 'is_published']);
            $table->index(['restaurant_id', 'rating']);
        });

        // Настройки гостевого меню
        Schema::create('guest_menu_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->string('key', 50);
            $table->text('value')->nullable();
            $table->timestamps();
            
            $table->unique(['restaurant_id', 'key']);
        });

        // Базовые настройки
        DB::table('guest_menu_settings')->insert([
            ['restaurant_id' => 1, 'key' => 'restaurant_name', 'value' => 'Ресторан PosLab', 'created_at' => now(), 'updated_at' => now()],
            ['restaurant_id' => 1, 'key' => 'restaurant_logo', 'value' => null, 'created_at' => now(), 'updated_at' => now()],
            ['restaurant_id' => 1, 'key' => 'primary_color', 'value' => '#f97316', 'created_at' => now(), 'updated_at' => now()],
            ['restaurant_id' => 1, 'key' => 'welcome_text', 'value' => 'Добро пожаловать!', 'created_at' => now(), 'updated_at' => now()],
            ['restaurant_id' => 1, 'key' => 'show_prices', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
            ['restaurant_id' => 1, 'key' => 'allow_waiter_call', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
            ['restaurant_id' => 1, 'key' => 'allow_reviews', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
            ['restaurant_id' => 1, 'key' => 'wifi_name', 'value' => 'PosLab_Guest', 'created_at' => now(), 'updated_at' => now()],
            ['restaurant_id' => 1, 'key' => 'wifi_password', 'value' => 'welcome123', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_menu_settings');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('waiter_calls');
        Schema::dropIfExists('table_qr_codes');
    }
};
