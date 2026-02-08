<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class ShiftEvent extends Model
{
    use BelongsToRestaurant;

    public $timestamps = false;

    protected $fillable = [
        'restaurant_id',
        'cash_shift_id',
        'type',
        'amount',
        'user_id',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    const TYPE_OPENED = 'opened';
    const TYPE_CLOSED = 'closed';

    public function shift()
    {
        return $this->belongsTo(CashShift::class, 'cash_shift_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function recordOpen(CashShift $shift, float $amount = 0, ?int $userId = null): self
    {
        return static::create([
            'restaurant_id' => $shift->restaurant_id,
            'cash_shift_id' => $shift->id,
            'type' => self::TYPE_OPENED,
            'amount' => $amount,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    public static function recordClose(CashShift $shift, float $amount = 0, ?int $userId = null): self
    {
        return static::create([
            'restaurant_id' => $shift->restaurant_id,
            'cash_shift_id' => $shift->id,
            'type' => self::TYPE_CLOSED,
            'amount' => $amount,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }
}
