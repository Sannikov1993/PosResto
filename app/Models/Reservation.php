<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'table_id',
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
        'reminder_sent',
        'reminder_sent_at',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'deposit' => 'decimal:2',
        'deposit_paid' => 'boolean',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    protected $appends = ['time_range', 'status_label', 'is_past'];

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
        
        // Занять стол
        if ($this->table) {
            $this->table->update(['status' => 'reserved']);
        }
    }

    public function complete()
    {
        $this->update(['status' => 'completed']);
        
        // Освободить стол
        if ($this->table) {
            $this->table->update(['status' => 'free']);
        }
    }

    public function markNoShow()
    {
        $this->update(['status' => 'no_show']);
    }

    // Check if time slot conflicts with existing reservations
    public static function hasConflict($tableId, $date, $timeFrom, $timeTo, $excludeId = null)
    {
        $query = self::where('table_id', $tableId)
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
}
