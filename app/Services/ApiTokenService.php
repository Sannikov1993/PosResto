<?php

namespace App\Services;

use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Carbon\Carbon;

class ApiTokenService
{
    /**
     * Create access and refresh tokens for a user via API client
     */
    public function createTokensForUser(
        User $user,
        ApiClient $apiClient,
        array $scopes = [],
        ?string $ip = null
    ): array {
        $accessTokenName = "api:{$apiClient->id}:access";
        $refreshTokenName = "api:{$apiClient->id}:refresh";

        // Revoke existing tokens for this client
        $this->revokeClientTokens($user, $apiClient);

        // Determine scopes (intersection of requested and client allowed)
        $allowedScopes = $this->resolveScopes($scopes, $apiClient);

        // Access token expiration
        $accessTtl = config('api.tokens.access_ttl', 60);
        $accessExpiresAt = Carbon::now()->addMinutes($accessTtl);

        // Refresh token expiration
        $refreshTtl = config('api.tokens.refresh_ttl', 30);
        $refreshExpiresAt = Carbon::now()->addDays($refreshTtl);

        // Generate refresh token
        $refreshToken = $this->generateRefreshToken();

        // Create access token
        $accessToken = $user->createToken($accessTokenName, $allowedScopes, $accessExpiresAt);

        // Update token with additional fields
        $accessToken->accessToken->update([
            'token_type' => 'api',
            'scopes' => $allowedScopes,
            'api_client_id' => $apiClient->id,
            'refresh_token' => hash('sha256', $refreshToken),
            'refresh_token_expires_at' => $refreshExpiresAt,
            'created_ip' => $ip,
        ]);

        return [
            'access_token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTtl * 60, // seconds
            'expires_at' => $accessExpiresAt->toIso8601String(),
            'refresh_expires_at' => $refreshExpiresAt->toIso8601String(),
            'scopes' => $allowedScopes,
        ];
    }

    /**
     * Create machine-to-machine token for API client
     */
    public function createClientToken(ApiClient $apiClient, ?string $ip = null): array
    {
        // Use tenant owner or create system token
        $user = $apiClient->tenant->users()->where('is_tenant_owner', true)->first();

        if (!$user) {
            throw new \RuntimeException('Tenant has no owner user');
        }

        $tokenName = "client:{$apiClient->id}";
        $scopes = $apiClient->scopes ?? [];

        // Longer expiration for client credentials
        $ttl = config('api.tokens.access_ttl', 60) * 24; // 24 hours
        $expiresAt = Carbon::now()->addMinutes($ttl);

        // Revoke existing client tokens
        PersonalAccessToken::where('name', $tokenName)
            ->where('tokenable_id', $user->id)
            ->where('tokenable_type', get_class($user))
            ->delete();

        $token = $user->createToken($tokenName, $scopes, $expiresAt);

        $token->accessToken->update([
            'token_type' => 'api',
            'scopes' => $scopes,
            'api_client_id' => $apiClient->id,
            'created_ip' => $ip,
        ]);

        return [
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $ttl * 60,
            'expires_at' => $expiresAt->toIso8601String(),
            'scopes' => $scopes,
        ];
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshToken(string $refreshToken, ?string $ip = null): ?array
    {
        $hashedRefreshToken = hash('sha256', $refreshToken);

        $token = PersonalAccessToken::where('refresh_token', $hashedRefreshToken)
            ->where('token_type', 'api')
            ->first();

        if (!$token) {
            return null;
        }

        // Check refresh token expiration
        if ($token->refresh_token_expires_at && $token->refresh_token_expires_at->isPast()) {
            $token->delete();
            return null;
        }

        $user = $token->tokenable;
        $apiClient = ApiClient::find($token->api_client_id);

        if (!$user || !$apiClient || !$apiClient->is_active) {
            $token->delete();
            return null;
        }

        // Delete old token
        $token->delete();

        // Create new tokens
        return $this->createTokensForUser(
            $user,
            $apiClient,
            $token->scopes ?? [],
            $ip
        );
    }

    /**
     * Revoke token
     */
    public function revokeToken(string $token): bool
    {
        $accessToken = PersonalAccessToken::findToken($token);

        if ($accessToken) {
            $accessToken->delete();
            return true;
        }

        return false;
    }

    /**
     * Revoke all tokens for user from specific API client
     */
    public function revokeClientTokens(User $user, ApiClient $apiClient): int
    {
        return PersonalAccessToken::where('tokenable_id', $user->id)
            ->where('tokenable_type', get_class($user))
            ->where('api_client_id', $apiClient->id)
            ->delete();
    }

    /**
     * Revoke all tokens for API client
     */
    public function revokeAllClientTokens(ApiClient $apiClient): int
    {
        return PersonalAccessToken::where('api_client_id', $apiClient->id)->delete();
    }

    /**
     * Get token info
     */
    public function getTokenInfo(string $token): ?array
    {
        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return null;
        }

        $user = $accessToken->tokenable;
        $apiClient = $accessToken->api_client_id
            ? ApiClient::find($accessToken->api_client_id)
            : null;

        return [
            'token_type' => $accessToken->token_type ?? 'internal',
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'api_client_id' => $apiClient?->id,
            'api_client_name' => $apiClient?->name,
            'scopes' => $accessToken->scopes ?? $accessToken->abilities ?? [],
            'created_at' => $accessToken->created_at?->toIso8601String(),
            'last_used_at' => $accessToken->last_used_at?->toIso8601String(),
            'expires_at' => $accessToken->expires_at?->toIso8601String(),
            'is_expired' => $accessToken->expires_at && $accessToken->expires_at->isPast(),
        ];
    }

