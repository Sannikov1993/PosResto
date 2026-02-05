<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API Authentication Controller
 *
 * Handles token refresh and revocation for public API.
 */
class AuthController extends BaseApiController
{
    protected ApiTokenService $tokenService;

    public function __construct(ApiTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Refresh access token using refresh token
     *
     * POST /api/v1/auth/refresh
     * Body: { "refresh_token": "..." }
     */
    public function refresh(Request $request): JsonResponse
    {
        $data = $this->validateRequest($request, [
            'refresh_token' => 'required|string',
        ]);

        $result = $this->tokenService->refreshToken(
            $data['refresh_token'],
            $request->ip()
        );

        if (!$result) {
            return $this->error(
                'TOKEN_INVALID',
                'Invalid or expired refresh token',
                401
            );
        }

        return $this->success($result, 'Token refreshed successfully');
    }

    /**
     * Revoke current token
     *
     * POST /api/v1/auth/revoke
     */
    public function revoke(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (!$token) {
            return $this->error('TOKEN_INVALID', 'No token provided', 401);
        }

        $revoked = $this->tokenService->revokeToken($token);

        if (!$revoked) {
            return $this->error('TOKEN_INVALID', 'Token not found', 404);
        }

        return $this->success(null, 'Token revoked successfully');
    }

    /**
     * Get current token info
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (!$token) {
            // API Key auth - return client info
            $apiClient = $this->getApiClient($request);

            if (!$apiClient) {
                return $this->unauthorized();
            }

            return $this->success([
                'auth_type' => 'api_key',
                'client' => [
                    'id' => $apiClient->id,
                    'name' => $apiClient->name,
                    'type' => $apiClient->type,
                    'rate_plan' => $apiClient->rate_plan,
                ],
                'scopes' => $this->getScopes($request),
                'tenant_id' => $this->getTenantId($request),
                'restaurant_id' => $this->getRestaurantId($request),
            ]);
        }

        // Bearer token auth
        $tokenInfo = $this->tokenService->getTokenInfo($token);

        if (!$tokenInfo) {
            return $this->unauthorized();
        }

        return $this->success([
            'auth_type' => 'bearer',
            'user' => [
                'id' => $tokenInfo['user_id'],
                'name' => $tokenInfo['user_name'],
            ],
            'client' => $tokenInfo['api_client_id'] ? [
                'id' => $tokenInfo['api_client_id'],
                'name' => $tokenInfo['api_client_name'],
            ] : null,
            'scopes' => $tokenInfo['scopes'],
            'token' => [
                'created_at' => $tokenInfo['created_at'],
                'last_used_at' => $tokenInfo['last_used_at'],
                'expires_at' => $tokenInfo['expires_at'],
                'is_expired' => $tokenInfo['is_expired'],
            ],
            'tenant_id' => $this->getTenantId($request),
            'restaurant_id' => $this->getRestaurantId($request),
        ]);
    }
}
