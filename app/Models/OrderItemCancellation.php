<?php

namespace App\Models;

use App\Traits\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemCancellation extends Model
{
    use HasFactory, BelongsToRestaurant;

    protected $fillable = [
        'restaurant_id',
        'order_item_id',
        'order_id',
        'dish_id',
        'product_name',
        'quantity',
        'price',
        'total',
        'previous_status',
        'cancelled_by',
        'cancelled_at',
        'reason_type',
        'reason_comment',
        'requires_approval',
        'approved_by',
        'approved_at',
        'approval_status',
        'rejection_reason',
        'kitchen_notified',
        'kitchen_notified_at',
        'kitchen_notification_method',
        'is_writeoff',
        'writeoff_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'requires_approval' => 'boolean',
        'kitchen_notified' => 'boolean',
        'is_writeoff' => 'boolean',
        'cancelled_at' => 'datetime',
        'approved_at' => 'datetime',
        'kitchen_notified_at' => 'datetime',
    ];

    // Типы причин отмены
    const REASON_GUEST_REFUSED = 'guest_refused';
    const REASON_GUEST_CHANGED_MIND = 'guest_changed_mind';
    const REASON_WRONG_ORDER = 'wrong_order';
    const REASON_OUT_OF_STOCK = 'out_of_stock';
    const REASON_QUALITY_ISSUE = 'quality_issue';
    const REASON_LONG_WAIT = 'long_wait';
    const REASON_DUPLICATE = 'duplicate';
    const REASON_OTHER = 'other';

    // Статусы подтверждения
    const APPROVAL_PENDING = 'pending';
    const APPROVAL_APPROVED = 'approved';
    const APPROVAL_REJECTED = 'rejected';

    // Статусы, требующие уведомления кухни
    const KITCHEN_NOTIFY_STATUSES = ['sent', 'cooking', 'ready'];

    // Статусы, требующие подтверждения менеджера
    const MANAGER_APPROVAL_STATUSES = ['ready', 'served'];

    // ===== RELATIONSHIPS =====

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function writeoff(): BelongsTo
    {
        return $this->belongsTo(WriteOff::class, 'writeoff_id');
    }

    // ===== SCOPES =====

    public function scopePendingApproval($query)
    {
        return $query->where('requires_approval', true)
                     ->where('approval_status', self::APPROVAL_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', self::APPROVAL_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('approval_status', self::APPROVAL_REJECTED);
    }

    public function scopeNotNotifiedKitchen($query)
    {
        return $query->where('kitchen_notified', false)
                     ->whereIn('previous_status', self::KITCHEN_NOTIFY_STATUSES);
    }

    // ===== HELPERS =====

    /**
     * Получить лейбл причины отмены
     */
    public function getReasonLabel(): string
    {
        return match($this->reason_type) {
            self::REASON_GUEST_REFUSED => 'Гость отказался',
            self::REASON_GUEST_CHANGED_MIND => 'Гость передумал',
            self::REASON_WRONG_ORDER => 'Ошибка официанта',
            self::REASON_OUT_OF_STOCK => 'Закончился товар',
            self::REASON_QUALITY_ISSUE => 'Проблема с качеством',
            self::REASON_LONG_WAIT => 'Долгое ожидание',
            self::REASON_DUPLICATE => 'Дубликат заказа',
            self::REASON_OTHER => 'Другое',
            default => $this->reason_type,
        };
    }

    /**
     * Нужно ли уведомлять кухню
     */
    public function needsKitchenNotification(): bool
    {
        return in_array($this->previous_status, self::KITCHEN_NOTIFY_STATUSES);
    }

    /**
     * Нужно ли подтверждение менеджера
     */
    public function needsManagerApproval(): bool
    {
        return in_array($this->previous_status, self::MANAGER_APPROVAL_STATUSES);
    }

    /**
     * Подтвердить отмену менеджером
     */
    public function approve(int $managerId): void
    {
        $this->update([
            'approval_status' => self::APPROVAL_APPROVED,
            'approved_by' => $managerId,
            'approved_at' => now(),
        ]);
    }

    /**
     * Отклонить отмену менеджером
     */
    public function reject(int $managerId, ?string $reason = null): void
    {
        $this->update([
            'approval_status' => self::APPROVAL_REJECTED,
            'approved_by' => $managerId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Отметить что кухня уведомлена
     */
    public function markKitchenNotified(string $method = 'kds'): void
    {
        $this->update([
            'kitchen_notified' => true,
            'kitchen_notified_at' => now(),
            'kitchen_notification_method' => $method,
        ]);
    }

    /**
     * Получить все типы причин с лейблами
     */
    public static function getReasonTypes(): array
    {
        return [
            self::REASON_GUEST_REFUSED => 'Гость отказался',
            self::REASON_GUEST_CHANGED_MIND => 'Гость передумал',
            self::REASON_WRONG_ORDER => 'Ошибка официанта',
            self::REASON_OUT_OF_STOCK => 'Закончился товар',
            self::REASON_QUALITY_ISSUE => 'Проблема с качеством',
            self::REASON_LONG_WAIT => 'Долгое ожидание',
            self::REASON_DUPLICATE => 'Дубликат заказа',
            self::REASON_OTHER => 'Другое',
        ];
    }
}
