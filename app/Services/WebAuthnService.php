<?php

namespace App\Services;

use App\Models\BiometricCredential;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

/**
 * Simplified WebAuthn service for biometric authentication
 * Uses Web Authentication API (navigator.credentials)
 */
class WebAuthnService
{
    protected string $rpId;
    protected string $rpName;

    public function __construct()
    {
        $this->rpId = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
        $this->rpName = config('app.name', 'MenuLab');
    }

    /**
     * Generate registration options for new credential
     */
    public function generateRegistrationOptions(User $user): array
    {
        $challenge = $this->generateChallenge();

        // Store challenge in cache for verification
        Cache::put(
            $this->getChallengeKey($user->id, 'register'),
            $challenge,
            now()->addMinutes(5)
        );

        // Get existing credentials to exclude
        $existingCredentials = BiometricCredential::forUser($user->id)
            ->pluck('credential_id')
            ->map(fn($id) => [
                'type' => 'public-key',
                'id' => $id,
            ])
            ->toArray();

        return [
            'challenge' => $this->base64UrlEncode($challenge),
            'rp' => [
                'id' => $this->rpId,
                'name' => $this->rpName,
            ],
            'user' => [
                'id' => $this->base64UrlEncode($user->id . '_' . $user->email),
                'name' => $user->email ?? $user->phone ?? "user_{$user->id}",
                'displayName' => $user->name,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],  // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform', // Built-in authenticator (Touch ID, Face ID)
                'userVerification' => 'required',
                'residentKey' => 'preferred',
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'excludeCredentials' => $existingCredentials,
        ];
    }

    /**
     * Verify registration response and create credential
     */
    public function verifyRegistration(User $user, array $response, ?string $name = null): ?BiometricCredential
    {
        // Get stored challenge
        $storedChallenge = Cache::pull($this->getChallengeKey($user->id, 'register'));
        if (!$storedChallenge) {
            throw new \Exception('Challenge expired or not found');
        }

        // Basic validation of response structure
        if (empty($response['id']) || empty($response['rawId']) || empty($response['response'])) {
            throw new \Exception('Invalid response structure');
        }

        $clientDataJSON = $this->base64UrlDecode($response['response']['clientDataJSON'] ?? '');
        $attestationObject = $this->base64UrlDecode($response['response']['attestationObject'] ?? '');

        if (empty($clientDataJSON) || empty($attestationObject)) {
            throw new \Exception('Missing required response data');
        }

        // Parse clientDataJSON
        $clientData = json_decode($clientDataJSON, true);
        if (!$clientData) {
            throw new \Exception('Invalid clientDataJSON');
        }

        // Verify challenge
        $receivedChallenge = $this->base64UrlDecode($clientData['challenge'] ?? '');
        if ($receivedChallenge !== $storedChallenge) {
            throw new \Exception('Challenge mismatch');
        }

        // Verify origin
        $expectedOrigin = rtrim(config('app.url'), '/');
        if (($clientData['origin'] ?? '') !== $expectedOrigin) {
            // Allow localhost variations in development
            if (!app()->isLocal()) {
                throw new \Exception('Origin mismatch');
            }
        }

        // Verify type
        if (($clientData['type'] ?? '') !== 'webauthn.create') {
            throw new \Exception('Invalid type');
        }

        // Parse attestation object (simplified - just extract public key)
        $authData = $this->parseAttestationObject($attestationObject);

        // Determine device type from authenticator info
        $deviceType = $this->detectDeviceType($response);

        // Create credential
        return BiometricCredential::createFromWebAuthn(
            $user->id,
            $response['id'],
            base64_encode($authData['publicKey'] ?? $attestationObject), // Store public key or raw attestation
            $authData['signCount'] ?? 0,
            $authData['aaguid'] ?? null,
            $name,
            $deviceType
        );
    }

    /**
     * Generate authentication options for verification
     */
    public function generateAuthenticationOptions(User $user): array
    {
        $challenge = $this->generateChallenge();

        // Store challenge
        Cache::put(
            $this->getChallengeKey($user->id, 'authenticate'),
            $challenge,
            now()->addMinutes(5)
        );

        // Get user's credentials
        $credentials = BiometricCredential::forUser($user->id)
            ->pluck('credential_id')
            ->map(fn($id) => [
                'type' => 'public-key',
                'id' => $id,
            ])
            ->toArray();

        if (empty($credentials)) {
            throw new \Exception('No biometric credentials registered');
        }

        return [
            'challenge' => $this->base64UrlEncode($challenge),
            'rpId' => $this->rpId,
            'allowCredentials' => $credentials,
            'userVerification' => 'required',
            'timeout' => 60000,
        ];
    }

