<?php

use App\Services\TenantManager;
use App\Exceptions\TenantNotSetException;

if (!function_exists('tenant')) {
    /**
     * Получить TenantManager
     */
    function tenant(): TenantManager
    {
        return app(TenantManager::class);
    }
}

if (!function_exists('tenant_id')) {
    /**
     * Получить ID текущего ресторана
     *
     * СТРОГИЙ РЕЖИМ: без параметра выбрасывает exception если tenant не установлен.
     * Это заставляет явно обрабатывать случаи без tenant.
     *
     * @param int|null $default Значение по умолчанию (DEPRECATED - используйте tenant_id_or_default())
     * @return int
     * @throws TenantNotSetException если tenant не установлен и default не указан
     */
    function tenant_id(?int $default = null): int
    {
        $manager = app(TenantManager::class);

        // Если передан default — используем его (для обратной совместимости)
        if ($default !== null) {
            // Логируем использование deprecated функционала
            if (!$manager->isSet()) {
                \Illuminate\Support\Facades\Log::warning(
                    'tenant_id() called with default fallback - consider fixing the code',
                    ['default' => $default, 'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)]
                );
            }
            return $manager->getOrDefault($default);
        }

        // Строгий режим — exception если не установлен
        return $manager->get();
    }
}

if (!function_exists('tenant_id_or_null')) {
    /**
     * Получить ID текущего ресторана или null
     *
     * Безопасная версия для случаев, когда tenant может быть не установлен
     * (консольные команды, очереди, публичные API).
     */
    function tenant_id_or_null(): ?int
    {
        return app(TenantManager::class)->getOrNull();
    }
}

if (!function_exists('tenant_id_or_default')) {
    /**
     * Получить ID текущего ресторана или значение по умолчанию
     *
     * Явная версия для случаев, когда нужен fallback.
     * Использование этой функции означает осознанное решение о fallback.
     */
    function tenant_id_or_default(int $default): int
    {
        return app(TenantManager::class)->getOrDefault($default);
    }
}

if (!function_exists('tenant_check')) {
    /**
     * Проверить, установлен ли tenant
     */
    function tenant_check(): bool
    {
        return app(TenantManager::class)->isSet();
    }
}

if (!function_exists('tenant_require')) {
    /**
     * Требовать наличие tenant, иначе abort(403)
     *
     * Используется в контроллерах для защиты endpoints.
     */
    function tenant_require(): int
    {
        $manager = app(TenantManager::class);

        if (!$manager->isSet()) {
            abort(403, 'Restaurant context required');
        }

        return $manager->get();
    }
}
