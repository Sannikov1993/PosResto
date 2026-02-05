<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Base API Collection class with standardized envelope format and pagination
 *
 * Response format for paginated:
 * {
 *   "success": true,
 *   "data": [ ... ],
 *   "meta": {
 *     "pagination": {
 *       "total": 100,
 *       "per_page": 15,
 *       "current_page": 1,
 *       "last_page": 7,
 *       "from": 1,
 *       "to": 15
 *     }
 *   }
 * }
 */
class ApiCollection extends ResourceCollection
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
     * The resource that this collection collects.
     */
    public $collects = ApiResource::class;

    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($resource) use ($request) {
            return $resource->toArray($request);
        })->all();
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

        // Add pagination meta if resource is paginated
        $meta = $this->additionalMeta;

        if ($this->resource instanceof \Illuminate\Pagination\AbstractPaginator) {
            $meta['pagination'] = $this->getPaginationMeta();
        }

        if (!empty($meta)) {
            $with['meta'] = $meta;
        }

        return $with;
    }

    /**
     * Get pagination metadata
     */
    protected function getPaginationMeta(): array
    {
        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
        $paginator = $this->resource;

        $meta = [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];

        // Add has_more for cursor-style pagination UX
        $meta['has_more'] = $paginator->hasMorePages();

        return $meta;
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

    /**
     * Add filter info to meta
     */
    public function withFilters(array $filters): static
    {
        return $this->withMeta(['filters' => $filters]);
    }

    /**
     * Add sort info to meta
     */
    public function withSort(string $field, string $direction = 'asc'): static
    {
        return $this->withMeta(['sort' => [
            'field' => $field,
            'direction' => $direction,
        ]]);
    }

    /**
     * Add timing info to meta
     */
    public function withTiming(float $durationMs): static
    {
        if (config('api.response.include_execution_time', true)) {
            return $this->withMeta(['timing' => [
                'duration_ms' => round($durationMs, 2),
            ]]);
        }
        return $this;
    }

    /**
     * Create collection with summary counts
     */
    public function withCounts(array $counts): static
    {
        return $this->withMeta(['counts' => $counts]);
    }
}
