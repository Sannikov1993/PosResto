<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job for sending Email notifications with full logging and retry support.
 */
class SendEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(
        public int $notificationLogId,
        public string $email,
        public string $subject,
        public string $htmlContent,
        public ?string $textContent = null,
        public array $attachments = [],
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $log = NotificationLog::find($this->notificationLogId);

        if (!$log) {
            Log::warning('SendEmailNotificationJob: NotificationLog not found', [
                'notification_log_id' => $this->notificationLogId,
            ]);
            return;
        }

        if ($log->status === NotificationLog::STATUS_DELIVERED) {
            return;
        }

        try {
            Mail::html($this->htmlContent, function ($message) {
                $message->to($this->email)
                    ->subject($this->subject);

                foreach ($this->attachments as $attachment) {
                    if (isset($attachment['path'])) {
                        $message->attach($attachment['path'], [
                            'as' => $attachment['name'] ?? null,
                            'mime' => $attachment['mime'] ?? null,
                        ]);
                    }
                }
            });

            $log->markDelivered([
                'sent_at' => now()->toIso8601String(),
            ]);

            Log::info('SendEmailNotificationJob: Email sent', [
                'notification_log_id' => $this->notificationLogId,
                'email' => $this->email,
            ]);

        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();

            // Don't retry for invalid email addresses
            $permanentErrors = [
                'Invalid address',
                'Recipient rejected',
                'Mailbox not found',
            ];

            $shouldRetry = true;
            foreach ($permanentErrors as $permanentError) {
                if (str_contains($errorMessage, $permanentError)) {
                    $shouldRetry = false;
                    break;
                }
            }

            $log->markFailed("Email error: {$errorMessage}", $shouldRetry);

            Log::error('SendEmailNotificationJob: Failed to send email', [
                'notification_log_id' => $this->notificationLogId,
                'email' => $this->email,
                'error' => $errorMessage,
                'will_retry' => $shouldRetry,
            ]);
        }
    }

    public function tags(): array
    {
        return [
            'notification',
            'email',
            "log:{$this->notificationLogId}",
        ];
    }
}
