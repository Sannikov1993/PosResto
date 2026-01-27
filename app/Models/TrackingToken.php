<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Модель токена для публичного отслеживания заказа
 *
 * Позволяет клиентам отслеживать заказ без авторизации,
 * используя уникальный токен.
 */
class TrackingToken extends Model
{
    protected $fillable = [
        'order_id',
        'token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    // ==================== Связи ====================

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ==================== Методы ====================

    /**
     * Сгенерировать или обновить токен для заказа
     *
     * @param Order $order
     * @param int $expiresInHours Время жизни токена в часах
     * @return self
     */
    public static function generateForOrder(Order $order, int $expiresInHours = 24): self
    {
        return self::updateOrCreate(
            ['order_id' => $order->id],
            [
                'token' => Str::random(64),
                'expires_at' => now()->addHours($expiresInHours),
            ]
        );
    }

    /**
     * Найти токен по значению
     *
     * @param string $token
     * @return self|null
     */
    public static function findByToken(string $token): ?self
    {
        return self::where('token', $token)->first();
    }

    /**
     * Проверить, действителен ли токен
     *
     * @return bool
     */
    public function isValid(): bool
    {
        // Токен без срока действия всегда валиден
        if (!$this->expires_at) {
            return true;
        }

        return $this->expires_at->isFuture();
    }

    /**
     * Проверить, истёк ли токен
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return !$this->isValid();
    }

    /**
     * Продлить срок действия токена
     *
     * @param int $hours
     * @return self
     */
    public function extend(int $hours = 24): self
    {
        $this->update([
            'expires_at' => now()->addHours($hours),
        ]);

        return $this;
    }

    /**
     * Отозвать токен (установить срок в прошлое)
     *
     * @return self
     */
    public function revoke(): self
    {
        $this->update([
            'expires_at' => now()->subMinute(),
        ]);

        return $this;
    }

    /**
     * Очистка просроченных токенов
     *
     * @return int Количество удалённых записей
     */
    public static function cleanup(): int
    {
        return self::where('expires_at', '<', now()->subDay())
            ->delete();
    }

    /**
     * Получить URL для отслеживания
     *
     * @return string
     */
    public function getTrackingUrl(): string
    {
        return url("/track/{$this->order->order_number}/live");
    }

    /**
     * Получить полный URL с токеном (для SMS/email)
     *
     * @return string
     */
    public function getFullTrackingUrl(): string
    {
        return url("/track/{$this->order->order_number}/live?token={$this->token}");
    }
}
