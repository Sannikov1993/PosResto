<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    /**
     * Разрешённые домены для CORS
     */
    private array $allowedOrigins = [
        'http://localhost',
        'http://localhost:8000',
        'http://localhost:8001',
        'http://127.0.0.1',
        'http://127.0.0.1:8000',
        'http://127.0.0.1:8001',
        'http://posresto',
        'http://posresto.local',
    ];

    public function handle(Request $request, Closure $next)
    {
        $origin = $request->header('Origin');

        // Проверяем, разрешён ли origin
        $allowedOrigin = in_array($origin, $this->allowedOrigins) ? $origin : $this->allowedOrigins[0];

        if ($request->isMethod('OPTIONS')) {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', $allowedOrigin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Auth-Token')
                ->header('Access-Control-Allow-Credentials', 'true');
        }

        $response = $next($request);

        // Безопасное добавление заголовков для любого типа ответа
        if (method_exists($response, 'header')) {
            return $response
                ->header('Access-Control-Allow-Origin', $allowedOrigin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Auth-Token')
                ->header('Access-Control-Allow-Credentials', 'true');
        }

        // Для StreamedResponse и подобных
        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Auth-Token');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}