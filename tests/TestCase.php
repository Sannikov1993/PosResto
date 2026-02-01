<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Services\TenantService;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Очищаем статический tenant между тестами
        $this->clearTenantState();
    }

    protected function tearDown(): void
    {
        // Очищаем tenant после каждого теста
        $this->clearTenantState();

        parent::tearDown();
    }

    /**
     * Очистка статического состояния TenantService
     */
    protected function clearTenantState(): void
    {
        // Используем рефлексию для очистки статических свойств
        // чтобы избежать создания сервиса до app boot
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
    }
}
