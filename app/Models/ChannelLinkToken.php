<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Traits\BelongsToRestaurant;

/**
 * Secure token for linking notification channels (Telegram, etc.) to customers.
 *
 * Token format: {random_token}_{hmac_signature}
 * - random_token: 32 chars stored in DB
 * - hmac_signature: computed from token + customer_id + secret
 */
class ChannelLinkToken extends Model
{
    use HasFactory, BelongsToRestaurant;

    // Channel types
    public const CHANNEL_TELEGRAM = 'telegram';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';

    // Token expiration (15 minutes by default)
    public const DEFAULT_EXPIRATION_MINUTES = 15;

    protected $fillable = [
        'restaurant_id',
        'customer_id',
        'channel',
        'token',
        'expires_at',
        'used_at',
        'revoked_at',
        'linked_identifier',
        'created_ip',
        'created_user_agent',
        'used_ip',
        'used_user_agent',
        'context_type',
        'context_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    // ===== RELATIONSHIPS =====

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // ===== SCOPES =====

    public function scopeValid($query)
    {
        return $query
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    // ===== TOKEN GENERATION =====

    /**
     * Generate a new link token for a customer.
     */
    public static function generate(
        Customer $customer,
        string $channel,
        ?Model $context = null,
        ?string $ip = null,
        ?string $userAgent = null,
        int $expirationMinutes = self::DEFAULT_EXPIRATION_MINUTES,
    ): static {
        // Revoke any existing valid tokens for this customer+channel
        static::where('customer_id', $customer->id)
            ->where('channel', $channel)
            ->valid()
            ->update(['revoked_at' => now()]);

        // Generate random token
        $token = Str::random(32);

        return static::create([
            'restaurant_id' => $customer->restaurant_id,
            'customer_id' => $customer->id,
            'channel' => $channel,
            'token' => $token,
            'expires_at' => now()->addMinutes($expirationMinutes),
            'created_ip' => $ip,
            'created_user_agent' => $userAgent ? Str::limit($userAgent, 255) : null,
            'context_type' => $context ? get_class($context) : null,
            'context_id' => $context?->getKey(),
        ]);
    }

    /**
     * Get the full token with HMAC signature for use in deep links.
     */
    public function getSignedToken(): string
    {
        $signature = $this->computeSignature();
        return "{$this->token}_{$signature}";
    }

    /**
     * Get the Telegram deep link URL.
     *
     * Uses restaurant's white-label bot if configured,
     * otherwise falls back to platform bot.
     */
    public function getTelegramDeepLink(): string
    {
        // Try restaurant's white-label bot first
        $restaurant = $this->restaurant;

        if ($restaurant && $restaurant->hasTelegramBot()) {
            $botUsername = $restaurant->telegram_bot_username;
        } else {
            // Fallback to platform bot
            $botUsername = config('services.telegram.bot_username', 'YourBot');
        }

        $signedToken = $this->getSignedToken();

        return "https://t.me/{$botUsername}?start=link_{$signedToken}";
    }

    /**
     * Get the bot username that should be used for this token.
     */
    public function getBotUsername(): string
    {
        $restaurant = $this->restaurant;

        if ($restaurant && $restaurant->hasTelegramBot()) {
            return $restaurant->telegram_bot_username;
        }

        return config('services.telegram.bot_username', 'YourBot');
    }

    /**
     * Check if this token uses restaurant's white-label bot.
     */
    public function usesWhiteLabelBot(): bool
    {
        $restaurant = $this->restaurant;
        return $restaurant && $restaurant->hasTelegramBot();
    }

    /**
     * Compute HMAC signature for the token.
     */
    protected function computeSignature(): string
    {
        $data = "{$this->token}:{$this->customer_id}:{$this->channel}";
        $secret = config('app.key');

        return substr(hash_hmac('sha256', $data, $secret), 0, 16);
    }

    // ===== TOKEN VERIFICATION =====

    /**
     * Find and verify a signed token.
     *
     * @param string $signedToken Format: {token}_{signature}
     * @return static|null
     */
    public static function findBySignedToken(string $signedToken): ?static
    {
        // Parse token and signature
        $parts = explode('_', $signedToken);
        if (count($parts) !== 2) {
            return null;
        }

        [$token, $providedSignature] = $parts;

        // Find the token
        $linkToken = static::where('token', $token)->valid()->first();

        if (!$linkToken) {
            return null;
        }

        // Verify signature
        $expectedSignature = $linkToken->computeSignature();
        if (!hash_equals($expectedSignature, $providedSignature)) {
            return null;
        }

        return $linkToken;
    }

    /**
     * Mark token as used and store the linked identifier.
     */
    public function markUsed(
        string $linkedIdentifier,
        ?string $ip = null,
        ?string $userAgent = null,
    ): void {
        $this->update([
            'used_at' => now(),
            'linked_identifier' => $linkedIdentifier,
            'used_ip' => $ip,
            'used_user_agent' => $userAgent ? Str::limit($userAgent, 255) : null,
        ]);
    }

    /**
     * Revoke the token.
     */
    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    // ===== STATUS CHECKS =====

    /**
     * Check if token is valid (not used, not revoked, not expired).
     */
    public function isValid(): bool
    {
        return $this->used_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    /**
     * Check if token has been used.
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Check if token has been revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Check if token has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Get human-readable status.
     */
    public function getStatusAttribute(): string
    {
        if ($this->isUsed()) {
            return 'used';
        }
        if ($this->isRevoked()) {
            return 'revoked';
        }
        if ($this->isExpired()) {
            return 'expired';
        }
        return 'valid';
    }
}