    /**
     * Validate token and get associated data
     */
    public function validateToken(string $token): ?array
    {
        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken) {
            return null;
        }

        // Check expiration
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return null;
        }

        $user = $accessToken->tokenable;

        if (!$user || !$user->is_active) {
            return null;
        }

        // Update last_used_at
        $accessToken->forceFill(['last_used_at' => now()])->save();

        $apiClient = $accessToken->api_client_id
            ? ApiClient::find($accessToken->api_client_id)
            : null;

        return [
            'user' => $user,
            'api_client' => $apiClient,
            'token' => $accessToken,
            'scopes' => $accessToken->scopes ?? $accessToken->abilities ?? [],
            'token_type' => $accessToken->token_type ?? 'internal',
        ];
    }

    /**
     * Generate refresh token
     */
    protected function generateRefreshToken(): string
    {
        $prefix = config('api.tokens.prefix', 'mlat_');
        return $prefix . 'rt_' . Str::random(48);
    }

    /**
     * Resolve scopes (intersection of requested and client allowed)
     */
    protected function resolveScopes(array $requestedScopes, ApiClient $apiClient): array
    {
        $clientScopes = $apiClient->scopes ?? [];

        // If client has wildcard, allow all requested scopes
        if (in_array('*', $clientScopes)) {
            return $requestedScopes;
        }

        // If no scopes requested, use all client scopes
        if (empty($requestedScopes)) {
            return $clientScopes;
        }

        // Return intersection
        return array_values(array_intersect($requestedScopes, $clientScopes));
    }

    /**
     * Check if token has scope
     */
    public function tokenHasScope(PersonalAccessToken $token, string $scope): bool
    {
        $scopes = $token->scopes ?? $token->abilities ?? [];

        // Wildcard access
        if (in_array('*', $scopes)) {
            return true;
        }

        // Exact match
        if (in_array($scope, $scopes)) {
            return true;
        }

        // Resource-level wildcard
        $parts = explode(':', $scope);
        if (count($parts) === 2) {
            $resource = $parts[0];
            if (in_array("{$resource}:*", $scopes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cleanup expired tokens
     */
    public function cleanupExpiredTokens(): int
    {
        return PersonalAccessToken::where('token_type', 'api')
            ->where(function ($query) {
                $query->where('expires_at', '<', now())
                    ->orWhere(function ($q) {
                        $q->whereNotNull('refresh_token_expires_at')
                            ->where('refresh_token_expires_at', '<', now());
                    });
            })
            ->delete();
    }
}
