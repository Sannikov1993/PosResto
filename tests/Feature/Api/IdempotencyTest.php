<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\ApiClient;
use App\Models\ApiIdempotencyKey;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Permission;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Restaurant $restaurant;
    protected Role $adminRole;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-idempotency',
            'email' => 'idempotency@test.com',
        ]);

        $this->restaurant = Restaurant::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Set tenant context for BelongsToTenant trait
        app(TenantService::class)->setCurrentTenant($this->tenant);

        $this->adminRole = Role::create([
            'restaurant_id' => $this->restaurant->id,
            'key' => 'admin',
            'name' => 'Administrator',
            'is_system' => true,
            'is_active' => true,
            'can_access_pos' => true,
            'can_access_backoffice' => true,
        ]);

        foreach (['settings.view', 'settings.edit'] as $key) {
            $perm = Permission::firstOrCreate([
                'restaurant_id' => $this->restaurant->id,
                'key' => $key,
            ], [
                'name' => $key,
                'group' => explode('.', $key)[0],
            ]);
            $this->adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $this->admin = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    protected function authenticate(?User $user = null): void
    {
        $user = $user ?? $this->admin;
        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    protected function createApiClient(array $overrides = []): ApiClient
    {
        $keys = ApiClient::generateKeyPair();

        return ApiClient::withoutGlobalScope('tenant')->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Test Client',
            'api_key' => $keys['api_key'],
            'api_key_prefix' => $keys['api_key_prefix'],
            'api_secret' => $keys['api_secret'],
            'scopes' => ['menu:read', 'orders:write'],
            'rate_plan' => 'free',
            'is_active' => true,
            'activated_at' => now(),
        ], $overrides));
    }

    // =====================================================
    // SCOPE ISOLATION
    // =====================================================

    /** @test */
    public function two_different_clients_with_same_key_get_different_responses(): void
    {
        $client1 = $this->createApiClient(['name' => 'Client 1']);
        $client2 = $this->createApiClient(['name' => 'Client 2']);

        // Store idempotency record for client 1
        ApiIdempotencyKey::store(
            key: 'test-key-123',
            apiClientId: $client1->id,
            userId: null,
            method: 'POST',
            path: 'api/orders',
            requestHash: hash('sha256', 'body1'),
            statusCode: 200,
            responseBody: json_encode(['client' => 'one']),
        );

        // Client 2 should NOT get client 1's cached response
        $result = ApiIdempotencyKey::findForClient($client2->id, null, 'test-key-123');
        $this->assertNull($result);

        // Client 1 should get its own cached response
        $result = ApiIdempotencyKey::findForClient($client1->id, null, 'test-key-123');
        $this->assertNotNull($result);
        $this->assertStringContainsString('one', $result->response_body);
    }

    /** @test */
    public function request_without_scope_does_not_use_idempotency_cache(): void
    {
        // When both api_client_id and user_id are null, findForClient should return null
        $result = ApiIdempotencyKey::findForClient(null, null, 'some-key');
        $this->assertNull($result);
    }

    /** @test */
    public function request_without_scope_cannot_store_idempotency_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Idempotency key requires api_client_id or user_id');

        ApiIdempotencyKey::store(
            key: 'orphan-key',
            apiClientId: null,
            userId: null,
            method: 'POST',
            path: 'api/orders',
            requestHash: hash('sha256', 'body'),
            statusCode: 200,
            responseBody: '{}',
        );
    }

    /** @test */
    public function same_client_same_key_gets_cached_response(): void
    {
        $client = $this->createApiClient();

        $cached = ApiIdempotencyKey::store(
            key: 'repeat-key',
            apiClientId: $client->id,
            userId: null,
            method: 'POST',
            path: 'api/orders',
            requestHash: hash('sha256', 'same-body'),
            statusCode: 201,
            responseBody: json_encode(['order_id' => 42]),
        );

        $result = ApiIdempotencyKey::findForClient($client->id, null, 'repeat-key');
        $this->assertNotNull($result);
        $this->assertEquals($cached->id, $result->id);
        $this->assertEquals(201, $result->status_code);
    }

    /** @test */
    public function user_scoped_idempotency_works(): void
    {
        $user = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        ApiIdempotencyKey::store(
            key: 'user-key-1',
            apiClientId: null,
            userId: $user->id,
            method: 'POST',
            path: 'api/orders',
            requestHash: hash('sha256', 'body'),
            statusCode: 200,
            responseBody: '{"ok":true}',
        );

        // Same user should find it
        $result = ApiIdempotencyKey::findForClient(null, $user->id, 'user-key-1');
        $this->assertNotNull($result);

        // Different user should not find it
        $otherUser = User::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
        $result = ApiIdempotencyKey::findForClient(null, $otherUser->id, 'user-key-1');
        $this->assertNull($result);
    }

    /** @test */
    public function expired_idempotency_keys_are_not_returned(): void
    {
        $client = $this->createApiClient();

        // Create an already-expired key
        ApiIdempotencyKey::create([
            'idempotency_key' => 'expired-key',
            'api_client_id' => $client->id,
            'user_id' => null,
            'method' => 'POST',
            'path' => 'api/orders',
            'request_hash' => hash('sha256', 'body'),
            'status_code' => 200,
            'response_body' => '{}',
            'created_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
        ]);

        $result = ApiIdempotencyKey::findForClient($client->id, null, 'expired-key');
        $this->assertNull($result);
    }
}
