<?php

namespace App\Services;

use App\Exceptions\TenantNotSetException;

/**
 * Менеджер текущего tenant (ресторана)
 *
 * Singleton-сервис, который хранит restaurant_id для текущего запроса.
 * Устанавливается один раз в middleware и используется везде.
 *
 * Использование:
 *   app(TenantManager::class)->get()     // Получить ID (или exception)
 *   app(TenantManager::class)->getId()   // Алиас для get()
 *   tenant()->get()                       // Через хелпер
 *   tenant_id()                           // Короткий хелпер
 */
class TenantManager
{
    private ?int $restaurantId = null;
    private ?object $restaurant = null;

    /**
     * Установить текущий tenant
     */
    public function set(int $restaurantId): self
    {
        $this->restaurantId = $restaurantId;
        $this->restaurant = null; // Сбрасываем кеш
        return $this;
    }

    /**
     * Получить ID текущего ресторана
     * @throws TenantNotSetException если tenant не установлен
     */
    public function get(): int
    {
        if ($this->restaurantId === null) {
            throw new TenantNotSetException('Tenant (restaurant_id) not set for this request');
        }
        return $this->restaurantId;
    }

    /**
     * Алиас для get()
     */
    public function getId(): int
    {
        return $this->get();
    }

    /**
     * Проверить, установлен ли tenant
     */
    public function isSet(): bool
    {
        return $this->restaurantId !== null;
    }

    /**
     * Получить ID или null (без exception)
     */
    public function getOrNull(): ?int
    {
        return $this->restaurantId;
    }

    /**
     * Получить ID или значение по умолчанию
     */
    public function getOrDefault(int $default): int
    {
        return $this->restaurantId ?? $default;
    }

    /**
     * Получить модель ресторана (с кешированием)
     */
    public function getRestaurant(): ?object
    {
        if ($this->restaurant === null && $this->restaurantId !== null) {
            $this->restaurant = \App\Models\Restaurant::find($this->restaurantId);
        }
        return $this->restaurant;
    }

    /**
     * Сбросить tenant (для тестов или консольных команд)
     */
    public function reset(): self
    {
        $this->restaurantId = null;
        $this->restaurant = null;
        return $this;
    }

    /**
     * Выполнить код в контексте другого tenant
     */
    public function runAs(int $restaurantId, callable $callback): mixed
    {
        $previousId = $this->restaurantId;
        $previousRestaurant = $this->restaurant;

        try {
            $this->set($restaurantId);
            return $callback();
        } finally {
            $this->restaurantId = $previousId;
            $this->restaurant = $previousRestaurant;
        }
    }
}
