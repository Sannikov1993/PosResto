<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'name',
        'phone',
        'email',
        'birth_date',
        'notes',
        'bonus_points',
        'total_orders',
        'total_spent',
        'last_order_at',
        'is_blacklisted',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'bonus_points' => 'integer',
        'total_orders' => 'integer',
        'total_spent' => 'decimal:2',
        'last_order_at' => 'datetime',
        'is_blacklisted' => 'boolean',
    ];

    // ===== RELATIONSHIPS =====

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function defaultAddress()
    {
        return $this->hasOne(CustomerAddress::class)->where('is_default', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    // ===== SCOPES =====

    public function scopeActive($query)
    {
        return $query->where('is_blacklisted', false);
    }

    public function scopeBlacklisted($query)
    {
        return $query->where('is_blacklisted', true);
    }

    public function scopeByPhone($query, string $phone)
    {
        // Нормализуем телефон для поиска
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return $query->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') LIKE ?", ["%{$phone}%"]);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    public function scopeTopCustomers($query, int $limit = 10)
    {
        return $query->orderByDesc('total_spent')->limit($limit);
    }

    // ===== HELPERS =====

    public function getDisplayName(): string
    {
        return $this->name ?: $this->phone;
    }

    public function addBonusPoints(int $points): void
    {
        $this->increment('bonus_points', $points);
    }

    public function useBonusPoints(int $points): bool
    {
        if ($this->bonus_points < $points) {
            return false;
        }
        $this->decrement('bonus_points', $points);
        return true;
    }

    public function updateStats(): void
    {
        $this->update([
            'total_orders' => $this->orders()->whereIn('status', ['completed'])->count(),
            'total_spent' => $this->orders()->whereIn('status', ['completed'])->sum('total'),
            'last_order_at' => $this->orders()->latest()->value('created_at'),
        ]);
    }

    public function blacklist(): void
    {
        $this->update(['is_blacklisted' => true]);
    }

    public function unblacklist(): void
    {
        $this->update(['is_blacklisted' => false]);
    }

    // Определение категории клиента по сумме заказов
    public function getCategory(): string
    {
        return match(true) {
            $this->total_spent >= 50000 => 'VIP',
            $this->total_spent >= 20000 => 'Постоянный',
            $this->total_spent >= 5000 => 'Активный',
            $this->total_orders >= 1 => 'Новый',
            default => 'Потенциальный',
        };
    }

    public function getCategoryColor(): string
    {
        return match($this->getCategory()) {
            'VIP' => '#F59E0B',
            'Постоянный' => '#8B5CF6',
            'Активный' => '#3B82F6',
            'Новый' => '#10B981',
            default => '#6B7280',
        };
    }

    // Дни рождения
    public function hasBirthdaySoon(int $days = 7): bool
    {
        if (!$this->birth_date) {
            return false;
        }
        
        $birthday = $this->birth_date->setYear(now()->year);
        if ($birthday->isPast()) {
            $birthday->addYear();
        }
        
        return $birthday->diffInDays(now()) <= $days;
    }

    public function isBirthdayToday(): bool
    {
        if (!$this->birth_date) {
            return false;
        }
        return $this->birth_date->isBirthday();
    }
}
