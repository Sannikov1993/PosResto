<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MeasureResponseTime
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = (microtime(true) - $start) * 1000; // ms
        $response->headers->set('X-Response-Time', round($duration, 2) . 'ms');

        if ($duration > 500) {
            Log::channel('single')->warning('Slow response', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'duration_ms' => round($duration, 2),
                'status' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}
