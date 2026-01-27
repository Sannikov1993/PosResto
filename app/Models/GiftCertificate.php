<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GiftCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'code',
        'amount',
        'balance',
        'buyer_customer_id',
        'buyer_name',
        'buyer_phone',
        'recipient_customer_id',
        'recipient_name',
        'recipient_phone',
        'payment_method',
        'sold_by_user_id',
        'status',
        'sold_at',
        'activated_at',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'sold_at' => 'date',
        'activated_at' => 'date',
        'expires_at' => 'date',
    ];

    // Статусы
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_USED = 'used';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Ожидает оплаты',
        self::STATUS_ACTIVE => 'Активен',
        self::STATUS_USED => 'Использован',
        self::STATUS_EXPIRED => 'Истёк',
        self::STATUS_CANCELLED => 'Отменён',
    ];

    public const PAYMENT_METHODS = [
        'cash' => 'Наличные',
        'card' => 'Карта',
        'online' => 'Онлайн',
    ];

    // ===== RELATIONSHIPS =====

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function buyerCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'buyer_customer_id');
    }

    public function recipientCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'recipient_customer_id');
    }

    public function soldByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by_user_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(GiftCertificateUsage::class);
    }

    // ===== SCOPES =====

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('balance', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now()->startOfDay());
            });
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }

    // ===== HELPERS =====

    /**
     * Генерация уникального кода сертификата
     */
    public static function generateCode(): string
    {
        do {
            // Формат: GC-XXXX-XXXX (12 символов)
            $code = 'GC-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Можно ли использовать сертификат
     */
    public function canBeUsed(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->balance <= 0) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->lt(now()->startOfDay())) {
            return false;
        }

        return true;
    }

    /**
     * Использовать сертификат
     */
    public function use(float $amount, ?int $orderId = null, ?int $customerId = null, ?int $usedByUserId = null): GiftCertificateUsage
    {
        if (!$this->canBeUsed()) {
            throw new \Exception('Сертификат не может быть использован');
        }

        if ($amount > $this->balance) {
            $amount = $this->balance;
        }

        $balanceBefore = $this->balance;
        $balanceAfter = $balanceBefore - $amount;

        $usage = $this->usages()->create([
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'used_by_user_id' => $usedByUserId,
        ]);

        $this->balance = $balanceAfter;

        if ($this->balance <= 0) {
            $this->status = self::STATUS_USED;
        }

        $this->save();

        return $usage;
    }

    /**
     * Активировать сертификат
     */
    public function activate(): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->activated_at = now();
        $this->sold_at = $this->sold_at ?? now();
        $this->save();
    }

    /**
     * Отменить сертификат
     */
    public function cancel(): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->save();
    }

    /**
     * Пометить как истёкший
     */
    public function markExpired(): void
    {
        $this->status = self::STATUS_EXPIRED;
        $this->save();
    }

    /**
     * Получить метку статуса
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Получить цвет статуса
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'green',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_USED => 'gray',
            self::STATUS_EXPIRED => 'orange',
            self::STATUS_CANCELLED => 'red',
            default => 'gray',
        };
    }

    /**
     * Получить метку способа оплаты
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }

    /**
     * Истёк ли сертификат
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->lt(now()->startOfDay());
    }

    /**
     * Проверить и обновить статус если истёк
     */
    public function checkExpiration(): void
    {
        if ($this->status === self::STATUS_ACTIVE && $this->isExpired()) {
            $this->markExpired();
        }
    }
}
