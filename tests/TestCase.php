<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Services\TenantService;
use App\Services\TenantManager;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Очищаем tenant между тестами
        $this->clearTenantState();
    }

    protected function tearDown(): void
    {
        // Очищаем tenant после каждого теста
        $this->clearTenantState();

        parent::tearDown();
    }

    /**
     * Очистка состояния TenantService и TenantManager
     */
    protected function clearTenantState(): void
    {
        // Очищаем TenantService (статические свойства)
        try {
            $reflection = new \ReflectionClass(TenantService::class);

            $tenantProp = $reflection->getProperty('currentTenant');
            $tenantProp->setAccessible(true);
            $tenantProp->setValue(null, null);

            $restaurantProp = $reflection->getProperty('currentRestaurant');
            $restaurantProp->setAccessible(true);
            $restaurantProp->setValue(null, null);
        } catch (\Throwable $e) {
            // Игнорируем если класс не найден (первый setUp)
        }

        // Очищаем TenantManager (singleton в контейнере)
        try {
            if (app()->bound(TenantManager::class)) {
                app(TenantManager::class)->reset();
            }
        } catch (\Throwable $e) {
            // Игнорируем
        }
    }
}
