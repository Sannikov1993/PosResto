<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Traits\BelongsToRestaurant;

class Reservation extends Model
{
    use HasFactory;
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'table_id',
        'linked_table_ids',
        'customer_id',
        'guest_name',
        'guest_phone',
        'guest_email',
        'date',
        'time_from',
        'time_to',
        'guests_count',
        'status',
        'notes',
        'special_requests',
        'deposit',
        'deposit_paid',
        'deposit_status',
        'deposit_paid_at',
        'deposit_paid_by',
        'deposit_payment_method',
        'deposit_operation_id',
        'reminder_sent',
        'reminder_sent_at',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    // Статусы депозита
    const DEPOSIT_PENDING = 'pending';       // Ожидает оплаты
    const DEPOSIT_PAID = 'paid';             // Оплачен
    const DEPOSIT_REFUNDED = 'refunded';     // Возвращён
    const DEPOSIT_TRANSFERRED = 'transferred'; // Переведён в заказ

    protected $casts = [
        'date' => 'date',
        'deposit' => 'decimal:2',
        'deposit_paid' => 'boolean',
        'deposit_paid_at' => 'datetime',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'linked_table_ids' => 'array',
    ];

    protected $appends = ['time_range', 'status_label', 'is_past', 'deposit_status_label', 'tables'];

    // Relationships
    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function depositPaidBy()
    {
        return $this->belongsTo(User::class, 'deposit_paid_by');
    }

    public function depositOperation()
    {
        return $this->belongsTo(CashOperation::class, 'deposit_operation_id');
    }

    // Связанные столы (для объединённой брони)
    public function linkedTables()
    {
        $ids = $this->linked_table_ids ?? [];
        return Table::whereIn('id', $ids)->get();
    }

    // Все столы брони (основной + связанные)
    public function getAllTables()
    {
        $ids = array_merge([$this->table_id], $this->linked_table_ids ?? []);
        return Table::whereIn('id', array_unique($ids))->get();
    }

    // Accessors
    public function getTimeRangeAttribute()
    {
        return Carbon::parse($this->time_from)->format('H:i') . ' - ' . Carbon::parse($this->time_to)->format('H:i');
    }

    public function getStatusLabelAttribute()
    {
        return [
            'pending' => 'Ожидает',
            'confirmed' => 'Подтверждено',
            'seated' => 'Гости сели',
            'completed' => 'Завершено',
            'cancelled' => 'Отменено',
            'no_show' => 'Не пришли',
        ][$this->status] ?? $this->status;
    }

    public function getIsPastAttribute()
    {
        return Carbon::parse($this->date)->addDay()->isPast();
    }

    public function getDepositStatusLabelAttribute()
    {
        return [
            self::DEPOSIT_PENDING => 'Ожидает',
            self::DEPOSIT_PAID => 'Оплачен',
            self::DEPOSIT_REFUNDED => 'Возвращён',
            self::DEPOSIT_TRANSFERRED => 'В заказе',
        ][$this->deposit_status] ?? $this->deposit_status;
    }

    /**
     * Получить все столы брони (основной + связанные)
     */
    public function getTablesAttribute()
    {
        $ids = array_merge([$this->table_id], $this->linked_table_ids ?? []);
        return Table::whereIn('id', array_unique(array_filter($ids)))->get(['id', 'number', 'zone_id']);
    }

