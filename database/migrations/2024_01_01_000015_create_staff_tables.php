<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Смены
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->unsignedBigInteger('user_id');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'])
                  ->default('scheduled');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->index(['restaurant_id', 'date']);
            $table->index(['user_id', 'date']);
        });

        // Учёт рабочего времени
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->date('date');
            $table->timestamp('clock_in')->nullable();
            $table->timestamp('clock_out')->nullable();
            $table->integer('break_minutes')->default(0);
            $table->integer('worked_minutes')->nullable();
            $table->enum('status', ['active', 'completed', 'edited'])->default('active');
            $table->text('notes')->nullable();
            $table->string('clock_in_method', 20)->default('manual'); // manual, pin, qr
            $table->string('clock_out_method', 20)->nullable();
            $table->timestamps();
            
            $table->index(['restaurant_id', 'date']);
            $table->index(['user_id', 'date']);
        });

        // Чаевые
        Schema::create('tips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('type', ['cash', 'card', 'shared'])->default('cash');
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['restaurant_id', 'date']);
            $table->index(['user_id', 'date']);
        });

        // Настройки зарплаты
        Schema::create('salary_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('type', ['hourly', 'monthly', 'daily'])->default('hourly');
            $table->decimal('rate', 10, 2); // ставка
            $table->decimal('bonus_percent', 5, 2)->default(0); // % от продаж
            $table->boolean('tips_included')->default(true);
            $table->timestamps();
            
            $table->unique('user_id');
        });

        // Добавляем поля в users если их нет
        if (!Schema::hasColumn('users', 'position')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('position', 50)->nullable()->after('role');
                $table->string('phone', 20)->nullable()->after('email');
                $table->date('hire_date')->nullable();
                $table->boolean('is_active')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tips');
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('salary_settings');
        
        if (Schema::hasColumn('users', 'position')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['position', 'phone', 'hire_date', 'is_active']);
            });
        }
    }
};
