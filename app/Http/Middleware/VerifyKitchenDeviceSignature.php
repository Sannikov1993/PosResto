<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\KitchenDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * HMAC Request Signing для кухонных устройств
 *
 * Проверяет подпись запроса:
 * X-Signature: HMAC-SHA256(timestamp + method + path + body)
 * X-Timestamp: Unix timestamp (не старше 5 минут)
 *
 * Graceful degradation: если у устройства нет hmac_secret, пропускаем проверку
 */
class VerifyKitchenDeviceSignature
{
    private const MAX_TIMESTAMP_DRIFT_SECONDS = 300; // 5 минут

    public function handle(Request $request, Closure $next): Response
    {
        $deviceId = $request->input('device_id') ?? $request->header('X-Device-ID');

        if (!$deviceId) {
            return $next($request);
        }

        $device = KitchenDevice::withoutGlobalScopes()
            ->where('device_id', $deviceId)
            ->first();

        if (!$device) {
            return $next($request);
        }

        // Graceful degradation: если нет HMAC secret, пропускаем
        if (empty($device->hmac_secret)) {
            return $next($request);
        }

        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');

        if (!$signature || !$timestamp) {
            return response()->json([
                'success' => false,
                'message' => 'Missing signature headers (X-Signature, X-Timestamp)',
            ], 401);
        }

        // Проверяем timestamp (защита от replay attacks)
        $timestampInt = (int) $timestamp;
        if (abs(time() - $timestampInt) > self::MAX_TIMESTAMP_DRIFT_SECONDS) {
            return response()->json([
                'success' => false,
                'message' => 'Request timestamp too old or in future',
            ], 401);
        }

        // Вычисляем ожидаемую подпись
        $method = strtoupper($request->method());
        $path = '/' . ltrim($request->path(), '/');
        $body = $request->getContent();

        $payload = $timestamp . $method . $path . $body;
        $expectedSignature = hash_hmac('sha256', $payload, $device->hmac_secret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('VerifyKitchenDeviceSignature: invalid signature', [
                'device_id' => $deviceId,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid request signature',
            ], 401);
        }

        return $next($request);
    }
}
