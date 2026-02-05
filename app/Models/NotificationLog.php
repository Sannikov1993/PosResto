<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\BelongsToRestaurant;

class NotificationLog extends Model
{
    use HasFactory, BelongsToRestaurant;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BOUNCED = 'bounced';

    // Channel constants
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_TELEGRAM = 'telegram';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_DATABASE = 'database';
    public const CHANNEL_PUSH = 'push';

    // Notification types
    public const TYPE_RESERVATION_CREATED = 'reservation_created';
    public const TYPE_RESERVATION_CONFIRMED = 'reservation_confirmed';
    public const TYPE_RESERVATION_CANCELLED = 'reservation_cancelled';
    public const TYPE_RESERVATION_REMINDER = 'reservation_reminder';
    public const TYPE_RESERVATION_SEATED = 'reservation_seated';
    public const TYPE_RESERVATION_NO_SHOW = 'reservation_no_show';
    public const TYPE_DEPOSIT_PAID = 'deposit_paid';
    public const TYPE_DEPOSIT_REFUNDED = 'deposit_refunded';

    // Retry delays in minutes (exponential backoff)
    public const RETRY_DELAYS = [1 => 5, 2 => 15, 3 => 45];

    protected $fillable = [
        'restaurant_id',
        'notifiable_type',
        'notifiable_id',
        'recipient_phone',
        'recipient_email',
        'recipient_name',
        'notification_type',
        'channel',
        'subject',
        'related_type',
        'related_id',
        'status',
        'error_message',
        'attempts',
        'max_attempts',
        'last_attempt_at',
        'next_retry_at',
        'delivered_at',
        'channel_data',
        'job_id',
        'job_queue',
    ];

    protected $casts = [
        'channel_data' => 'array',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'delivered_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    /**
     * Default attribute values.
     */
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
        'max_attempts' => 3,
    ];

    // ===== RELATIONSHIPS =====

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * The notifiable entity (Customer, User, etc.)
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The related entity (Reservation, Order, etc.)
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    // ===== SCOPES =====

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('notification_type', $type);
    }

    public function scopeRetryable($query)
    {
        return $query->where('status', self::STATUS_FAILED)
            ->whereColumn('attempts', '<', 'max_attempts');
    }

    public function scopeDueForRetry($query)
    {
        return $query->where('status', self::STATUS_FAILED)
            ->whereColumn('attempts', '<', 'max_attempts')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now());
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ===== METHODS =====

    /**
     * Mark notification as sent.
     */
    public function markSent(array $channelData = []): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'attempts' => $this->attempts + 1,
            'last_attempt_at' => now(),
            'channel_data' => array_merge($this->channel_data ?? [], $channelData),
        ]);
    }

    /**
     * Mark notification as delivered.
     */
    public function markDelivered(array $channelData = []): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
            'channel_data' => array_merge($this->channel_data ?? [], $channelData),
        ]);
    }

    /**
     * Mark notification as failed with retry scheduling.
     */
    public function markFailed(string $errorMessage, bool $scheduleRetry = true): void
    {
        $newAttempts = $this->attempts + 1;
        $maxAttempts = $this->max_attempts ?: 3;

        $data = [
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'attempts' => $newAttempts,
            'last_attempt_at' => now(),
            'next_retry_at' => null,
        ];

        // Schedule retry if allowed
        if ($scheduleRetry && $newAttempts < $maxAttempts) {
            $delayMinutes = self::RETRY_DELAYS[$newAttempts] ?? 60;
            $data['next_retry_at'] = now()->addMinutes($delayMinutes);
        }

        $this->update($data);
    }

    /**
     * Check if notification can be retried.
     */
    public function canRetry(): bool
    {
        $maxAttempts = $this->max_attempts ?: 3;

        return $this->status === self::STATUS_FAILED
            && $this->attempts < $maxAttempts;
    }

    /**
     * Check if notification is due for retry.
     */
    public function isDueForRetry(): bool
    {
        return $this->canRetry()
            && $this->next_retry_at
            && $this->next_retry_at->isPast();
    }

    /**
     * Reset for retry attempt.
     */
    public function resetForRetry(): void
    {
        $this->update([
            'status' => self::STATUS_PENDING,
            'error_message' => null,
            'next_retry_at' => null,
        ]);
    }

    /**
     * Get recipient display name.
     */
    public function getRecipientDisplayAttribute(): string
    {
        if ($this->notifiable) {
            return $this->notifiable->name ?? $this->notifiable->guest_name ?? 'Unknown';
        }

        return $this->recipient_name ?? $this->recipient_email ?? $this->recipient_phone ?? 'Unknown';
    }

    /**
     * Get human-readable notification type.
     */
    public function getTypeDisplayAttribute(): string
    {
        return match ($this->notification_type) {
            self::TYPE_RESERVATION_CREATED => 'Бронь создана',
            self::TYPE_RESERVATION_CONFIRMED => 'Бронь подтверждена',
            self::TYPE_RESERVATION_CANCELLED => 'Бронь отменена',
            self::TYPE_RESERVATION_REMINDER => 'Напоминание о брони',
            self::TYPE_RESERVATION_SEATED => 'Гость посажен',
            self::TYPE_RESERVATION_NO_SHOW => 'Неявка',
            self::TYPE_DEPOSIT_PAID => 'Депозит оплачен',
            self::TYPE_DEPOSIT_REFUNDED => 'Депозит возвращён',
            default => $this->notification_type,
        };
    }

    /**
     * Get human-readable channel name.
     */
    public function getChannelDisplayAttribute(): string
    {
        return match ($this->channel) {
            self::CHANNEL_EMAIL => 'Email',
            self::CHANNEL_TELEGRAM => 'Telegram',
            self::CHANNEL_SMS => 'SMS',
            self::CHANNEL_DATABASE => 'В приложении',
            self::CHANNEL_PUSH => 'Push',
            default => $this->channel,
        };
    }
}
