<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryCalculation extends Model
{
    use BelongsToRestaurant;
    protected $fillable = [
        'salary_period_id',
        'user_id',
        'restaurant_id',
        'salary_type',
        'base_salary',
        'hourly_rate',
        'percent_rate',
        'hours_worked',
        'overtime_hours',
        'days_worked',
        'work_days_in_period',
        'sales_amount',
        'orders_count',
        'base_amount',
        'hourly_amount',
        'overtime_amount',
        'percent_amount',
        'bonus_amount',
        'penalty_amount',
        'advance_paid',
        'gross_amount',
        'deductions',
        'net_amount',
        'paid_amount',
        'balance',
        'status',
        'notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'percent_rate' => 'decimal:2',
        'hours_worked' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'sales_amount' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'hourly_amount' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'percent_amount' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'advance_paid' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    protected $appends = ['status_label', 'salary_type_label'];

    // Relationships
    public function period(): BelongsTo
    {
        return $this->belongsTo(SalaryPeriod::class, 'salary_period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft' => 'Черновик',
            'calculated' => 'Рассчитано',
            'approved' => 'Утверждено',
            'partially_paid' => 'Частично выплачено',
            'paid' => 'Выплачено',
            default => $this->status,
        };
    }

    public function getSalaryTypeLabelAttribute(): string
    {
        return match($this->salary_type) {
            'fixed' => 'Оклад',
            'hourly' => 'Почасовая',
            'mixed' => 'Смешанная',
            'percent' => 'Процент',
            default => $this->salary_type,
        };
    }

    // Methods
    public function calculate(): self
    {
        // Сбрасываем суммы
        $this->base_amount = 0;
        $this->hourly_amount = 0;
        $this->overtime_amount = 0;
        $this->percent_amount = 0;

        // Стандартные рабочие часы в день
        $standardHoursPerDay = 8;
        $overtimeMultiplier = 1.5;

        switch ($this->salary_type) {
            case 'fixed':
                // Оклад - пропорционально отработанным дням
                if ($this->work_days_in_period > 0) {
                    $dailyRate = $this->base_salary / $this->work_days_in_period;
                    $this->base_amount = $dailyRate * $this->days_worked;
                } else {
                    $this->base_amount = $this->base_salary;
                }
                break;

            case 'hourly':
                // Почасовая оплата
                $regularHours = min($this->hours_worked, $this->days_worked * $standardHoursPerDay);
                $overtimeHours = max(0, $this->hours_worked - $regularHours);

                $this->hourly_amount = $regularHours * $this->hourly_rate;
                $this->overtime_hours = $overtimeHours;
                $this->overtime_amount = $overtimeHours * $this->hourly_rate * $overtimeMultiplier;
                break;

            case 'mixed':
                // Оклад + почасовая
                if ($this->work_days_in_period > 0) {
                    $dailyRate = $this->base_salary / $this->work_days_in_period;
                    $this->base_amount = $dailyRate * $this->days_worked;
                }

                // Дополнительные часы сверх нормы
                $standardHours = $this->days_worked * $standardHoursPerDay;
                $extraHours = max(0, $this->hours_worked - $standardHours);
                $this->hourly_amount = $extraHours * ($this->hourly_rate ?? 0);
                break;

            case 'percent':
                // Процент от продаж
                $this->percent_amount = $this->sales_amount * ($this->percent_rate / 100);
                break;
        }

        // Получаем бонусы и штрафы за период
        $bonuses = SalaryPayment::where('user_id', $this->user_id)
            ->where('salary_period_id', $this->salary_period_id)
            ->where('type', 'bonus')
            ->where('status', '!=', 'cancelled')
            ->sum('amount');

        $penalties = SalaryPayment::where('user_id', $this->user_id)
            ->where('salary_period_id', $this->salary_period_id)
            ->where('type', 'penalty')
            ->where('status', '!=', 'cancelled')
            ->sum('amount');

        $this->bonus_amount = abs($bonuses);
        $this->penalty_amount = abs($penalties);

        // Итого начислено
        $this->gross_amount = $this->base_amount + $this->hourly_amount +
                              $this->overtime_amount + $this->percent_amount +
                              $this->bonus_amount;

        // Удержания (штрафы)
        $this->deductions = $this->penalty_amount;

        // К выплате
        $this->net_amount = max(0, $this->gross_amount - $this->deductions);

        return $this;
    }

    public function addPayment(float $amount, string $type = 'salary', ?string $description = null, ?int $createdBy = null): SalaryPayment
    {
        $payment = SalaryPayment::create([
            'restaurant_id' => $this->restaurant_id,
            'salary_calculation_id' => $this->id,
            'salary_period_id' => $this->salary_period_id,
            'user_id' => $this->user_id,
            'created_by' => $createdBy,
            'type' => $type,
            'amount' => $amount,
            'status' => 'paid',
            'paid_at' => now(),
            'description' => $description,
        ]);

        // Обновляем paid_amount и balance
        $this->recalculatePaidAmount();

        return $payment;
    }

    public function recalculatePaidAmount(): void
    {
        $paidAmount = SalaryPayment::where('salary_calculation_id', $this->id)
            ->whereIn('type', ['salary', 'advance'])
            ->where('status', '!=', 'cancelled')
            ->sum('amount');

        $this->paid_amount = abs($paidAmount);
        $this->balance = $this->net_amount - $this->paid_amount;

        if ($this->balance <= 0) {
            $this->status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partially_paid';
        }

        $this->save();
    }

    public function getBreakdown(): array
    {
        return [
            'Базовый оклад' => $this->base_amount,
            'За часы работы' => $this->hourly_amount,
            'Сверхурочные' => $this->overtime_amount,
            'Процент от продаж' => $this->percent_amount,
            'Премии' => $this->bonus_amount,
            'Итого начислено' => $this->gross_amount,
            'Штрафы' => -$this->penalty_amount,
            'К выплате' => $this->net_amount,
            'Выплачено авансом' => -$this->advance_paid,
            'Остаток' => $this->balance,
        ];
    }
}
