<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChannelLinkToken;
use App\Models\Customer;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for linking notification channels (Telegram, etc.) to customers.
 *
 * Provides secure token-based flow:
 * 1. Generate signed token with expiration
 * 2. Customer clicks link / scans QR / etc.
 * 3. External service (bot) verifies token
 * 4. Channel is linked to customer
 *
 * Usage:
 *   $service = app(ChannelLinkingService::class);
 *
 *   // Generate link for customer
 *   $result = $service->generateTelegramLink($customer, $reservation);
 *   // Returns: ['token' => ..., 'deep_link' => 'https://t.me/Bot?start=link_...', 'expires_at' => ...]
 *
 *   // Verify and complete linking (called from bot webhook)
 *   $result = $service->completeTelegramLink($signedToken, $chatId, $username);
 *   // Returns: ['success' => true, 'customer' => Customer]
 */
class ChannelLinkingService
{
    /**
     * Generate Telegram linking token and deep link.
     *
     * @param Customer $customer Customer to link
     * @param Model|null $context Context (e.g., Reservation that triggered this)
     * @param string|null $ip Request IP for audit
     * @param string|null $userAgent Request User-Agent for audit
     * @return array{token_id: int, deep_link: string, expires_at: string, expires_in_seconds: int}
     */
    public function generateTelegramLink(
        Customer $customer,
        ?Model $context = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): array {
        $token = ChannelLinkToken::generate(
            customer: $customer,
            channel: ChannelLinkToken::CHANNEL_TELEGRAM,
            context: $context,
            ip: $ip,
            userAgent: $userAgent,
        );

        Log::info('ChannelLinkingService: Telegram link generated', [
            'customer_id' => $customer->id,
            'token_id' => $token->id,
            'context' => $context ? class_basename($context) . '#' . $context->getKey() : null,
        ]);

        return [
            'token_id' => $token->id,
            'deep_link' => $token->getTelegramDeepLink(),
            'expires_at' => $token->expires_at->toIso8601String(),
            'expires_in_seconds' => (int) now()->diffInSeconds($token->expires_at),
        ];
    }

