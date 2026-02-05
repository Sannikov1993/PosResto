<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Services\ApiTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate API requests using either:
 * 1. API Key + Secret (machine-to-machine, X-API-Key / X-API-Secret headers)
 * 2. Bearer Token (user context, Authorization header)
 */
class AuthenticateApiClient
{
    protected ApiTokenService $tokenService;

    public function __construct(ApiTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Try API Key authentication first
        $apiKey = $request->header('X-API-Key');
        $apiSecret = $request->header('X-API-Secret');

        if ($apiKey && $apiSecret) {
            return $this->authenticateWithApiKey($request, $next, $apiKey, $apiSecret);
        }

        // Try Bearer Token authentication
        $bearerToken = $request->bearerToken();

        if ($bearerToken) {
            return $this->authenticateWithBearerToken($request, $next, $bearerToken);
        }

        // No authentication provided
        return $this->unauthorizedResponse('Authentication required. Provide X-API-Key/X-API-Secret headers or Bearer token.');
    }

    /**
     * Authenticate using API Key and Secret
     */
    protected function authenticateWithApiKey(
        Request $request,
        Closure $next,
        string $apiKey,
        string $apiSecret
    ): Response {
        // Find API client by key
        $apiClient = ApiClient::findByApiKey($apiKey);

        if (!$apiClient) {
            return $this->unauthorizedResponse('Invalid API key', 'INVALID_CREDENTIALS');
        }

        // Verify secret
        if (!$apiClient->verifySecret($apiSecret)) {
            return $this->unauthorizedResponse('Invalid API secret', 'INVALID_CREDENTIALS');
        }

        // Check if client is active
        if (!$apiClient->is_active) {
            return $this->unauthorizedResponse('API client is deactivated', 'TOKEN_INVALID');
        }

        // Check IP whitelist
        $clientIp = $request->ip();
        if (!$apiClient->isIpAllowed($clientIp)) {
            return $this->forbiddenResponse('IP address not allowed');
        }

        // Store API client in request for later use
        $request->attributes->set('api_client', $apiClient);
        $request->attributes->set('api_auth_type', 'api_key');
        $request->attributes->set('api_scopes', $apiClient->scopes ?? []);

        // Set tenant and restaurant context
        $request->attributes->set('tenant_id', $apiClient->tenant_id);
        if ($apiClient->restaurant_id) {
            $request->attributes->set('restaurant_id', $apiClient->restaurant_id);
        }

        // Record usage
        $apiClient->update(['last_used_at' => now()]);

        return $next($request);
    }

    /**
     * Authenticate using Bearer Token
     */
    protected function authenticateWithBearerToken(
        Request $request,
        Closure $next,
        string $token
    ): Response {
        // Validate token
        $tokenData = $this->tokenService->validateToken($token);

        if (!$tokenData) {
            return $this->unauthorizedResponse('Invalid or expired token', 'TOKEN_INVALID');
        }

        $user = $tokenData['user'];
        $apiClient = $tokenData['api_client'];
        $accessToken = $tokenData['token'];

        // Check if this is a public API token
        if ($tokenData['token_type'] !== 'api') {
            return $this->unauthorizedResponse('Token not valid for public API', 'TOKEN_INVALID');
        }

        // Check if API client is still active
        if ($apiClient && !$apiClient->is_active) {
            return $this->unauthorizedResponse('API client is deactivated', 'TOKEN_INVALID');
        }

        // Check token expiration
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return $this->unauthorizedResponse('Token has expired', 'TOKEN_EXPIRED');
        }

        // Set authenticated user
        Auth::setUser($user);

        // Store API info in request
        $request->attributes->set('api_client', $apiClient);
        $request->attributes->set('api_auth_type', 'bearer');
        $request->attributes->set('api_scopes', $tokenData['scopes'] ?? []);
        $request->attributes->set('api_token', $accessToken);

        // Set tenant and restaurant context
        $request->attributes->set('tenant_id', $user->tenant_id);
        if ($apiClient?->restaurant_id) {
            $request->attributes->set('restaurant_id', $apiClient->restaurant_id);
        } elseif ($user->restaurant_id) {
            $request->attributes->set('restaurant_id', $user->restaurant_id);
        }

        // Update API client usage
        if ($apiClient) {
            $apiClient->update(['last_used_at' => now()]);
        }

        return $next($request);
    }

    /**
     * Return unauthorized response
     */
    protected function unauthorizedResponse(string $message, string $code = 'UNAUTHORIZED'): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], 401);
    }

    /**
     * Return forbidden response
     */
    protected function forbiddenResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => $message,
            ],
        ], 403);
    }
}
