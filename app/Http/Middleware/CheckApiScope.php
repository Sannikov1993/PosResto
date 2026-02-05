<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check if the authenticated API client/token has required scopes
 *
 * Usage in routes:
 * Route::get('/dishes', ...)->middleware('api.scope:menu:read');
 * Route::post('/orders', ...)->middleware('api.scope:orders:write');
 * Route::get('/reports', ...)->middleware('api.scope:orders:read,finance:read'); // OR logic
 */
class CheckApiScope
{
    /**
     * Handle an incoming request.
     *
     * @param string ...$scopes Required scopes (OR logic - at least one must match)
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        // Get scopes from request (set by AuthenticateApiClient middleware)
        $grantedScopes = $request->attributes->get('api_scopes', []);

        // If no scopes required, allow
        if (empty($scopes)) {
            return $next($request);
        }

        // Check if any required scope is granted (OR logic)
        foreach ($scopes as $requiredScope) {
            if ($this->hasScope($grantedScopes, $requiredScope)) {
                return $next($request);
            }
        }

        // No matching scope found
        return $this->insufficientScopeResponse($scopes, $grantedScopes);
    }

    /**
     * Check if granted scopes include the required scope
     */
    protected function hasScope(array $grantedScopes, string $requiredScope): bool
    {
        // Wildcard access
        if (in_array('*', $grantedScopes)) {
            return true;
        }

        // Exact match
        if (in_array($requiredScope, $grantedScopes)) {
            return true;
        }

        // Resource-level wildcard (e.g., 'menu:*' matches 'menu:read')
        $parts = explode(':', $requiredScope);
        if (count($parts) === 2) {
            $resource = $parts[0];
            if (in_array("{$resource}:*", $grantedScopes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return insufficient scope response
     */
    protected function insufficientScopeResponse(array $required, array $granted): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INSUFFICIENT_SCOPE',
                'message' => config('api.error_codes.INSUFFICIENT_SCOPE', 'Insufficient permissions'),
                'required_scopes' => $required,
                'granted_scopes' => $granted,
            ],
        ], 403);
    }
}
