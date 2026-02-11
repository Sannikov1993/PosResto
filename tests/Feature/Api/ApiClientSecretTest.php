<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\ApiClient;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Permission;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class ApiClientSecretTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Restaurant $restaurant;
    protected Role $adminRole;
    protected User $admin;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-secret',
            'email' => 'secret@test.com',
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

        $adminPermissions = ['settings.view', 'settings.edit'];
        foreach ($adminPermissions as $key) {
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
            'tenant_id' => $this->tenant->id,
            'restaurant_id' => $this->restaurant->id,
            'role' => 'admin',
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);
    }

    protected function authenticate(): void
    {
        $this->token = $this->admin->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $this->token);
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
            'scopes' => ['menu:read'],
            'rate_plan' => 'free',
            'is_active' => true,
            'activated_at' => now(),
        ], $overrides));
    }

    // =====================================================
    // MODEL: VERIFY SECRET WITH HASH
    // =====================================================

    /** @test */
    public function verify_secret_works_with_hashed_value(): void
    {
        $plaintextSecret = \Illuminate\Support\Str::random(48);

        $client = $this->createApiClient([
            'name' => 'Verify Secret Client',
            'api_secret' => $plaintextSecret,
        ]);

        // The secret should be hashed in DB, not plaintext
        $client->refresh();
        $this->assertNotEquals($plaintextSecret, $client->getRawOriginal('api_secret'));
        $this->assertTrue(str_starts_with($client->getRawOriginal('api_secret'), '$2y$'));

        // verifySecret should still work with the plaintext input
        $this->assertTrue($client->verifySecret($plaintextSecret));
        $this->assertFalse($client->verifySecret('wrong-secret'));
    }

    /** @test */
    public function api_secret_is_hashed_on_save(): void
    {
        $client = $this->createApiClient(['name' => 'Hash Test']);

        // Fetch raw value from DB
        $rawSecret = $client->getRawOriginal('api_secret');
        $this->assertTrue(Hash::isHashed($rawSecret));
    }

    /** @test */
    public function already_hashed_secret_is_not_double_hashed(): void
    {
        $hashed = Hash::make('some-secret-value');

        $client = $this->createApiClient([
            'name' => 'Double Hash Test',
            'api_secret' => $hashed,
        ]);

        // Should be the same hash, not double-hashed
        $this->assertEquals($hashed, $client->getRawOriginal('api_secret'));
    }

    // =====================================================
    // CONTROLLER: STORE - ONE TIME REVEAL
    // =====================================================

    /** @test */
    public function store_returns_plaintext_secret_once(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/backoffice/api-clients', [
            'name' => 'New Client',
            'scopes' => ['menu:read'],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'name', 'api_key', 'api_secret'],
            ]);

        $data = $response->json('data');

        // api_secret returned should be plaintext (not hashed)
        $this->assertNotEmpty($data['api_secret']);
        $this->assertFalse(str_starts_with($data['api_secret'], '$2y$'));

        // But in DB it should be hashed
        $client = ApiClient::find($data['id']);
        $this->assertTrue(str_starts_with($client->getRawOriginal('api_secret'), '$2y$'));

        // The plaintext secret should verify against the hash
        $this->assertTrue($client->verifySecret($data['api_secret']));

        // Message should mention saving the secret
        $this->assertStringContainsString('Сохраните секрет', $response->json('message'));
    }

    // =====================================================
    // CONTROLLER: SHOW - NO SECRET
    // =====================================================

    /** @test */
    public function show_does_not_return_api_secret(): void
    {
        $this->authenticate();

        $client = $this->createApiClient([
            'name' => 'Show Test Client',
            'webhook_url' => 'https://example.com/hook',
            'webhook_secret' => ApiClient::generateWebhookSecret(),
        ]);

        $response = $this->getJson("/api/backoffice/api-clients/{$client->id}");

        $response->assertOk();

        $data = $response->json('data');

        // Should NOT have api_secret or webhook_secret values
        $this->assertArrayNotHasKey('api_secret', $data);
        $this->assertArrayNotHasKey('webhook_secret', $data);

        // Should have boolean indicators instead
        $this->assertTrue($data['api_secret_set']);
        $this->assertTrue($data['webhook_secret_set']);
    }

    /** @test */
    public function show_reports_false_when_secrets_not_set(): void
    {
        $this->authenticate();

        $client = $this->createApiClient(['name' => 'No Secrets Client']);

        $response = $this->getJson("/api/backoffice/api-clients/{$client->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertTrue($data['api_secret_set']);
        $this->assertFalse($data['webhook_secret_set']);
    }

    // =====================================================
    // CONTROLLER: UPDATE - NO DATA LEAK
    // =====================================================

    /** @test */
    public function update_does_not_leak_secret_data(): void
    {
        $this->authenticate();

        $client = $this->createApiClient(['name' => 'Update Test Client']);

        $response = $this->putJson("/api/backoffice/api-clients/{$client->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk();

        $data = $response->json('data');

        // Response should NOT contain secrets
        $this->assertArrayNotHasKey('api_secret', $data);
        $this->assertArrayNotHasKey('webhook_secret', $data);
        $this->assertTrue($data['updated']);
    }

    // =====================================================
    // CONTROLLER: REGENERATE CREDENTIALS
    // =====================================================

    /** @test */
    public function regenerate_credentials_returns_new_secret_once(): void
    {
        $this->authenticate();

        $oldPlaintextSecret = \Illuminate\Support\Str::random(48);
        $client = $this->createApiClient([
            'name' => 'Regen Test Client',
            'api_secret' => $oldPlaintextSecret,
        ]);

        $response = $this->postJson("/api/backoffice/api-clients/{$client->id}/regenerate", [
            'type' => 'secret',
        ]);

        $response->assertOk();

        $data = $response->json('data');

        // Should contain the new plaintext secret
        $this->assertNotEmpty($data['api_secret']);
        $this->assertFalse(str_starts_with($data['api_secret'], '$2y$'));

        // Verify it actually works against DB hash
        $client->refresh();
        $this->assertTrue($client->verifySecret($data['api_secret']));

        // Old secret should no longer work
        $this->assertFalse($client->verifySecret($oldPlaintextSecret));

        // Message should mention saving
        $this->assertStringContainsString('Сохраните', $response->json('message'));
    }

    /** @test */
    public function regenerate_both_returns_new_key_and_secret(): void
    {
        $this->authenticate();

        $client = $this->createApiClient(['name' => 'Regen Both Test']);
        $oldKey = $client->api_key;

        $response = $this->postJson("/api/backoffice/api-clients/{$client->id}/regenerate", [
            'type' => 'both',
        ]);

        $response->assertOk();

        $data = $response->json('data');

        $this->assertNotEquals($oldKey, $data['api_key']);
        $this->assertNotEmpty($data['api_secret']);
        $this->assertFalse(str_starts_with($data['api_secret'], '$2y$'));
    }

    /** @test */
    public function regenerate_webhook_returns_new_webhook_secret(): void
    {
        $this->authenticate();

        $client = $this->createApiClient([
            'name' => 'Regen Webhook Test',
            'webhook_url' => 'https://example.com/hook',
            'webhook_secret' => ApiClient::generateWebhookSecret(),
        ]);

        $response = $this->postJson("/api/backoffice/api-clients/{$client->id}/regenerate", [
            'type' => 'webhook',
        ]);

        $response->assertOk();

        $data = $response->json('data');

        // Should have new webhook secret
        $this->assertNotEmpty($data['webhook_secret']);
        // api_secret should be null (not regenerated)
        $this->assertNull($data['api_secret']);
    }
}
