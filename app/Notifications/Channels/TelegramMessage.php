<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

/**
 * Fluent builder for Telegram notification messages.
 *
 * Usage:
 *   TelegramMessage::create()
 *       ->greeting('Hello!')
 *       ->line('Your reservation is confirmed.')
 *       ->line('')
 *       ->line('Details:')
 *       ->field('Date', '05.02.2026')
 *       ->field('Time', '19:00 - 21:00')
 *       ->field('Guests', '4')
 *       ->line('')
 *       ->line('See you soon!')
 */
class TelegramMessage
{
    protected array $lines = [];
    protected array $buttons = [];
    protected ?string $parseMode = 'HTML';
    protected bool $disableWebPagePreview = true;
    protected bool $disableNotification = false;

    public static function create(): static
    {
        return new static();
    }

    /**
     * Add a greeting line (bold).
     */
    public function greeting(string $text): static
    {
        $this->lines[] = "<b>{$text}</b>";
        return $this;
    }

    /**
     * Add a line of text.
     */
    public function line(string $text): static
    {
        $this->lines[] = $text;
        return $this;
    }

    /**
     * Add a bold line.
     */
    public function bold(string $text): static
    {
        $this->lines[] = "<b>{$text}</b>";
        return $this;
    }

    /**
     * Add an italic line.
     */
    public function italic(string $text): static
    {
        $this->lines[] = "<i>{$text}</i>";
        return $this;
    }

    /**
     * Add a field with label and value.
     */
    public function field(string $label, ?string $value, ?string $emoji = null): static
    {
        if ($value === null || $value === '') {
            return $this;
        }

        $prefix = $emoji ? "{$emoji} " : '';
        $this->lines[] = "{$prefix}{$label}: <b>{$value}</b>";
        return $this;
    }

    /**
     * Add an emoji field (emoji + value only).
     */
    public function emoji(string $emoji, string $value): static
    {
        $this->lines[] = "{$emoji} {$value}";
        return $this;
    }

    /**
     * Add a success indicator.
     */
    public function success(string $text): static
    {
        $this->lines[] = "✅ {$text}";
        return $this;
    }

    /**
     * Add an error indicator.
     */
    public function error(string $text): static
    {
        $this->lines[] = "❌ {$text}";
        return $this;
    }

    /**
     * Add a warning indicator.
     */
    public function warning(string $text): static
    {
        $this->lines[] = "⚠️ {$text}";
        return $this;
    }

    /**
     * Add an info indicator.
     */
    public function info(string $text): static
    {
        $this->lines[] = "ℹ️ {$text}";
        return $this;
    }

    /**
     * Add a separator line.
     */
    public function separator(): static
    {
        $this->lines[] = '─────────────';
        return $this;
    }

    /**
     * Add an inline button.
     */
    public function button(string $text, string $url): static
    {
        $this->buttons[] = [
            ['text' => $text, 'url' => $url],
        ];
        return $this;
    }

    /**
     * Add a callback button.
     */
    public function callbackButton(string $text, string $callbackData): static
    {
        $this->buttons[] = [
            ['text' => $text, 'callback_data' => $callbackData],
        ];
        return $this;
    }

    /**
     * Add a row of buttons.
     */
    public function buttonRow(array $buttons): static
    {
        $row = [];
        foreach ($buttons as $button) {
            if (isset($button['url'])) {
                $row[] = ['text' => $button['text'], 'url' => $button['url']];
            } elseif (isset($button['callback_data'])) {
                $row[] = ['text' => $button['text'], 'callback_data' => $button['callback_data']];
            }
        }
        if (!empty($row)) {
            $this->buttons[] = $row;
        }
        return $this;
    }

    /**
     * Disable notification sound.
     */
    public function silent(): static
    {
        $this->disableNotification = true;
        return $this;
    }

    /**
     * Enable web page preview for links.
     */
    public function withWebPagePreview(): static
    {
        $this->disableWebPagePreview = false;
        return $this;
    }

    /**
     * Render the message as HTML string.
     */
    public function render(): string
    {
        return implode("\n", $this->lines);
    }

    /**
     * Get additional options for the Telegram API.
     */
    public function getOptions(): array
    {
        $options = [
            'parse_mode' => $this->parseMode,
            'disable_web_page_preview' => $this->disableWebPagePreview,
            'disable_notification' => $this->disableNotification,
        ];

        if (!empty($this->buttons)) {
            $options['reply_markup'] = json_encode([
                'inline_keyboard' => $this->buttons,
            ]);
        }

        return $options;
    }

    /**
     * Convert to string.
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
