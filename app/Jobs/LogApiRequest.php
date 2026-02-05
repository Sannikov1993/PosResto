<?php

namespace App\Jobs;

use App\Models\ApiRequestLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogApiRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * Request data to log
     */
    protected array $data;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->onQueue(config('api.logging.queue', 'api-logs'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Mask sensitive fields in request/response bodies
        $data = $this->maskSensitiveData($this->data);

        // Truncate large bodies
        $maxBodySize = config('api.logging.max_body_size', 10000);

        if (isset($data['request_body']) && strlen($data['request_body']) > $maxBodySize) {
            $data['request_body'] = substr($data['request_body'], 0, $maxBodySize) . '...[truncated]';
        }

        if (isset($data['response_body']) && strlen($data['response_body']) > $maxBodySize) {
            $data['response_body'] = substr($data['response_body'], 0, $maxBodySize) . '...[truncated]';
        }

        ApiRequestLog::create($data);
    }

    /**
     * Mask sensitive data in arrays and JSON strings
     */
    protected function maskSensitiveData(array $data): array
    {
        $maskedFields = config('api.logging.masked_fields', [
            'password', 'pin_code', 'api_secret', 'token', 'card_number', 'cvv',
        ]);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskArray($value, $maskedFields);
            } elseif (is_string($value) && $this->isJson($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $data[$key] = json_encode($this->maskArray($decoded, $maskedFields));
                }
            }
        }

        return $data;
    }

    /**
     * Recursively mask sensitive fields in array
     */
    protected function maskArray(array $array, array $maskedFields): array
    {
        foreach ($array as $key => $value) {
            if (in_array(strtolower($key), array_map('strtolower', $maskedFields))) {
                $array[$key] = '***MASKED***';
            } elseif (is_array($value)) {
                $array[$key] = $this->maskArray($value, $maskedFields);
            }
        }

        return $array;
    }

    /**
     * Check if string is valid JSON
     */
    protected function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(1);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Log failure but don't throw - logging should not break the app
        \Log::warning('Failed to log API request', [
            'request_id' => $this->data['request_id'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
