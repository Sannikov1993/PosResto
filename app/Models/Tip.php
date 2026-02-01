<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Traits\BelongsToRestaurant;

class Tip extends Model
{
    use BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'user_id',
        'order_id',
        'amount',
        'type',
        'date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    protected $appends = ['type_label'];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Accessors
    public function getTypeLabelAttribute()
    {
        return [
            'cash' => 'Наличные',
            'card' => 'Карта',
            'shared' => 'Общие',
        ][$this->type] ?? $this->type;
    }

    // Scopes
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForPeriod($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    // Statistics
    public static function getDailyTotal($userId, $date = null)
    {
        $date = $date ?? Carbon::today();
        return self::where('user_id', $userId)
            ->whereDate('date', $date)
            ->sum('amount');
    }

    public static function getMonthlyTotal($userId, $month = null, $year = null)
    {
        $month = $month ?? Carbon::now()->month;
        $year = $year ?? Carbon::now()->year;

        return self::where('user_id', $userId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->sum('amount');
    }

    public static function getStats($userId, $from, $to)
    {
        $tips = self::where('user_id', $userId)
            ->whereBetween('date', [$from, $to])
            ->get();

        return [
            'total' => $tips->sum('amount'),
            'count' => $tips->count(),
            'avg' => $tips->count() > 0 ? round($tips->avg('amount'), 2) : 0,
            'by_type' => [
                'cash' => $tips->where('type', 'cash')->sum('amount'),
                'card' => $tips->where('type', 'card')->sum('amount'),
                'shared' => $tips->where('type', 'shared')->sum('amount'),
            ],
        ];
    }
}