    /**
     * Complete Telegram channel linking.
     *
     * Called from Telegram bot webhook when user clicks the link.
     *
     * @param string $signedToken Signed token from deep link
     * @param string $chatId Telegram chat ID
     * @param string|null $username Telegram username
     * @return array{success: bool, error?: string, customer?: Customer}
     */
    public function completeTelegramLink(
        string $signedToken,
        string $chatId,
        ?string $username = null,
    ): array {
        // Find and verify token
        $token = ChannelLinkToken::findBySignedToken($signedToken);

        if (!$token) {
            Log::warning('ChannelLinkingService: Invalid or expired token', [
                'signed_token' => substr($signedToken, 0, 20) . '...',
            ]);

            return [
                'success' => false,
                'error' => 'invalid_token',
                'message' => 'Ссылка недействительна или истекла. Запросите новую ссылку.',
            ];
        }

        // Check if already linked to another chat
        $existingCustomer = Customer::where('telegram_chat_id', $chatId)
            ->where('id', '!=', $token->customer_id)
            ->first();

        if ($existingCustomer) {
            Log::warning('ChannelLinkingService: Chat ID already linked to another customer', [
                'chat_id' => $chatId,
                'existing_customer_id' => $existingCustomer->id,
                'requested_customer_id' => $token->customer_id,
            ]);

            return [
                'success' => false,
                'error' => 'already_linked',
                'message' => 'Этот Telegram аккаунт уже привязан к другому номеру.',
            ];
        }

        // Complete linking in transaction
        try {
            DB::transaction(function () use ($token, $chatId, $username) {
                // Mark token as used
                $token->markUsed($chatId);

                // Link Telegram to customer
                $token->customer->linkTelegram($chatId, $username);
            });

            Log::info('ChannelLinkingService: Telegram linked successfully', [
                'customer_id' => $token->customer_id,
                'chat_id' => $chatId,
                'token_id' => $token->id,
            ]);

            // Reload customer
            $token->customer->refresh();

            return [
                'success' => true,
                'customer' => $token->customer,
                'message' => 'Telegram успешно привязан! Теперь вы будете получать уведомления.',
            ];

        } catch (\Throwable $e) {
            Log::error('ChannelLinkingService: Failed to complete linking', [
                'token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'internal_error',
                'message' => 'Произошла ошибка. Попробуйте позже.',
            ];
        }
    }

    /**
     * Unlink Telegram from customer.
     *
     * @param Customer $customer
     * @return array{success: bool, message: string}
     */
    public function unlinkTelegram(Customer $customer): array
    {
        if (!$customer->hasTelegram()) {
            return [
                'success' => false,
                'error' => 'not_linked',
                'message' => 'Telegram не привязан.',
            ];
        }

        $chatId = $customer->telegram_chat_id;
        $customer->unlinkTelegram();

        Log::info('ChannelLinkingService: Telegram unlinked', [
            'customer_id' => $customer->id,
            'chat_id' => $chatId,
        ]);

        return [
            'success' => true,
            'message' => 'Telegram отвязан. Вы больше не будете получать уведомления.',
        ];
    }

    /**
     * Get linking status for a customer.
     */
    public function getChannelStatus(Customer $customer): array
    {
        return [
            'telegram' => [
                'linked' => $customer->hasTelegram(),
                'username' => $customer->telegram_username,
                'linked_at' => $customer->telegram_linked_at?->toIso8601String(),
                'consent' => $customer->telegram_consent,
            ],
            'email' => [
                'linked' => !empty($customer->email),
                'address' => $customer->email,
                'consent' => $customer->email_consent,
            ],
            'sms' => [
                'linked' => !empty($customer->phone),
                'phone' => $customer->phone,
                'consent' => $customer->sms_consent,
            ],
        ];
    }

    /**
     * Update notification preferences for a customer.
     */
    public function updatePreferences(
        Customer $customer,
        array $preferences,
    ): array {
        $validTypes = ['reservation', 'reminder', 'marketing'];
        $validChannels = ['email', 'telegram', 'sms'];

        $sanitized = [];
        foreach ($preferences as $type => $channels) {
            if (!in_array($type, $validTypes)) {
                continue;
            }

            $sanitized[$type] = array_values(array_filter(
                (array) $channels,
                fn($ch) => in_array($ch, $validChannels)
            ));
        }

        $customer->update(['notification_preferences' => $sanitized]);

        Log::info('ChannelLinkingService: Preferences updated', [
            'customer_id' => $customer->id,
            'preferences' => $sanitized,
        ]);

        return [
            'success' => true,
            'preferences' => $sanitized,
        ];
    }

    /**
     * Update consent for a specific channel.
     */
    public function updateConsent(
        Customer $customer,
        string $channel,
        bool $consent,
    ): array {
        $consentField = match ($channel) {
            'telegram' => 'telegram_consent',
            'email' => 'email_consent',
            'sms' => 'sms_consent',
            default => null,
        };

        if (!$consentField) {
            return [
                'success' => false,
                'error' => 'invalid_channel',
            ];
        }

        $customer->update([$consentField => $consent]);

        Log::info('ChannelLinkingService: Consent updated', [
            'customer_id' => $customer->id,
            'channel' => $channel,
            'consent' => $consent,
        ]);

        return [
            'success' => true,
            'channel' => $channel,
            'consent' => $consent,
        ];
    }

    /**
     * Find customer by phone and generate link.
     *
     * Used when guest wants to link Telegram after making a reservation.
     */
    public function generateLinkByPhone(
        int $restaurantId,
        string $phone,
        ?Reservation $reservation = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): array {
        // Normalize phone
        $normalizedPhone = Customer::normalizePhone($phone);

        // Find customer
        $customer = Customer::where('restaurant_id', $restaurantId)
            ->byPhone($normalizedPhone)
            ->first();

        if (!$customer) {
            return [
                'success' => false,
                'error' => 'customer_not_found',
                'message' => 'Клиент с таким номером не найден.',
            ];
        }

        // Check if already linked
        if ($customer->hasTelegram()) {
            return [
                'success' => false,
                'error' => 'already_linked',
                'message' => 'Telegram уже привязан к этому номеру.',
                'username' => $customer->telegram_username,
            ];
        }

        // Generate link
        $link = $this->generateTelegramLink($customer, $reservation, $ip, $userAgent);

        return [
            'success' => true,
            ...$link,
        ];
    }

    /**
     * Cleanup expired tokens.
     */
    public function cleanupExpiredTokens(int $olderThanDays = 7): int
    {
        $deleted = ChannelLinkToken::where('expires_at', '<', now()->subDays($olderThanDays))
            ->delete();

        Log::info('ChannelLinkingService: Cleaned up expired tokens', [
            'deleted' => $deleted,
        ]);

        return $deleted;
    }
}
