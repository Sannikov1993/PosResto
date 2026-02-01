<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;



// ============================================

class PrintJob extends Model
{
    use BelongsToRestaurant;
    protected $fillable = [
        'restaurant_id',
        'printer_id',
        'order_id',
        'type',
        'status',
        'content',
        'error_message',
        'attempts',
        'printed_at',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
    ];

    protected $appends = ['type_label', 'status_label'];

    const STATUS_PENDING = 'pending';
    const STATUS_PRINTING = 'printing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    // Relationships
    public function printer()
    {
        return $this->belongsTo(Printer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Accessors
    public function getTypeLabelAttribute()
    {
        return [
            'receipt' => 'Чек',
            'kitchen' => 'Кухня',
            'precheck' => 'Пречек',
            'report' => 'Отчёт',
        ][$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute()
    {
        return [
            'pending' => 'В очереди',
            'printing' => 'Печатается',
            'completed' => 'Напечатано',
            'failed' => 'Ошибка',
        ][$this->status] ?? $this->status;
    }

    // Methods
    public function process(): array
    {
        if ($this->status === 'completed') {
            return ['success' => true, 'message' => 'Уже напечатано'];
        }

        $this->update(['status' => 'printing', 'attempts' => $this->attempts + 1]);

        $result = $this->printer->send($this->content);

        if ($result['success']) {
            $this->update([
                'status' => 'completed',
                'printed_at' => now(),
            ]);
        } else {
            $this->update([
                'status' => $this->attempts >= 3 ? 'failed' : 'pending',
                'error_message' => $result['message'],
            ]);
        }

        return $result;
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
