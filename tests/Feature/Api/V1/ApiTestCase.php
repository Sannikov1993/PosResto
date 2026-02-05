<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiClient;
use App\Models\Restaurant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Restaurant $restaurant;
    protected ApiClient $apiClient;
    protected array $headers;
    protected string $apiSecret;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
            'email' => 'test@example.com',
            'phone' => '+79991234567',
            'plan' => 'business',
            'is_active' => true,
        ]);

        // Create restaurant
        $this->restaurant = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create API client
        $keyPair = ApiClient::generateKeyPair();
        $this->apiSecret = $keyPair['api_secret'];

        $this->apiClient = ApiClient::create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test API Client',
            'api_key' => $keyPair['api_key'],
            'api_key_prefix' => $keyPair['api_key_prefix'],
            'api_secret' => $keyPair['api_secret'], // Plain text - verifySecret uses hash_equals
            'scopes' => ['*'],
            'rate_plan' => 'enterprise',
            'is_active' => true,
        ]);

        // Set up headers
        $this->headers = [
            'X-API-Key' => $keyPair['api_key'],
            'X-API-Secret' => $keyPair['api_secret'],
            'Accept' => 'application/json',
        ];
    }

    /**
     * Make authenticated API request
     */
    protected function apiGet(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->headers)->getJson("/api/v1{$uri}", $data);
    }

    protected function apiPost(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->headers)->postJson("/api/v1{$uri}", $data);
    }

    protected function apiPatch(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->headers)->patchJson("/api/v1{$uri}", $data);
    }

    protected function apiPut(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->headers)->putJson("/api/v1{$uri}", $data);
    }

    protected function apiDelete(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->headers)->deleteJson("/api/v1{$uri}", $data);
    }

    /**
     * Assert successful API response
     */
    protected function assertApiSuccess(\Illuminate\Testing\TestResponse $response): void
    {
        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    /**
     * Assert API error response (custom API format)
     */
    protected function assertApiError(\Illuminate\Testing\TestResponse $response, int $status, string $code = null): void
    {
        $response->assertStatus($status);
        $response->assertJson(['success' => false]);

        if ($code) {
            $response->assertJsonPath('error.code', $code);
        }
    }

    /**
     * Assert validation error response (Laravel standard format)
     */
    protected function assertValidationError(\Illuminate\Testing\TestResponse $response, ?string $field = null): void
    {
        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);

        if ($field) {
            $response->assertJsonValidationErrors($field);
        }
    }

    /**
     * Create client with specific scopes
     */
    protected function createClientWithScopes(array $scopes): array
    {
        $keyPair = ApiClient::generateKeyPair();
        $client = ApiClient::create([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Limited Client',
            'api_key' => $keyPair['api_key'],
            'api_key_prefix' => $keyPair['api_key_prefix'],
            'api_secret' => $keyPair['api_secret'], // Plain text
            'scopes' => $scopes,
            'rate_plan' => 'free',
            'is_active' => true,
        ]);

        return [
            'client' => $client,
            'headers' => [
                'X-API-Key' => $keyPair['api_key'],
                'X-API-Secret' => $keyPair['api_secret'],
                'Accept' => 'application/json',
            ],
        ];
    }
}
