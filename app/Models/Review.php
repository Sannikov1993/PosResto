<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use BelongsToRestaurant;
    protected $fillable = [
        'restaurant_id',
        'order_id',
        'table_id',
        'guest_name',
        'guest_phone',
        'rating',
        'food_rating',
        'service_rating',
        'atmosphere_rating',
        'comment',
        'admin_response',
        'is_published',
        'source',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected $appends = ['rating_stars', 'avg_rating'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function getRatingStarsAttribute()
    {
        return str_repeat('⭐', $this->rating);
    }

    public function getAvgRatingAttribute()
    {
        $ratings = array_filter([
            $this->food_rating,
            $this->service_rating,
            $this->atmosphere_rating,
        ]);
        
        return count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 1) : $this->rating;
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }

    public static function getStats($restaurantId = 1)
    {
        $reviews = self::where('restaurant_id', $restaurantId)->get();
        
        if ($reviews->isEmpty()) {
            return [
                'total' => 0,
                'average' => 0,
                'distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
                'food_avg' => 0,
                'service_avg' => 0,
                'atmosphere_avg' => 0,
            ];
        }

        $distribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $distribution[$i] = $reviews->where('rating', $i)->count();
        }

        return [
            'total' => $reviews->count(),
            'average' => round($reviews->avg('rating'), 1),
            'distribution' => $distribution,
            'food_avg' => round($reviews->whereNotNull('food_rating')->avg('food_rating') ?? 0, 1),
            'service_avg' => round($reviews->whereNotNull('service_rating')->avg('service_rating') ?? 0, 1),
            'atmosphere_avg' => round($reviews->whereNotNull('atmosphere_rating')->avg('atmosphere_rating') ?? 0, 1),
        ];
    }
}