    // Scopes
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeForTable($query, $tableId)
    {
        return $query->where('table_id', $tableId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'confirmed', 'seated']);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', Carbon::today())
                     ->whereIn('status', ['pending', 'confirmed']);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('date', Carbon::today());
    }

    // Methods
    public function confirm($userId = null)
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_by' => $userId,
            'confirmed_at' => now(),
        ]);
    }

    public function cancel($reason = null)
    {
        $this->update([
            'status' => 'cancelled',
            'notes' => $reason ? $this->notes . "\nПричина отмены: " . $reason : $this->notes,
        ]);
    }

    public function seat()
    {
        $this->update(['status' => 'seated']);

        // Занять все столы брони
        $allTables = $this->getAllTables();
        foreach ($allTables as $table) {
            $table->update(['status' => 'occupied']);
        }
    }

    public function unseat()
    {
        $this->update(['status' => 'confirmed']);

        // Проверяем, есть ли другие активные заказы на этих столах
        $allTables = $this->getAllTables();
        foreach ($allTables as $table) {
            // Проверяем есть ли другие seated брони или активные заказы
            $hasOtherActivity = Reservation::where('table_id', $table->id)
                ->where('id', '!=', $this->id)
                ->where('status', 'seated')
                ->exists();

            if (!$hasOtherActivity) {
                $hasActiveOrders = \App\Models\Order::where('table_id', $table->id)
                    ->whereIn('status', ['pending', 'preparing', 'ready', 'served'])
                    ->exists();

                if (!$hasActiveOrders) {
                    $table->update(['status' => 'free']);
                }
            }
        }
    }

    public function complete()
    {
        $this->update(['status' => 'completed']);

        // Освободить все столы брони
        $allTables = $this->getAllTables();
        foreach ($allTables as $table) {
            $table->update(['status' => 'free']);
        }
    }

    public function markNoShow()
    {
        $this->update(['status' => 'no_show']);
    }

    /**
     * Оплатить депозит
     */
    public function payDeposit(string $paymentMethod, int $userId, ?int $operationId = null): void
    {
        $this->update([
            'deposit_paid' => true,
            'deposit_status' => self::DEPOSIT_PAID,
            'deposit_paid_at' => now(),
            'deposit_paid_by' => $userId,
            'deposit_payment_method' => $paymentMethod,
            'deposit_operation_id' => $operationId,
        ]);
    }

    /**
     * Вернуть депозит
     */
    public function refundDeposit(): void
    {
        $this->update([
            'deposit_status' => self::DEPOSIT_REFUNDED,
        ]);
    }

    /**
     * Перевести депозит в заказ
     */
    public function transferDeposit(): void
    {
        $this->update([
            'deposit_status' => self::DEPOSIT_TRANSFERRED,
        ]);
    }

    /**
     * Депозит оплачен?
     */
    public function isDepositPaid(): bool
    {
        return $this->deposit_status === self::DEPOSIT_PAID;
    }

    /**
     * Можно принять оплату депозита?
     */
    public function canPayDeposit(): bool
    {
        return $this->deposit > 0
            && in_array($this->deposit_status, [self::DEPOSIT_PENDING, self::DEPOSIT_REFUNDED])
            && in_array($this->status, ['pending', 'confirmed']);
    }

    /**
     * Можно вернуть депозит?
     */
    public function canRefundDeposit(): bool
    {
        return $this->deposit > 0
            && $this->deposit_status === self::DEPOSIT_PAID
            && in_array($this->status, ['pending', 'confirmed', 'cancelled', 'no_show']);
    }

    // Check if time slot conflicts with existing reservations (учитывает linked_table_ids)
    public static function hasConflict($tableId, $date, $timeFrom, $timeTo, $excludeId = null)
    {
        $tableId = (int) $tableId;
        $query = self::where(function($q) use ($tableId) {
                $q->where('table_id', $tableId)
                  ->orWhereJsonContains('linked_table_ids', $tableId)
                  ->orWhereJsonContains('linked_table_ids', (string) $tableId);
            })
            ->whereDate('date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'seated'])
            ->where(function ($q) use ($timeFrom, $timeTo) {
                $q->where(function ($q2) use ($timeFrom, $timeTo) {
                    // Новое бронирование начинается во время существующего
                    $q2->where('time_from', '<=', $timeFrom)
                       ->where('time_to', '>', $timeFrom);
                })->orWhere(function ($q2) use ($timeFrom, $timeTo) {
                    // Новое бронирование заканчивается во время существующего
                    $q2->where('time_from', '<', $timeTo)
                       ->where('time_to', '>=', $timeTo);
                })->orWhere(function ($q2) use ($timeFrom, $timeTo) {
                    // Новое бронирование полностью внутри существующего
                    $q2->where('time_from', '>=', $timeFrom)
                       ->where('time_to', '<=', $timeTo);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Проверка конфликта с блокировкой (для предотвращения race condition)
     * Учитывает linked_table_ids
     */
    public static function hasConflictWithLock($tableId, $date, $timeFrom, $timeTo, $excludeId = null)
    {
        $timeFrom = \Carbon\Carbon::parse($timeFrom)->format('H:i:s');
        $timeTo = \Carbon\Carbon::parse($timeTo)->format('H:i:s');
        $tableId = (int) $tableId;

        $query = self::lockForUpdate()
            ->where(function($q) use ($tableId) {
                $q->where('table_id', $tableId)
                  ->orWhereJsonContains('linked_table_ids', $tableId)
                  ->orWhereJsonContains('linked_table_ids', (string) $tableId);
            })
            ->whereDate('date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'seated'])
            ->where(function ($q) use ($timeFrom, $timeTo) {
                $q->where(function ($q2) use ($timeFrom, $timeTo) {
                    $q2->where('time_from', '<=', $timeFrom)
                       ->where('time_to', '>', $timeFrom);
                })->orWhere(function ($q2) use ($timeFrom, $timeTo) {
                    $q2->where('time_from', '<', $timeTo)
                       ->where('time_to', '>=', $timeTo);
                })->orWhere(function ($q2) use ($timeFrom, $timeTo) {
                    $q2->where('time_from', '>=', $timeFrom)
                       ->where('time_to', '<=', $timeTo);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
