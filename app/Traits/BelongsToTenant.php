<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait для моделей, которые принадлежат тенанту.
 *
 * Использование:
 * 1. Добавить trait в модель: use BelongsToTenant;
 * 2. Убедиться, что в таблице есть поле tenant_id
 *
 * Trait автоматически:
 * - Добавляет связь tenant()
 * - Добавляет global scope для фильтрации по текущему тенанту
 * - Автоматически устанавливает tenant_id при создании записи
 */
trait BelongsToTenant
{
    /**
     * Boot trait
     */
    public static function bootBelongsToTenant(): void
    {
        // Добавляем global scope для автоматической фильтрации
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantService = app(TenantService::class);

            // Если есть текущий тенант, фильтруем по нему
            if ($tenantService->hasTenant()) {
                $builder->where(
                    $builder->getModel()->getTable() . '.tenant_id',
                    $tenantService->getCurrentTenantId()
                );
            }
        });

        // Автоматически устанавливаем tenant_id при создании
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $tenantService = app(TenantService::class);

                if ($tenantService->hasTenant()) {
                    $model->tenant_id = $tenantService->getCurrentTenantId();
                }
            }
        });
    }

    /**
     * Связь с тенантом
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope для получения записей без фильтра по тенанту
     * Используется когда нужно получить данные всех тенантов (например, для суперадмина)
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Scope для фильтрации по конкретному тенанту
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->withoutGlobalScope('tenant')
            ->where($this->getTable() . '.tenant_id', $tenantId);
    }
}
