<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CashShift;
use App\Models\User;

/**
 * Policy для финансовых операций.
 *
 * Управление кассовыми сменами, операциями и отчётами.
 */
class FinancePolicy
{
    /**
     * Суперадмин и владелец тенанта — полный доступ
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin() || $user->isTenantOwner()) {
            return true;
        }

        return null;
    }

    /**
     * Просмотр списка смен
     */
    public function viewShifts(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    /**
     * Открытие кассовой смены
     */
    public function openShift(User $user): bool
    {
        return $user->hasPermission('finance.edit');
    }

    /**
     * Закрытие кассовой смены — только своего ресторана
     */
    public function closeShift(User $user, CashShift $shift): bool
    {
        if ($user->restaurant_id !== $shift->restaurant_id) {
            return false;
        }

        if (!$user->hasPermission('finance.edit')) {
            return false;
        }

        // Смена должна быть открыта
        return $shift->isOpen();
    }

    /**
     * Кассовая операция (внесение/изъятие)
     */
    public function cashOperation(User $user, CashShift $shift): bool
    {
        if ($user->restaurant_id !== $shift->restaurant_id) {
            return false;
        }

        if (!$user->hasPermission('finance.edit')) {
            return false;
        }

        return $shift->isOpen();
    }

    /**
     * X-отчёт (промежуточный, без закрытия)
     */
    public function viewXReport(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    /**
     * Z-отчёт (итоговый при закрытии)
     */
    public function viewZReport(User $user, CashShift $shift): bool
    {
        if ($user->restaurant_id !== $shift->restaurant_id) {
            return false;
        }

        return $user->hasPermission('finance.view');
    }

    /**
     * Просмотр финансовой аналитики
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->hasPermission('reports.view');
    }

    /**
     * Управление финансовыми настройками
     */
    public function manageSettings(User $user): bool
    {
        return $user->hasPermission('finance.edit')
            && $user->isManager();
    }
}
