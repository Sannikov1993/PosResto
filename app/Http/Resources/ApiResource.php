<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base API Resource class with standardized envelope format
 *
 * Response format:
 * {
 *   "success": true,
 *   "data": { ... },
 *   "meta": { ... }
 * }
 */
class ApiResource extends JsonResource
{
    /**
     * Additional meta data
     */
    protected array $additionalMeta = [];

    /**
     * Custom message for response
     */
    protected ?string $message = null;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        // Override in child classes
        return parent::toArray($request);
    }

    /**
     * Customize the outgoing response.
     */
    public function withResponse(Request $request, \Illuminate\Http\JsonResponse $response): void
    {
        // Add X-Request-ID header if available
        $requestId = $request->attributes->get('api_request_id');
        if ($requestId && config('api.response.include_request_id', true)) {
            $response->header('X-Request-ID', $requestId);
        }
    }

    /**
     * Get the resource's response envelope
     */
    public function with(Request $request): array
    {
        $with = [
            'success' => true,
        ];

        if ($this->message) {
            $with['message'] = $this->message;
        }

        if (!empty($this->additionalMeta)) {
            $with['meta'] = $this->additionalMeta;
        }

        return $with;
    }

    /**
     * Add additional meta data
     */
    public function withMeta(array $meta): static
    {
        $this->additionalMeta = array_merge($this->additionalMeta, $meta);
        return $this;
    }

    /**
     * Set response message
     */
    public function withMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    // ===== COMMON FORMATTERS =====

    /**
     * Format datetime according to API config
     */
    protected function formatDateTime(?\DateTimeInterface $dateTime): ?string
    {
        if (!$dateTime) {
            return null;
        }

        return $dateTime->format(config('api.response.datetime_format', 'Y-m-d\TH:i:s.uP'));
    }

    /**
     * Format date according to API config
     */
    protected function formatDate(?\DateTimeInterface $date): ?string
    {
        if (!$date) {
            return null;
        }

        return $date->format(config('api.response.date_format', 'Y-m-d'));
    }

    /**
     * Format time according to API config
     */
    protected function formatTime(?\DateTimeInterface $time): ?string
    {
        if (!$time) {
            return null;
        }

        return $time->format(config('api.response.time_format', 'H:i:s'));
    }

    /**
     * Format money value (returns cents or smallest currency unit)
     */
    protected function formatMoney(float|int|null $value): ?int
    {
        if ($value === null) {
            return null;
        }

        // Convert to cents (smallest unit)
        return (int) round($value * 100);
    }

    /**
     * Format money value as decimal string
     */
    protected function formatMoneyDecimal(float|int|null $value, int $decimals = 2): ?string
    {
        if ($value === null) {
            return null;
        }

        return number_format((float) $value, $decimals, '.', '');
    }

    /**
     * Conditionally include a value
     */
    protected function includeWhen(bool $condition, mixed $value): mixed
    {
        return $this->when($condition, $value);
    }

    /**
     * Include relationship only when loaded
     */
    protected function includeRelation(string $relation, ?string $resourceClass = null): mixed
    {
        return $this->whenLoaded($relation, function () use ($relation, $resourceClass) {
            $related = $this->resource->$relation;

            if ($resourceClass && class_exists($resourceClass)) {
                if ($related instanceof \Illuminate\Database\Eloquent\Collection) {
                    return $resourceClass::collection($related);
                }
                return new $resourceClass($related);
            }

            return $related;
        });
    }

    /**
     * Get image URL with optional transformation
     */
    protected function imageUrl(?string $path, ?string $transformation = null): ?string
    {
        if (!$path) {
            return null;
        }

        // If already a full URL, return as is
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        // Build storage URL
        return asset('storage/' . $path);
    }
}
