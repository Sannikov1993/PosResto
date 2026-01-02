<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaiterCall extends Model
{
    protected $fillable = [
        'restaurant_id',
        'table_id',
        'type',
        'status',
        'accepted_by',
        'message',
        'accepted_at',
        'completed_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['type_label', 'status_label', 'type_icon', 'wait_time'];

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function getTypeLabelAttribute()
    {
        return [
            'waiter' => 'Вызов официанта',
            'bill' => 'Запрос счёта',
            'help' => 'Нужна помощь',
        ][$this->type] ?? $this->type;
    }

    public function getTypeIconAttribute()
    {
        return [
            'waiter' => '🙋',
            'bill' => '💳',
            'help' => '❓',
        ][$this->type] ?? '📢';
    }

    public function getStatusLabelAttribute()
    {
        return [
            'pending' => 'Ожидает',
            'accepted' => 'Принят',
            'completed' => 'Выполнен',
            'cancelled' => 'Отменён',
        ][$this->status] ?? $this->status;
    }

    public function getWaitTimeAttribute()
    {
        if ($this->status === 'completed' && $this->completed_at) {
            return $this->completed_at->diffInMinutes($this->created_at);
        }
        return now()->diffInMinutes($this->created_at);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'accepted']);
    }

    public function accept($userId): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_by' => $userId,
            'accepted_at' => now(),
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }
}
