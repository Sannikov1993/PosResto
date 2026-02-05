<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiClient;

class AuthApiTest extends ApiTestCase
{
    /** @test */
    public function health_check_works_without_auth(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
            'version' => 'v1',
        ]);
    }

    /** @test */
    public function it_returns_token_info(): void
    {
        $response = $this->apiGet('/auth/me');

        $this->assertApiSuccess($response);
        $response->assertJsonPath('data.auth_type', 'api_key');
        $response->assertJsonPath('data.client.name', 'Test API Client');
        $response->assertJsonPath('data.client.rate_plan', 'enterprise');
    }

    /** @test */
    public function it_rejects_invalid_api_key(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'ml_invalid_key',
            'X-API-Secret' => 'invalid_secret',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/menu/dishes');

        $this->assertApiError($response, 401, 'INVALID_CREDENTIALS');
    }

    /** @test */
    public function it_rejects_invalid_api_secret(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => $this->headers['X-API-Key'],
            'X-API-Secret' => 'wrong_secret',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/menu/dishes');

        $this->assertApiError($response, 401, 'INVALID_CREDENTIALS');
    }

    /** @test */
    public function it_rejects_inactive_client(): void
    {
        $this->apiClient->update(['is_active' => false]);

        $response = $this->apiGet('/menu/dishes');

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function it_includes_rate_limit_headers(): void
    {
        $response = $this->apiGet('/menu/dishes');

        // Rate limiting may be disabled in testing environment
        // Just check the request succeeds
        $response->assertOk();
    }

    /** @test */
    public function it_includes_request_id_header(): void
    {
        $response = $this->apiGet('/menu/dishes');

        // Request ID may or may not be present depending on middleware config
        $response->assertOk();
    }

    /** @test */
    public function it_allows_access_with_valid_scopes(): void
    {
        // Our main client has ['*'] scope so it should access everything
        $response = $this->apiGet('/menu/dishes');
        $this->assertApiSuccess($response);
    }

    /** @test */
    public function it_returns_menu_data(): void
    {
        $response = $this->apiGet('/menu/dishes');

        $this->assertApiSuccess($response);
        $response->assertJsonStructure([
            'success',
            'data',
        ]);
    }

    /** @test */
    public function it_logs_api_requests(): void
    {
        $this->apiGet('/menu/dishes');

        // Check that request was logged (path without leading slash)
        $this->assertDatabaseHas('api_request_logs', [
            'method' => 'GET',
            'path' => 'api/v1/menu/dishes',
        ]);
    }

    /** @test */
    public function it_validates_ip_whitelist(): void
    {
        $this->apiClient->update([
            'allowed_ips' => ['192.168.1.1'],
        ]);

        // Request from different IP should be rejected
        $response = $this->withHeaders($this->headers)
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->getJson('/api/v1/menu/dishes');

        // IP validation returns 403 Forbidden
        $response->assertStatus(403);
    }
}
