<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPayment extends Model
{
    protected $fillable = [
        'restaurant_id',
        'salary_calculation_id',
        'salary_period_id',
        'user_id',
        'created_by',
        'type',
        'amount',
        'hours_worked',
        'period_start',
        'period_end',
        'status',
        'paid_at',
        'payment_method',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'hours_worked' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
    ];

    // Типы начислений
    const TYPE_SALARY = 'salary';
    const TYPE_ADVANCE = 'advance';
    const TYPE_BONUS = 'bonus';
    const TYPE_PENALTY = 'penalty';
    const TYPE_OVERTIME = 'overtime';

    // Статусы
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    // Relationships
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(SalaryCalculation::class, 'salary_calculation_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(SalaryPeriod::class, 'salary_period_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForPeriod($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    // Methods
    public function markAsPaid(string $method = null): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'payment_method' => $method,
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    public static function getTypes(): array
    {
        return [
            self::TYPE_SALARY => 'Зарплата',
            self::TYPE_ADVANCE => 'Аванс',
            self::TYPE_BONUS => 'Премия',
            self::TYPE_PENALTY => 'Штраф',
            self::TYPE_OVERTIME => 'Переработка',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Ожидает',
            self::STATUS_PAID => 'Выплачено',
            self::STATUS_CANCELLED => 'Отменено',
        ];
    }
}
