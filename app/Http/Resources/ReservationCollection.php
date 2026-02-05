<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Collection resource for reservations.
 *
 * Provides:
 * - Pagination metadata
 * - Aggregated statistics
 * - Consistent collection structure
 *
 * Usage:
 *   return new ReservationCollection($reservations);
 *   return ReservationCollection::make($reservations)->withStats();
 */
class ReservationCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = ReservationResource::class;

    /**
     * Include statistics in response.
     */
    private bool $includeStats = false;

    /**
     * Include statistics in response.
     */
    public function withStats(): static
    {
        $this->includeStats = true;
        return $this;
    }

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->toArray();
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        $meta = [
            'version' => 'v1',
            'count' => $this->collection->count(),
        ];

        if ($this->includeStats) {
            $meta['stats'] = $this->calculateStats();
        }

        return [
            'meta' => $meta,
        ];
    }

    /**
     * Calculate statistics for the collection.
     */
    private function calculateStats(): array
    {
        $collection = $this->collection;

        $byStatus = $collection->groupBy(fn($item) => $item->status);

        return [
            'total' => $collection->count(),
            'by_status' => [
                'pending' => $byStatus->get('pending')?->count() ?? 0,
                'confirmed' => $byStatus->get('confirmed')?->count() ?? 0,
                'seated' => $byStatus->get('seated')?->count() ?? 0,
                'completed' => $byStatus->get('completed')?->count() ?? 0,
                'cancelled' => $byStatus->get('cancelled')?->count() ?? 0,
                'no_show' => $byStatus->get('no_show')?->count() ?? 0,
            ],
            'total_guests' => $collection
                ->whereIn('status', ['pending', 'confirmed', 'seated'])
                ->sum('guests_count'),
            'with_deposit' => $collection
                ->where('deposit', '>', 0)
                ->count(),
            'deposit_paid' => $collection
                ->where('deposit_status', 'paid')
                ->count(),
        ];
    }
}
