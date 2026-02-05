<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reservations API Controller
 *
 * CRUD operations for reservations.
 */
class ReservationsController extends BaseApiController
{
    /**
     * List reservations
     *
     * GET /api/v1/reservations
     *
     * Query params:
     * - date: filter by date
     * - status: filter by status
     * - table_id: filter by table
     * - customer_phone: filter by phone
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $query = Reservation::where('restaurant_id', $restaurantId)
            ->with(['table.zone', 'customer'])
            ->orderBy('reserved_at');

        // Date filter
        if ($request->has('date')) {
            $query->whereDate('reserved_at', $request->input('date'));
        }

        // Date range
        if ($request->has('from')) {
            $query->where('reserved_at', '>=', $request->input('from'));
        }
        if ($request->has('to')) {
            $query->where('reserved_at', '<=', $request->input('to'));
        }

        // Status filter
        if ($request->has('status')) {
            $statuses = explode(',', $request->input('status'));
            $query->whereIn('status', $statuses);
        }

        // Table filter
        if ($request->has('table_id')) {
            $query->where('table_id', $request->input('table_id'));
        }

        // Customer phone filter
        if ($request->has('customer_phone')) {
            $query->where('customer_phone', 'like', '%' . $request->input('customer_phone') . '%');
        }

        // Paginate
        $pagination = $this->getPaginationParams($request);
        $reservations = $query->paginate($pagination['per_page'], ['*'], 'page', $pagination['page']);

        $transformed = $reservations->through(function ($reservation) {
            return $this->transformReservation($reservation);
        });

        return $this->paginated($transformed, null);
    }

    /**
     * Get single reservation
     *
     * GET /api/v1/reservations/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $reservation = Reservation::where('restaurant_id', $restaurantId)
            ->with(['table.zone', 'customer'])
            ->find($id);

        if (!$reservation) {
            return $this->notFound('Reservation not found');
        }

        return $this->success($this->transformReservation($reservation));
    }

    /**
     * Create reservation
     *
     * POST /api/v1/reservations
     */
    public function store(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $data = $this->validateRequest($request, [
            'table_id' => 'required|integer|exists:tables,id',
            'reserved_at' => 'required|date|after:now',
            'duration' => 'nullable|integer|min:30|max:480',
            'guests' => 'required|integer|min:1|max:50',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'comment' => 'nullable|string|max:1000',
            'source' => 'nullable|string|max:50',
            'external_id' => 'nullable|string|max:100',
        ]);

        // Verify table belongs to restaurant
        $table = Table::where('restaurant_id', $restaurantId)->find($data['table_id']);

        if (!$table) {
            return $this->notFound('Table not found');
        }

        // Check capacity
        if ($data['guests'] > $table->capacity) {
            return $this->businessError(
                'CAPACITY_EXCEEDED',
                "Table capacity is {$table->capacity} guests"
            );
        }

        // Check availability
        $duration = $data['duration'] ?? 120;
        $reservedAt = \Carbon\Carbon::parse($data['reserved_at']);
        $endsAt = $reservedAt->copy()->addMinutes($duration);

        $conflict = Reservation::where('table_id', $data['table_id'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($q) use ($reservedAt, $endsAt) {
                $q->whereBetween('reserved_at', [$reservedAt, $endsAt])
                    ->orWhere(function ($sq) use ($reservedAt, $endsAt) {
                        $sq->where('reserved_at', '<=', $reservedAt)
                            ->whereRaw('DATE_ADD(reserved_at, INTERVAL COALESCE(duration, 120) MINUTE) > ?', [$reservedAt]);
                    });
            })
            ->exists();

        if ($conflict) {
            return $this->businessError(
                'TIME_SLOT_UNAVAILABLE',
                'This time slot is not available'
            );
        }

        $reservation = Reservation::create([
            'tenant_id' => $this->getTenantId($request),
            'restaurant_id' => $restaurantId,
            'table_id' => $data['table_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_email' => $data['customer_email'] ?? null,
            'reserved_at' => $reservedAt,
            'duration' => $duration,
            'guests' => $data['guests'],
            'comment' => $data['comment'] ?? null,
            'status' => 'pending',
            'source' => $data['source'] ?? 'api',
            'external_id' => $data['external_id'] ?? null,
        ]);

        $reservation->load(['table.zone', 'customer']);

        return $this->created($this->transformReservation($reservation), 'Reservation created successfully');
    }

    /**
     * Update reservation
     *
     * PATCH /api/v1/reservations/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $reservation = Reservation::where('restaurant_id', $restaurantId)->find($id);

        if (!$reservation) {
            return $this->notFound('Reservation not found');
        }

        if (in_array($reservation->status, ['completed', 'cancelled', 'no_show'])) {
            return $this->businessError(
                'RESERVATION_CANNOT_BE_MODIFIED',
                'This reservation cannot be modified'
            );
        }

        $data = $this->validateRequest($request, [
            'reserved_at' => 'nullable|date|after:now',
            'duration' => 'nullable|integer|min:30|max:480',
            'guests' => 'nullable|integer|min:1|max:50',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'comment' => 'nullable|string|max:1000',
            'status' => 'nullable|in:pending,confirmed',
        ]);

        $reservation->update(array_filter($data, fn($v) => $v !== null));
        $reservation->refresh();
        $reservation->load(['table.zone', 'customer']);

        return $this->success($this->transformReservation($reservation), 'Reservation updated successfully');
    }

    /**
     * Cancel reservation
     *
     * POST /api/v1/reservations/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $reservation = Reservation::where('restaurant_id', $restaurantId)->find($id);

        if (!$reservation) {
            return $this->notFound('Reservation not found');
        }

        if (in_array($reservation->status, ['completed', 'cancelled'])) {
            return $this->businessError(
                'RESERVATION_CANNOT_BE_CANCELLED',
                'This reservation cannot be cancelled'
            );
        }

        $data = $this->validateRequest($request, [
            'reason' => 'nullable|string|max:500',
        ]);

        $reservation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $data['reason'] ?? null,
        ]);

        $reservation->refresh();

        return $this->success($this->transformReservation($reservation), 'Reservation cancelled');
    }

    /**
     * Check availability
     *
     * POST /api/v1/reservations/availability
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $data = $this->validateRequest($request, [
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
            'guests' => 'required|integer|min:1|max:50',
            'duration' => 'nullable|integer|min:30|max:480',
        ]);

        $duration = $data['duration'] ?? 120;
        $reservedAt = \Carbon\Carbon::parse("{$data['date']} {$data['time']}");
        $endsAt = $reservedAt->copy()->addMinutes($duration);

        // Find available tables
        $tables = Table::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->where('capacity', '>=', $data['guests'])
            ->with('zone')
            ->get();

        $availableTables = [];

        foreach ($tables as $table) {
            $hasConflict = Reservation::where('table_id', $table->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->where(function ($q) use ($reservedAt, $endsAt) {
                    $q->whereBetween('reserved_at', [$reservedAt, $endsAt])
                        ->orWhere(function ($sq) use ($reservedAt) {
                            $sq->where('reserved_at', '<=', $reservedAt)
                                ->whereRaw('DATE_ADD(reserved_at, INTERVAL COALESCE(duration, 120) MINUTE) > ?', [$reservedAt]);
                        });
                })
                ->exists();

            if (!$hasConflict) {
                $availableTables[] = [
                    'id' => $table->id,
                    'number' => $table->number,
                    'name' => $table->name,
                    'capacity' => $table->capacity,
                    'zone' => $table->zone ? [
                        'id' => $table->zone->id,
                        'name' => $table->zone->name,
                    ] : null,
                ];
            }
        }

        return $this->success([
            'date' => $data['date'],
            'time' => $data['time'],
            'guests' => $data['guests'],
            'duration' => $duration,
            'available_tables' => $availableTables,
            'total_available' => count($availableTables),
        ]);
    }

    /**
     * Transform reservation for API response
     */
    protected function transformReservation(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'external_id' => $reservation->external_id,
            'status' => $reservation->status,
            'reserved_at' => $this->formatDateTime($reservation->reserved_at),
            'duration_minutes' => $reservation->duration ?? 120,
            'ends_at' => $this->formatDateTime(
                $reservation->reserved_at?->copy()->addMinutes($reservation->duration ?? 120)
            ),
            'guests' => $reservation->guests,
            'table' => $reservation->table ? [
                'id' => $reservation->table->id,
                'number' => $reservation->table->number,
                'name' => $reservation->table->name,
                'zone' => $reservation->table->zone ? [
                    'id' => $reservation->table->zone->id,
                    'name' => $reservation->table->zone->name,
                ] : null,
            ] : null,
            'customer' => [
                'id' => $reservation->customer_id,
                'name' => $reservation->customer_name,
                'phone' => $reservation->customer_phone,
                'email' => $reservation->customer_email,
            ],
            'comment' => $reservation->comment,
            'source' => $reservation->source,
            'created_at' => $this->formatDateTime($reservation->created_at),
            'cancelled_at' => $this->formatDateTime($reservation->cancelled_at),
            'cancel_reason' => $reservation->cancel_reason,
        ];
    }
}
