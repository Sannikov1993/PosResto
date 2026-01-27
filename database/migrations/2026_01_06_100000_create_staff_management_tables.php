<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Приглашения для регистрации сотрудников
        if (!Schema::hasTable('staff_invitations')) {
            Schema::create('staff_invitations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('token', 64)->unique();
                $table->string('email')->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('name')->nullable()->comment('Предзаполненное имя');
                $table->string('role', 30)->default('waiter');
                $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
                $table->enum('salary_type', ['fixed', 'hourly', 'mixed', 'percent'])->default('fixed');
                $table->decimal('salary_amount', 10, 2)->default(0);
                $table->decimal('hourly_rate', 10, 2)->nullable();
                $table->decimal('percent_rate', 5, 2)->nullable()->comment('Процент от продаж');
                $table->json('permissions')->nullable()->comment('Дополнительные права');
                $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])->default('pending');
                $table->timestamp('expires_at');
                $table->timestamp('accepted_at')->nullable();
                $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['restaurant_id', 'status']);
                $table->index('token');
            });
        }

        // Расширяем таблицу users для зарплаты
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'salary_type')) {
                $table->enum('salary_type', ['fixed', 'hourly', 'mixed', 'percent'])->default('fixed')->after('salary');
            }
            if (!Schema::hasColumn('users', 'hourly_rate')) {
                $table->decimal('hourly_rate', 10, 2)->nullable()->after('salary_type');
            }
            if (!Schema::hasColumn('users', 'percent_rate')) {
                $table->decimal('percent_rate', 5, 2)->nullable()->after('hourly_rate')->comment('Процент от продаж');
            }
            if (!Schema::hasColumn('users', 'bank_card')) {
                $table->string('bank_card', 20)->nullable()->after('percent_rate');
            }
            if (!Schema::hasColumn('users', 'passport_data')) {
                $table->text('passport_data')->nullable()->after('bank_card');
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->string('address')->nullable()->after('passport_data');
            }
            if (!Schema::hasColumn('users', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('address');
            }
            if (!Schema::hasColumn('users', 'emergency_contact')) {
                $table->string('emergency_contact')->nullable()->after('birth_date');
            }
            if (!Schema::hasColumn('users', 'emergency_phone')) {
                $table->string('emergency_phone', 20)->nullable()->after('emergency_contact');
            }
            if (!Schema::hasColumn('users', 'invitation_id')) {
                $table->foreignId('invitation_id')->nullable()->after('role_id');
            }
            if (!Schema::hasColumn('users', 'fired_at')) {
                $table->timestamp('fired_at')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('users', 'fire_reason')) {
                $table->string('fire_reason')->nullable()->after('fired_at');
            }
        });

        // Таблица начислений зарплаты
        if (!Schema::hasTable('salary_payments')) {
            Schema::create('salary_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('type', ['salary', 'advance', 'bonus', 'penalty', 'overtime'])->default('salary');
                $table->decimal('amount', 10, 2);
                $table->decimal('hours_worked', 6, 2)->nullable();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
                $table->timestamp('paid_at')->nullable();
                $table->string('payment_method', 30)->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index(['restaurant_id', 'user_id']);
                $table->index(['user_id', 'period_start', 'period_end']);
            });
        }

        // Таблица учёта рабочего времени
        if (!Schema::hasTable('work_sessions')) {
            Schema::create('work_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamp('clock_in');
                $table->timestamp('clock_out')->nullable();
                $table->decimal('hours_worked', 6, 2)->nullable();
                $table->decimal('break_minutes', 5, 2)->default(0);
                $table->text('notes')->nullable();
                $table->string('clock_in_ip', 45)->nullable();
                $table->string('clock_out_ip', 45)->nullable();
                $table->timestamps();

                $table->index(['restaurant_id', 'user_id']);
                $table->index(['user_id', 'clock_in']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('work_sessions');
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('staff_invitations');

        Schema::table('users', function (Blueprint $table) {
            $columns = ['salary_type', 'hourly_rate', 'percent_rate', 'bank_card', 'passport_data',
                       'address', 'birth_date', 'emergency_contact', 'emergency_phone',
                       'invitation_id', 'fired_at', 'fire_reason'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
