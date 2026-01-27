<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\TenantService;

class SetTenantScope
{
    public function __construct(
        protected TenantService $tenantService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Суперадмин видит всё - не устанавливаем ограничения
            if ($user->isSuperAdmin()) {
                return $next($request);
            }

            // Устанавливаем текущий тенант из пользователя
            if ($user->tenant_id) {
                $this->tenantService->setCurrentTenant($user->tenant);
            }
        }

        return $next($request);
    }
}
