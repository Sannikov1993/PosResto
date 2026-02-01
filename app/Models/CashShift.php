<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CashShift extends Model
{
    use HasFactory;

    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'restaurant_id',
        'cashier_id',
        'cash_register_id',
        'shift_number',
        'status',
        'opening_amount',
        'closing_amount',
        'expected_amount',
        'difference',
        'total_cash',
        'total_card',
        'total_online',
        'orders_count',
        'refunds_count',
        'refunds_amount',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'opening_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'difference' => 'decimal:2',
        'total_cash' => 'decimal:2',
        'total_card' => 'decimal:2',
        'total_online' => 'decimal:2',
        'refunds_amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(CashOperation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShiftEvent::class, 'cash_shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public static function getCurrentShift(int $restaurantId): ?self
    {
        return static::where('restaurant_id', $restaurantId)
            ->where('status', self::STATUS_OPEN)
            ->latest('opened_at')
            ->first();
    }

    public static function openShift(int $restaurantId, ?int $cashierId = null, float $openingAmount = 0): self
    {
        return DB::transaction(function () use ($restaurantId, $cashierId, $openingAmount) {
            // Блокируем все смены ресторана для проверки
            $existingOpen = static::where('restaurant_id', $restaurantId)
                ->where('status', self::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            // Если уже есть открытая смена - возвращаем её
            if ($existingOpen) {
                return $existingOpen;
            }

            $today = now()->toDateString();
            $todayShiftsCount = static::where('restaurant_id', $restaurantId)
                ->whereDate('opened_at', $today)
                ->count();

            $shiftNumber = now()->format('dmy') . '-' . str_pad($todayShiftsCount + 1, 2, '0', STR_PAD_LEFT);

            $shift = static::create([
                'restaurant_id' => $restaurantId,
                'cashier_id' => $cashierId,
                'shift_number' => $shiftNumber,
                'status' => self::STATUS_OPEN,
                'opening_amount' => $openingAmount,
                'opened_at' => now(),
            ]);

            return $shift;
        });
    }

    public function closeShift(float $closingAmount): self
    {
        return DB::transaction(function () use ($closingAmount) {
            // Блокируем смену для закрытия
            $shift = static::lockForUpdate()->find($this->id);

            if (!$shift || $shift->status !== self::STATUS_OPEN) {
                return $this; // Смена уже закрыта
            }

            $expectedAmount = $shift->calculateExpectedAmount();

            $shift->update([
                'status' => self::STATUS_CLOSED,
                'closing_amount' => $closingAmount,
                'expected_amount' => $expectedAmount,
                'difference' => $closingAmount - $expectedAmount,
                'closed_at' => now(),
            ]);

            ShiftEvent::recordClose($shift, $closingAmount);

            $this->refresh();
            return $this;
        });
    }

    public function calculateExpectedAmount(): float
    {
        $cashIncome = $this->operations()
            ->where('payment_method', 'cash')
            ->whereIn('type', ['income', 'deposit'])
            ->sum('amount');

        $cashExpense = $this->operations()
            ->where('payment_method', 'cash')
            ->whereIn('type', ['expense', 'withdrawal'])
            ->sum('amount');

        return $this->opening_amount + $cashIncome - $cashExpense;
    }

    /**
     * Обновить итоги смены (атомарно с блокировкой)
     */
    public function updateTotals(): void
    {
        DB::transaction(function () {
            $shift = static::lockForUpdate()->find($this->id);
            if (!$shift) {
                return;
            }

            $totals = $shift->operations()
                ->where('type', 'income')
                ->selectRaw("
                    SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as total_cash,
                    SUM(CASE WHEN payment_method = 'card' THEN amount ELSE 0 END) as total_card,
                    SUM(CASE WHEN payment_method = 'online' THEN amount ELSE 0 END) as total_online,
                    COUNT(DISTINCT order_id) as orders_count
                ")
                ->first();

            $refunds = $shift->operations()
                ->where('type', 'expense')
                ->where('category', 'refund')
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
                ->first();

            $shift->update([
                'total_cash' => $totals->total_cash ?? 0,
                'total_card' => $totals->total_card ?? 0,
                'total_online' => $totals->total_online ?? 0,
                'orders_count' => $totals->orders_count ?? 0,
                'refunds_count' => $refunds->count ?? 0,
                'refunds_amount' => $refunds->amount ?? 0,
            ]);

            $this->refresh();
        });
    }

    public function getTotalRevenueAttribute(): float
    {
        return floatval($this->total_cash ?? 0) + floatval($this->total_card ?? 0) + floatval($this->total_online ?? 0);
    }

    public function getAvgCheckAttribute(): float
    {
        $ordersCount = intval($this->orders_count ?? 0);
        if ($ordersCount <= 0) {
            return 0;
        }
        return round($this->total_revenue / $ordersCount, 2);
    }

    public function getRefundsAttribute(): float
    {
        return floatval($this->refunds_amount ?? 0);
    }

    /**
     * Текущая сумма наличных в кассе
     */
    public function getCurrentCashAttribute(): float
    {
        return $this->calculateExpectedAmount();
    }

    protected $appends = [
        'total_revenue',
        'avg_check',
        'refunds',
        'current_cash',
    ];
}
