<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Table;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tables API Controller
 *
 * Read-only access to tables and zones.
 */
class TablesController extends BaseApiController
{
    /**
     * List tables
     *
     * GET /api/v1/tables
     *
     * Query params:
     * - zone_id: int (filter by zone)
     * - status: string (free, occupied, reserved)
     * - min_capacity: int
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $query = Table::where('restaurant_id', $restaurantId)
            ->with('zone')
            ->orderBy('number');

        // Zone filter
        if ($request->has('zone_id')) {
            $query->where('zone_id', $request->input('zone_id'));
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Capacity filter
        if ($request->has('min_capacity')) {
            $query->where('capacity', '>=', $request->input('min_capacity'));
        }

        // Active only
        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        $tables = $query->get();

        $transformed = $tables->map(function ($table) {
            return [
                'id' => $table->id,
                'number' => $table->number,
                'name' => $table->name,
                'capacity' => $table->capacity,
                'min_capacity' => $table->min_capacity,
                'status' => $table->status,
                'is_active' => $table->is_active,
                'zone' => $table->zone ? [
                    'id' => $table->zone->id,
                    'name' => $table->zone->name,
                ] : null,
                'position' => [
                    'x' => $table->position_x,
                    'y' => $table->position_y,
                ],
                'shape' => $table->shape,
            ];
        });

        return $this->success($transformed);
    }

    /**
     * Get single table
     *
     * GET /api/v1/tables/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $table = Table::where('restaurant_id', $restaurantId)
            ->with(['zone', 'activeOrders.items'])
            ->find($id);

        if (!$table) {
            return $this->notFound('Table not found');
        }

        return $this->success([
            'id' => $table->id,
            'number' => $table->number,
            'name' => $table->name,
            'capacity' => $table->capacity,
            'min_capacity' => $table->min_capacity,
            'status' => $table->status,
            'is_active' => $table->is_active,
            'zone' => $table->zone ? [
                'id' => $table->zone->id,
                'name' => $table->zone->name,
            ] : null,
            'position' => [
                'x' => $table->position_x,
                'y' => $table->position_y,
            ],
            'shape' => $table->shape,
            'active_orders_count' => $table->activeOrders->count(),
        ]);
    }

    /**
     * List zones
     *
     * GET /api/v1/tables/zones
     */
    public function zones(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        if (!$restaurantId) {
            return $this->error('INVALID_REQUEST', 'Restaurant ID required', 400);
        }

        $zones = Zone::where('restaurant_id', $restaurantId)
            ->withCount(['tables' => function ($q) {
                $q->where('is_active', true);
            }])
            ->orderBy('sort_order')
            ->get();

        $transformed = $zones->map(function ($zone) {
            return [
                'id' => $zone->id,
                'name' => $zone->name,
                'description' => $zone->description,
                'color' => $zone->color,
                'is_active' => $zone->is_active,
                'tables_count' => $zone->tables_count,
                'sort_order' => $zone->sort_order,
            ];
        });

        return $this->success($transformed);
    }

    /**
     * Check table availability
     *
     * GET /api/v1/tables/{id}/availability
     *
     * Query params:
     * - date: required, format Y-m-d
     * - time: optional, format H:i
     * - duration: optional, minutes (default 120)
     */
    public function availability(Request $request, int $id): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $table = Table::where('restaurant_id', $restaurantId)->find($id);

        if (!$table) {
            return $this->notFound('Table not found');
        }

        $data = $this->validateRequest($request, [
            'date' => 'required|date_format:Y-m-d',
            'time' => 'nullable|date_format:H:i',
            'duration' => 'nullable|integer|min:30|max:480',
        ]);

        $date = $data['date'];
        $time = $data['time'] ?? null;
        $duration = $data['duration'] ?? 120;

        // Get reservations for this date
        $reservations = $table->reservations()
            ->whereDate('reserved_at', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('reserved_at')
            ->get();

        // Build availability slots
        $slots = [];
        $openHour = 10; // TODO: Get from restaurant settings
        $closeHour = 23;

        for ($hour = $openHour; $hour < $closeHour; $hour++) {
            foreach (['00', '30'] as $minute) {
                $slotTime = sprintf('%02d:%s', $hour, $minute);
                $slotStart = \Carbon\Carbon::parse("{$date} {$slotTime}");
                $slotEnd = $slotStart->copy()->addMinutes($duration);

                $isAvailable = true;

                foreach ($reservations as $reservation) {
                    $resStart = $reservation->reserved_at;
                    $resEnd = $resStart->copy()->addMinutes($reservation->duration ?? 120);

                    // Check overlap
                    if ($slotStart < $resEnd && $slotEnd > $resStart) {
                        $isAvailable = false;
                        break;
                    }
                }

                $slots[] = [
                    'time' => $slotTime,
                    'available' => $isAvailable,
                ];
            }
        }

        // Check specific time if provided
        $specificAvailable = null;
        if ($time) {
            $slot = collect($slots)->firstWhere('time', $time);
            $specificAvailable = $slot ? $slot['available'] : false;
        }

        return $this->success([
            'table_id' => $table->id,
            'table_name' => $table->name,
            'date' => $date,
            'duration_minutes' => $duration,
            'is_available' => $specificAvailable,
            'slots' => $slots,
        ]);
    }
}
