<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Traits\BelongsToTenant;

class ApiClient extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'restaurant_id',
        'name',
        'description',
        'api_key',
        'api_secret',
        'api_key_prefix',
        'scopes',
        'rate_plan',
        'custom_rate_limit',
        'is_active',
        'activated_at',
        'deactivated_at',
        'deactivation_reason',
        'type',
        'environment',
        'allowed_ips',
        'allowed_origins',
        'metadata',
        'webhook_url',
        'webhook_secret',
        'webhook_events',
        'last_used_at',
        'total_requests',
        'total_errors',
        'created_by',
    ];

    protected $hidden = [
        'api_secret',
        'webhook_secret',
    ];

    protected $casts = [
        'scopes' => 'array',
        'allowed_ips' => 'array',
        'allowed_origins' => 'array',
        'metadata' => 'array',
        'webhook_events' => 'array',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'last_used_at' => 'datetime',
        'total_requests' => 'integer',
        'total_errors' => 'integer',
    ];

    // ===== TYPE CONSTANTS =====

    const TYPE_INTEGRATION = 'integration';
    const TYPE_WEBSITE = 'website';
    const TYPE_MOBILE = 'mobile';
    const TYPE_KIOSK = 'kiosk';
    const TYPE_AGGREGATOR = 'aggregator';

    const ENV_PRODUCTION = 'production';
    const ENV_SANDBOX = 'sandbox';

    // ===== RELATIONSHIPS =====

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    // ===== SCOPES =====

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeProduction($query)
    {
        return $query->where('environment', self::ENV_PRODUCTION);
    }

    public function scopeSandbox($query)
    {
        return $query->where('environment', self::ENV_SANDBOX);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ===== API KEY GENERATION =====

    /**
     * Generate a new API key pair
     */
    public static function generateKeyPair(): array
    {
        $prefix = config('api.keys.prefix', 'ml_');
        $keyLength = config('api.keys.length', 32);
        $secretLength = config('api.keys.secret_length', 48);

        $randomKey = Str::random($keyLength);
        $apiKey = $prefix . $randomKey;

        return [
            'api_key' => $apiKey,
            'api_key_prefix' => substr($apiKey, 0, 16),
            'api_secret' => Str::random($secretLength),
        ];
    }

    /**
     * Generate a new webhook secret
     */
    public static function generateWebhookSecret(): string
    {
        return 'whsec_' . Str::random(48);
    }

    /**
     * Create a new API client with generated keys
     */
    public static function createWithKeys(array $attributes): self
    {
        $keys = self::generateKeyPair();

        return self::create(array_merge($attributes, $keys, [
            'activated_at' => now(),
        ]));
    }

    // ===== AUTHENTICATION =====

    /**
     * Find client by API key
     */
    public static function findByApiKey(string $apiKey): ?self
    {
        $prefix = substr($apiKey, 0, 16);

        return self::where('api_key_prefix', $prefix)
            ->where('api_key', $apiKey)
            ->first();
    }

    /**
     * Verify API secret against stored hash
     */
    public function verifySecret(string $secret): bool
    {
        return Hash::check($secret, $this->api_secret);
    }

    /**
     * Hash api_secret on save
     */
    protected function apiSecret(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Hash::needsRehash($value) ? Hash::make($value) : $value,
        );
    }

    /**
     * Check if client can authenticate
     */
    public function canAuthenticate(): bool
    {
        return $this->is_active && $this->environment !== self::ENV_SANDBOX;
    }

    // ===== SCOPE CHECKING =====

    /**
     * Check if client has a specific scope
     */
    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];

        // Wildcard access
        if (in_array('*', $scopes)) {
            return true;
        }

        // Exact match
        if (in_array($scope, $scopes)) {
            return true;
        }

        // Resource-level wildcard (e.g., 'menu:*' matches 'menu:read')
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
     * Check if client has all specified scopes
     */
    public function hasAllScopes(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if (!$this->hasScope($scope)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if client has any of specified scopes
     */
    public function hasAnyScope(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if ($this->hasScope($scope)) {
                return true;
            }
        }
        return false;
    }

    // ===== RATE LIMITING =====

    /**
     * Get rate limit for this client
     */
    public function getRateLimit(): int
    {
        if ($this->custom_rate_limit) {
            return $this->custom_rate_limit;
        }

        $plans = config('api.rate_limiting.plans', []);
        $plan = $plans[$this->rate_plan] ?? $plans['free'] ?? ['requests_per_minute' => 60];

        return $plan['requests_per_minute'];
    }

    /**
     * Get burst limit for this client
     */
    public function getBurstLimit(): int
    {
        $plans = config('api.rate_limiting.plans', []);
        $plan = $plans[$this->rate_plan] ?? $plans['free'] ?? ['burst' => 10];

        return $plan['burst'];
    }

    /**
     * Get daily limit for this client
     */
    public function getDailyLimit(): ?int
    {
        $plans = config('api.rate_limiting.plans', []);
        $plan = $plans[$this->rate_plan] ?? $plans['free'] ?? ['daily_limit' => 1000];

        return $plan['daily_limit'];
    }

    // ===== IP WHITELIST =====

    /**
     * Check if IP is allowed
     */
    public function isIpAllowed(string $ip): bool
    {
        $allowedIps = $this->allowed_ips;

        // No whitelist = all IPs allowed
        if (empty($allowedIps)) {
            return true;
        }

        foreach ($allowedIps as $allowed) {
            if ($this->ipMatches($ip, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP matches pattern (supports CIDR notation)
     */
    protected function ipMatches(string $ip, string $pattern): bool
    {
        // Exact match
        if ($ip === $pattern) {
            return true;
        }

        // CIDR notation
        if (str_contains($pattern, '/')) {
            [$subnet, $mask] = explode('/', $pattern);
            $mask = (int) $mask;

            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);

            if ($ipLong === false || $subnetLong === false) {
                return false;
            }

            $maskLong = -1 << (32 - $mask);

            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        return false;
    }

    // ===== WEBHOOKS =====

    /**
     * Check if webhook is configured for event
     */
    public function hasWebhookForEvent(string $event): bool
    {
        if (empty($this->webhook_url)) {
            return false;
        }

        $events = $this->webhook_events ?? [];

        return in_array($event, $events) || in_array('*', $events);
    }

    // ===== STATISTICS =====

    /**
     * Increment request counter
     */
    public function recordRequest(bool $isError = false): void
    {
        $update = [
            'last_used_at' => now(),
            'total_requests' => $this->total_requests + 1,
        ];

        if ($isError) {
            $update['total_errors'] = $this->total_errors + 1;
        }

        $this->update($update);
    }

    // ===== ACTIVATION =====

    /**
     * Activate the client
     */
    public function activate(): void
    {
        $this->update([
            'is_active' => true,
            'activated_at' => now(),
            'deactivated_at' => null,
            'deactivation_reason' => null,
        ]);
    }

    /**
     * Deactivate the client
     */
    public function deactivate(string $reason = null): void
    {
        $this->update([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivation_reason' => $reason,
        ]);
    }

    /**
     * Regenerate API keys
     */
    public function regenerateKeys(): array
    {
        $keys = self::generateKeyPair();

        $this->update($keys);

        return [
            'api_key' => $keys['api_key'],
            'api_secret' => $keys['api_secret'],
        ];
    }

    // ===== HELPERS =====

    /**
     * Get type label
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_WEBSITE => 'Сайт',
            self::TYPE_MOBILE => 'Мобильное приложение',
            self::TYPE_KIOSK => 'Киоск',
            self::TYPE_AGGREGATOR => 'Агрегатор',
            default => 'Интеграция',
        };
    }

    /**
     * Get available types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_INTEGRATION => 'Интеграция',
            self::TYPE_WEBSITE => 'Сайт',
            self::TYPE_MOBILE => 'Мобильное приложение',
            self::TYPE_KIOSK => 'Киоск',
            self::TYPE_AGGREGATOR => 'Агрегатор',
        ];
    }

    /**
     * Get available rate plans
     */
    public static function getRatePlans(): array
    {
        return array_keys(config('api.rate_limiting.plans', []));
    }
}
