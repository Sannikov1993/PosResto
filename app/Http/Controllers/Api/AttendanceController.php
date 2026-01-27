<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\AttendanceQrCode;
use App\Models\Restaurant;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService,
    ) {}

    /**
     * Получить статус посещаемости текущего пользователя
     * GET /api/cabinet/attendance/status
     */
    public function status(Request $request): JsonResponse
    {
        $user = Auth::user();
        $restaurantId = $user->restaurant_id;

        $status = $this->attendanceService->getUserAttendanceStatus($user, $restaurantId);

        $restaurant = Restaurant::find($restaurantId);

        return response()->json([
            'success' => true,
            'data' => [
                ...$status,
                'attendance_mode' => $restaurant->attendance_mode ?? 'disabled',
                'qr_enabled' => in_array($restaurant->attendance_mode, ['qr_only', 'device_or_qr']),
                'device_enabled' => in_array($restaurant->attendance_mode, ['device_only', 'device_or_qr']),
            ],
        ]);
    }

    /**
     * Clock in через QR-код
     * POST /api/cabinet/attendance/qr/clock-in
     */
    public function clockInQr(Request $request): JsonResponse
    {
        $request->validate([
            'qr_token' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $user = Auth::user();

        $result = $this->attendanceService->processQrEvent(
            user: $user,
            qrToken: $request->input('qr_token'),
            eventType: AttendanceEvent::TYPE_CLOCK_IN,
            latitude: $request->input('latitude'),
            longitude: $request->input('longitude'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Clock out через QR-код
     * POST /api/cabinet/attendance/qr/clock-out
     */
    public function clockOutQr(Request $request): JsonResponse
    {
        $request->validate([
            'qr_token' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $user = Auth::user();

        $result = $this->attendanceService->processQrEvent(
            user: $user,
            qrToken: $request->input('qr_token'),
            eventType: AttendanceEvent::TYPE_CLOCK_OUT,
            latitude: $request->input('latitude'),
            longitude: $request->input('longitude'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Получить QR-код для сканирования (для дисплея в ресторане)
     * GET /api/attendance/qr/{restaurantId}
     */
    public function getQrCode(int $restaurantId): JsonResponse
    {
        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return response()->json([
                'success' => false,
                'error' => 'restaurant_not_found',
            ], 404);
        }

        if (!in_array($restaurant->attendance_mode, ['qr_only', 'device_or_qr'])) {
            return response()->json([
                'success' => false,
                'error' => 'qr_disabled',
                'message' => 'QR-код отключён для этого ресторана',
            ], 400);
        }

        $qrCode = AttendanceQrCode::getOrCreateForRestaurant($restaurantId);
        $token = $qrCode->generateToken();

        // Формируем URL для сканирования
        $scanUrl = url("/cabinet/attendance/scan?token={$token}");

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'scan_url' => $scanUrl,
                'type' => $qrCode->type,
                'expires_at' => $qrCode->expires_at?->toIso8601String(),
                'refresh_in_seconds' => $qrCode->type === 'dynamic'
                    ? max(0, $qrCode->expires_at?->diffInSeconds(now()) ?? 0)
                    : null,
                'restaurant' => [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                ],
            ],
        ]);
    }

    /**
     * Обновить QR-код (для динамических кодов)
     * POST /api/attendance/qr/{restaurantId}/refresh
     */
    public function refreshQrCode(int $restaurantId): JsonResponse
    {
        $qrCode = AttendanceQrCode::forRestaurant($restaurantId)->active()->first();

        if (!$qrCode) {
            return response()->json([
                'success' => false,
                'error' => 'qr_not_found',
            ], 404);
        }

        $qrCode->refresh();
        $token = $qrCode->generateToken();

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'expires_at' => $qrCode->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Получить историю событий пользователя
     * GET /api/cabinet/attendance/history
     */
    public function history(Request $request): JsonResponse
    {
        $user = Auth::user();

        $events = AttendanceEvent::forUser($user->id)
            ->with(['device:id,name,type'])
            ->orderBy('event_time', 'desc')
            ->limit($request->input('limit', 50))
            ->get()
            ->map(fn ($event) => [
                'id' => $event->id,
                'type' => $event->event_type,
                'source' => $event->source,
                'source_label' => $event->source_label,
                'method' => $event->verification_method,
                'method_label' => $event->method_label,
                'device' => $event->device?->name,
                'event_time' => $event->event_time->toIso8601String(),
                'confidence' => $event->confidence,
            ]);

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Валидация QR-кода (проверка без clock in/out)
     * POST /api/cabinet/attendance/qr/validate
     */
    public function validateQr(Request $request): JsonResponse
    {
        $request->validate([
            'qr_token' => 'required|string',
        ]);

        $qrCode = AttendanceQrCode::findByToken($request->input('qr_token'));

        if (!$qrCode) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'error' => 'invalid_qr',
            ]);
        }

        $isValid = $qrCode->validateToken($request->input('qr_token'));

        return response()->json([
            'success' => true,
            'valid' => $isValid,
            'restaurant' => $isValid ? [
                'id' => $qrCode->restaurant_id,
                'name' => $qrCode->restaurant->name,
            ] : null,
            'require_geolocation' => $qrCode->require_geolocation,
        ]);
    }
}
