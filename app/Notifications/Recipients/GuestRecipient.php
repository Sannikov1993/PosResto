<?php

declare(strict_types=1);

namespace App\Notifications\Recipients;

use App\Models\Restaurant;
use Illuminate\Notifications\Notifiable;

/**
 * Temporary recipient for guests who are not registered customers.
 *
 * Used when sending notifications to reservation guests who don't have
 * a Customer record (e.g., walk-ins or first-time visitors).
 *
 * Usage:
 *   $guest = GuestRecipient::fromReservation($reservation);
 *   $guest->notify(new ReservationConfirmedNotification($reservation));
 */
class GuestRecipient
{
    use Notifiable;

    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?string $telegramChatId = null,
        public readonly array $preferences = [],
        public readonly ?int $restaurant_id = null,
        public readonly ?Restaurant $restaurant = null,
    ) {}

    /**
     * Create from a reservation model.
     */
    public static function fromReservation($reservation): static
    {
        return new static(
            name: $reservation->guest_name,
            phone: $reservation->guest_phone,
            email: $reservation->guest_email,
            telegramChatId: null, // Guests don't have telegram by default
            preferences: [],
            restaurant_id: $reservation->restaurant_id,
            restaurant: $reservation->restaurant,
        );
    }

    /**
     * Create with specific channels.
     */
    public static function make(
        ?string $name = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $telegramChatId = null,
        ?int $restaurantId = null,
        ?Restaurant $restaurant = null,
    ): static {
        return new static(
            name: $name,
            phone: $phone,
            email: $email,
            telegramChatId: $telegramChatId,
            restaurant_id: $restaurantId,
            restaurant: $restaurant,
        );
    }

    /**
     * Route notifications for the mail channel.
     */
    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }

    /**
     * Route notifications for the Telegram channel.
     */
    public function routeNotificationForTelegram(): ?string
    {
        return $this->telegramChatId;
    }

    /**
     * Route notifications for SMS channel.
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }

    /**
     * Get notification preferences for a specific type.
     *
     * @param string $type Notification type (e.g., 'reservation', 'marketing')
     * @return array List of preferred channels
     */
    public function getNotificationPreferences(string $type): array
    {
        if (isset($this->preferences[$type])) {
            return $this->preferences[$type];
        }

        // Default: use whatever channels are available
        return $this->getAvailableChannels();
    }

    /**
     * Get all available notification channels for this guest.
     */
    public function getAvailableChannels(): array
    {
        $channels = [];

        if ($this->email) {
            $channels[] = 'mail';
        }

        if ($this->telegramChatId) {
            $channels[] = 'telegram';
        }

        if ($this->phone) {
            $channels[] = 'sms';
        }

        return $channels;
    }

    /**
     * Check if any notification channel is available.
     */
    public function canBeNotified(): bool
    {
        return !empty($this->getAvailableChannels());
    }

    /**
     * Get display name for logging.
     */
    public function getDisplayName(): string
    {
        return $this->name ?? $this->email ?? $this->phone ?? 'Unknown Guest';
    }
}
