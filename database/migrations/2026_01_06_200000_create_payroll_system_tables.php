<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Расширяем work_sessions если таблица уже существует
        if (Schema::hasTable('work_sessions')) {
            Schema::table('work_sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('work_sessions', 'status')) {
                    $table->enum('status', ['active', 'completed', 'corrected'])->default('active');
                }
                if (!Schema::hasColumn('work_sessions', 'corrected_by')) {
                    $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('work_sessions', 'correction_reason')) {
                    $table->string('correction_reason')->nullable();
                }
                if (!Schema::hasColumn('work_sessions', 'original_clock_in')) {
                    $table->timestamp('original_clock_in')->nullable();
                }
                if (!Schema::hasColumn('work_sessions', 'original_clock_out')) {
                    $table->timestamp('original_clock_out')->nullable();
                }
            });
        }

        // Расчётные периоды (обычно месяц)
        if (!Schema::hasTable('salary_periods')) {
            Schema::create('salary_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->string('name'); // "Январь 2026", "Февраль 2026"
                $table->date('start_date');
                $table->date('end_date');
                $table->enum('status', ['draft', 'calculating', 'calculated', 'approved', 'paid', 'closed'])->default('draft');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['restaurant_id', 'start_date', 'end_date']);
                $table->index(['restaurant_id', 'status']);
            });
        }

        // Расчёт зарплаты по сотруднику за период
        if (!Schema::hasTable('salary_calculations')) {
            Schema::create('salary_calculations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('salary_period_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();

                // Данные о ставке (копируются на момент расчёта)
                $table->enum('salary_type', ['fixed', 'hourly', 'mixed', 'percent'])->default('fixed');
                $table->decimal('base_salary', 10, 2)->default(0)->comment('Базовый оклад');
                $table->decimal('hourly_rate', 10, 2)->nullable()->comment('Ставка за час');
                $table->decimal('percent_rate', 5, 2)->nullable()->comment('Процент от продаж');

                // Отработанное время
                $table->decimal('hours_worked', 8, 2)->default(0)->comment('Отработано часов');
                $table->decimal('overtime_hours', 8, 2)->default(0)->comment('Сверхурочные часы');
                $table->integer('days_worked')->default(0)->comment('Отработано дней');
                $table->integer('work_days_in_period')->default(0)->comment('Рабочих дней в периоде');

                // Продажи (для процентной ставки)
                $table->decimal('sales_amount', 12, 2)->default(0)->comment('Сумма продаж');
                $table->integer('orders_count')->default(0)->comment('Количество заказов');

                // Расчёт
                $table->decimal('base_amount', 10, 2)->default(0)->comment('Оклад за период');
                $table->decimal('hourly_amount', 10, 2)->default(0)->comment('За отработанные часы');
                $table->decimal('overtime_amount', 10, 2)->default(0)->comment('За сверхурочные');
                $table->decimal('percent_amount', 10, 2)->default(0)->comment('Процент от продаж');
                $table->decimal('bonus_amount', 10, 2)->default(0)->comment('Премии');
                $table->decimal('penalty_amount', 10, 2)->default(0)->comment('Штрафы');
                $table->decimal('advance_paid', 10, 2)->default(0)->comment('Выплачено авансом');

                $table->decimal('gross_amount', 10, 2)->default(0)->comment('Начислено всего');
                $table->decimal('deductions', 10, 2)->default(0)->comment('Удержания');
                $table->decimal('net_amount', 10, 2)->default(0)->comment('К выплате');
                $table->decimal('paid_amount', 10, 2)->default(0)->comment('Фактически выплачено');
                $table->decimal('balance', 10, 2)->default(0)->comment('Остаток к выплате');

                $table->enum('status', ['draft', 'calculated', 'approved', 'partially_paid', 'paid'])->default('draft');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['salary_period_id', 'user_id']);
                $table->index(['user_id', 'status']);
            });
        }

        // Обновляем salary_payments
        if (Schema::hasTable('salary_payments')) {
            Schema::table('salary_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('salary_payments', 'salary_calculation_id')) {
                    $table->foreignId('salary_calculation_id')->nullable()
                          ->constrained()->nullOnDelete();
                }
                if (!Schema::hasColumn('salary_payments', 'salary_period_id')) {
                    $table->foreignId('salary_period_id')->nullable()
                          ->constrained()->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('salary_payments', function (Blueprint $table) {
            if (Schema::hasColumn('salary_payments', 'salary_calculation_id')) {
                $table->dropForeign(['salary_calculation_id']);
                $table->dropColumn('salary_calculation_id');
            }
            if (Schema::hasColumn('salary_payments', 'salary_period_id')) {
                $table->dropForeign(['salary_period_id']);
                $table->dropColumn('salary_period_id');
            }
        });

        Schema::dropIfExists('salary_calculations');
        Schema::dropIfExists('salary_periods');

        Schema::table('work_sessions', function (Blueprint $table) {
            $columns = ['status', 'corrected_by', 'correction_reason', 'original_clock_in', 'original_clock_out'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('work_sessions', $col)) {
                    if ($col === 'corrected_by') {
                        $table->dropForeign(['corrected_by']);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