    /**
     * Verify authentication response
     */
    public function verifyAuthentication(User $user, array $response): BiometricCredential
    {
        // Get stored challenge
        $storedChallenge = Cache::pull($this->getChallengeKey($user->id, 'authenticate'));
        if (!$storedChallenge) {
            throw new \Exception('Challenge expired or not found');
        }

        // Find credential
        $credential = BiometricCredential::where('user_id', $user->id)
            ->where('credential_id', $response['id'] ?? '')
            ->first();

        if (!$credential) {
            throw new \Exception('Credential not found');
        }

        // Parse response
        $clientDataJSON = $this->base64UrlDecode($response['response']['clientDataJSON'] ?? '');
        $authenticatorData = $this->base64UrlDecode($response['response']['authenticatorData'] ?? '');
        $signature = $this->base64UrlDecode($response['response']['signature'] ?? '');

        if (empty($clientDataJSON) || empty($authenticatorData) || empty($signature)) {
            throw new \Exception('Missing required response data');
        }

        // Parse clientDataJSON
        $clientData = json_decode($clientDataJSON, true);
        if (!$clientData) {
            throw new \Exception('Invalid clientDataJSON');
        }

        // Verify challenge
        $receivedChallenge = $this->base64UrlDecode($clientData['challenge'] ?? '');
        if ($receivedChallenge !== $storedChallenge) {
            throw new \Exception('Challenge mismatch');
        }

        // Verify type
        if (($clientData['type'] ?? '') !== 'webauthn.get') {
            throw new \Exception('Invalid type');
        }

        // Parse authenticator data for sign count
        $signCount = $this->parseSignCount($authenticatorData);

        // Verify sign count (replay attack protection)
        if ($signCount !== 0 && $signCount <= $credential->sign_count) {
            throw new \Exception('Possible replay attack detected');
        }

        // Update credential
        $credential->updateSignCount($signCount);

        return $credential;
    }

    /**
     * Check if user has biometric credentials
     */
    public function userHasBiometric(int $userId): bool
    {
        return BiometricCredential::forUser($userId)->exists();
    }

    /**
     * Get user's biometric credentials
     */
    public function getUserCredentials(int $userId): array
    {
        return BiometricCredential::getForUser($userId)
            ->map(fn($c) => $c->info)
            ->toArray();
    }

    /**
     * Delete credential
     */
    public function deleteCredential(int $userId, int $credentialId): bool
    {
        return BiometricCredential::where('id', $credentialId)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    // ==================== HELPERS ====================

    protected function generateChallenge(): string
    {
        return random_bytes(32);
    }

    protected function getChallengeKey(int $userId, string $type): string
    {
        return "webauthn_challenge_{$type}_{$userId}";
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $data): string
    {
        $padding = 4 - strlen($data) % 4;
        if ($padding !== 4) {
            $data .= str_repeat('=', $padding);
        }
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }

    protected function parseAttestationObject(string $attestationObject): array
    {
        // Simplified parsing - in production, use a proper CBOR library
        // For now, just store the raw attestation object as "public key"
        return [
            'publicKey' => $attestationObject,
            'signCount' => 0,
            'aaguid' => null,
        ];
    }

    protected function parseSignCount(string $authenticatorData): int
    {
        if (strlen($authenticatorData) < 37) {
            return 0;
        }
        // Sign count is at bytes 33-36 (big-endian)
        return unpack('N', substr($authenticatorData, 33, 4))[1] ?? 0;
    }

    protected function detectDeviceType(array $response): string
    {
        // Try to detect from authenticator attachment or user agent
        $attachment = $response['authenticatorAttachment'] ?? 'platform';

        if ($attachment === 'cross-platform') {
            return 'security_key';
        }

        // Platform authenticator - could be fingerprint or face
        // Default to fingerprint, frontend can override
        return $response['deviceType'] ?? 'fingerprint';
    }
}
