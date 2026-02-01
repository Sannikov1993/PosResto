<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToRestaurant;

class Notification extends Model
{
    use HasFactory, BelongsToRestaurant;

    // Notification types
    const TYPE_SHIFT_REMINDER = 'shift_reminder';
    const TYPE_SHIFT_STARTED = 'shift_started';
    const TYPE_SHIFT_ENDED = 'shift_ended';
    const TYPE_SCHEDULE_CHANGE = 'schedule_change';
    const TYPE_SCHEDULE_PUBLISHED = 'schedule_published';
    const TYPE_SALARY_PAID = 'salary_paid';
    const TYPE_SALARY_CALCULATED = 'salary_calculated';
    const TYPE_BONUS_RECEIVED = 'bonus_received';
    const TYPE_PENALTY_RECEIVED = 'penalty_received';
    const TYPE_NEW_ORDER = 'new_order';
    const TYPE_ORDER_READY = 'order_ready';
    const TYPE_SYSTEM = 'system';
    const TYPE_CUSTOM = 'custom';

    // Channels
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_TELEGRAM = 'telegram';
    const CHANNEL_PUSH = 'push';
    const CHANNEL_IN_APP = 'in_app';

    protected $fillable = [
        'user_id',
        'restaurant_id',
        'type',
        'title',
        'message',
        'data',
        'channels',
        'read_at',
        'sent_at',
        'delivery_status',
    ];

    protected $casts = [
        'data' => 'array',
        'channels' => 'array',
        'delivery_status' => 'array',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected $appends = ['is_read', 'time_ago'];

    // ==================== RELATIONSHIPS ====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    // ==================== ACCESSORS ====================

    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    // ==================== SCOPES ====================

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ==================== METHODS ====================

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    public function markAsSent(array $channelStatuses = []): void
    {
        $this->update([
            'sent_at' => now(),
            'delivery_status' => array_merge($this->delivery_status ?? [], $channelStatuses),
        ]);
    }

    public function updateDeliveryStatus(string $channel, string $status, ?string $error = null): void
    {
        $deliveryStatus = $this->delivery_status ?? [];
        $deliveryStatus[$channel] = [
            'status' => $status,
            'error' => $error,
            'at' => now()->toIso8601String(),
        ];
        $this->update(['delivery_status' => $deliveryStatus]);
    }

    // ==================== STATIC HELPERS ====================

    public static function getTypeLabel(string $type): string
    {
        return match($type) {
            self::TYPE_SHIFT_REMINDER => 'Напоминание о смене',
            self::TYPE_SHIFT_STARTED => 'Смена начата',
            self::TYPE_SHIFT_ENDED => 'Смена завершена',
            self::TYPE_SCHEDULE_CHANGE => 'Изменение расписания',
            self::TYPE_SCHEDULE_PUBLISHED => 'Расписание опубликовано',
            self::TYPE_SALARY_PAID => 'Зарплата выплачена',
            self::TYPE_SALARY_CALCULATED => 'Зарплата рассчитана',
            self::TYPE_BONUS_RECEIVED => 'Получена премия',
            self::TYPE_PENALTY_RECEIVED => 'Получен штраф',
            self::TYPE_NEW_ORDER => 'Новый заказ',
            self::TYPE_ORDER_READY => 'Заказ готов',
            self::TYPE_SYSTEM => 'Системное',
            self::TYPE_CUSTOM => 'Уведомление',
            default => 'Уведомление',
        };
    }

    public static function getTypeIcon(string $type): string
    {
        return match($type) {
            self::TYPE_SHIFT_REMINDER => '⏰',
            self::TYPE_SHIFT_STARTED => '🟢',
            self::TYPE_SHIFT_ENDED => '🔴',
            self::TYPE_SCHEDULE_CHANGE => '📅',
            self::TYPE_SCHEDULE_PUBLISHED => '📋',
            self::TYPE_SALARY_PAID => '💰',
            self::TYPE_SALARY_CALCULATED => '🧮',
            self::TYPE_BONUS_RECEIVED => '🎁',
            self::TYPE_PENALTY_RECEIVED => '⚠️',
            self::TYPE_NEW_ORDER => '🍽️',
            self::TYPE_ORDER_READY => '✅',
            self::TYPE_SYSTEM => '⚙️',
            default => '🔔',
        };
    }
}
