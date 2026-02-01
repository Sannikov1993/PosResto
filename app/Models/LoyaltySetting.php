<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Traits\BelongsToTenant;

class LoyaltySetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'restaurant_id',
        'key',
        'value',
    ];

    /**
     * Get setting value by key for tenant
     */
    public static function get($key, $default = null, $tenantId = null)
    {
        // Если tenantId не передан, пробуем получить из текущего контекста
        if ($tenantId === null) {
            $tenantService = app(\App\Services\TenantService::class);
            $tenantId = $tenantService->getCurrentTenantId() ?? 1;
        }

        $setting = self::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Set setting value for tenant
     */
    public static function set($key, $value, $tenantId = null)
    {
        if ($tenantId === null) {
            $tenantService = app(\App\Services\TenantService::class);
            $tenantId = $tenantService->getCurrentTenantId() ?? 1;
        }

        return self::withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Get all settings for tenant
     */
    public static function getAll($tenantId = null)
    {
        if ($tenantId === null) {
            $tenantService = app(\App\Services\TenantService::class);
            $tenantId = $tenantService->getCurrentTenantId() ?? 1;
        }

        return self::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->pluck('value', 'key')
            ->toArray();
    }
}
