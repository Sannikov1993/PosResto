<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class SalaryPeriod extends Model
{
    protected $fillable = [
        'restaurant_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    protected $appends = ['status_label', 'period_label'];

    // Статусы
    const STATUS_DRAFT = 'draft';
    const STATUS_CALCULATING = 'calculating';
    const STATUS_CALCULATED = 'calculated';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID = 'paid';
    const STATUS_CLOSED = 'closed';

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(SalaryCalculation::class);
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
            'calculating' => 'Расчёт...',
            'calculated' => 'Рассчитано',
            'approved' => 'Утверждено',
            'paid' => 'Выплачено',
            'closed' => 'Закрыто',
            default => $this->status,
        };
    }

    public function getPeriodLabelAttribute(): string
    {
        if ($this->start_date && $this->end_date) {
            return $this->start_date->format('d.m') . ' - ' . $this->end_date->format('d.m.Y');
        }
        return $this->name;
    }

    // Scopes
    public function scopeForRestaurant($query, $restaurantId)
    {
        return $query->where('restaurant_id', $restaurantId);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CLOSED]);
    }

    // Methods
    public static function createForMonth(int $restaurantId, int $year, int $month, ?int $createdBy = null): self
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $monthNames = [
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
            5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
            9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
        ];

        return self::create([
            'restaurant_id' => $restaurantId,
            'name' => $monthNames[$month] . ' ' . $year,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => self::STATUS_DRAFT,
            'created_by' => $createdBy,
        ]);
    }

    public function calculateAll(): self
    {
        $this->update(['status' => self::STATUS_CALCULATING]);

        // Получаем всех активных сотрудников ресторана
        $users = User::where('restaurant_id', $this->restaurant_id)
            ->where('is_active', true)
            ->whereNotIn('role', ['owner', 'super_admin'])
            ->get();

        $totalAmount = 0;

        foreach ($users as $user) {
            $calculation = $this->calculateForUser($user);
            $totalAmount += $calculation->net_amount;
        }

        $this->update([
            'status' => self::STATUS_CALCULATED,
            'total_amount' => $totalAmount,
        ]);

        return $this;
    }

    public function calculateForUser(User $user): SalaryCalculation
    {
        // Получаем или создаём расчёт
        $calculation = SalaryCalculation::firstOrNew([
            'salary_period_id' => $this->id,
            'user_id' => $user->id,
        ]);

        $calculation->restaurant_id = $this->restaurant_id;
        $calculation->salary_type = $user->salary_type ?? 'fixed';
        $calculation->base_salary = $user->salary ?? 0;
        $calculation->hourly_rate = $user->hourly_rate;
        $calculation->percent_rate = $user->percent_rate;

        // Получаем отработанное время
        $workStats = WorkSession::getStatsForPeriod(
            $user->id,
            $this->start_date,
            $this->end_date
        );

        $calculation->hours_worked = $workStats['total_hours'];
        $calculation->days_worked = $workStats['days_worked'];

        // Рабочих дней в месяце (примерно)
        $calculation->work_days_in_period = $this->start_date->diffInWeekdays($this->end_date) + 1;

        // Получаем продажи сотрудника за период
        $salesStats = $this->getSalesForUser($user->id);
        $calculation->sales_amount = $salesStats['total'];
        $calculation->orders_count = $salesStats['count'];

        // Считаем зарплату
        $calculation->calculate();

        // Получаем уже выплаченные авансы за этот период
        $advancePaid = SalaryPayment::where('user_id', $user->id)
            ->where('salary_period_id', $this->id)
            ->whereIn('type', ['advance'])
            ->where('status', '!=', 'cancelled')
            ->sum('amount');

        $calculation->advance_paid = abs($advancePaid);
        $calculation->paid_amount = abs($advancePaid);
        $calculation->balance = $calculation->net_amount - abs($advancePaid);

        $calculation->status = 'calculated';
        $calculation->save();

        return $calculation;
    }

    private function getSalesForUser(int $userId): array
    {
        $total = Order::where('waiter_id', $userId)
            ->whereBetween('created_at', [$this->start_date, $this->end_date->endOfDay()])
            ->where('status', 'completed')
            ->sum('total');

        $count = Order::where('waiter_id', $userId)
            ->whereBetween('created_at', [$this->start_date, $this->end_date->endOfDay()])
            ->where('status', 'completed')
            ->count();

        return ['total' => $total, 'count' => $count];
    }

    public function approve(int $approverId): self
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        $this->calculations()->update(['status' => 'approved']);

        return $this;
    }

    public function markAsPaid(): self
    {
        $this->update(['status' => self::STATUS_PAID]);
        return $this;
    }

    public function close(): self
    {
        $this->update(['status' => self::STATUS_CLOSED]);
        return $this;
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Черновик',
            self::STATUS_CALCULATING => 'Расчёт...',
            self::STATUS_CALCULATED => 'Рассчитано',
            self::STATUS_APPROVED => 'Утверждено',
            self::STATUS_PAID => 'Выплачено',
            self::STATUS_CLOSED => 'Закрыто',
        ];
    }
}
