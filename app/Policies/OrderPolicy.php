<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Суперадмин и владелец тенанта имеют полный доступ
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin() || $user->isTenantOwner()) {
            return true;
        }

        return null;
    }

    /**
     * Просмотр списка заказов
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('orders.view');
    }

    /**
     * Просмотр конкретного заказа — только своего ресторана
     */
    public function view(User $user, Order $order): bool
    {
        return $user->restaurant_id === $order->restaurant_id
            && $user->hasPermission('orders.view');
    }

    /**
     * Создание заказа
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('orders.create');
    }

    /**
     * Обновление заказа — только своего ресторана
     */
    public function update(User $user, Order $order): bool
    {
        return $user->restaurant_id === $order->restaurant_id
            && $user->hasPermission('orders.edit');
    }

    /**
     * Отмена заказа — проверка permissions и суммы
     */
    public function cancel(User $user, Order $order): bool
    {
        if ($user->restaurant_id !== $order->restaurant_id) {
            return false;
        }

        if (!$user->hasPermission('orders.cancel')) {
            return false;
        }

        return $user->canCancelOrder((float) $order->total);
    }

    /**
     * Оплата заказа
     */
    public function pay(User $user, Order $order): bool
    {
        return $user->restaurant_id === $order->restaurant_id
            && $user->canProcessPayments();
    }

    /**
     * Возврат по заказу
     */
    public function refund(User $user, Order $order): bool
    {
        if ($user->restaurant_id !== $order->restaurant_id) {
            return false;
        }

        return $user->canRefund((float) $order->total);
    }

    /**
     * Изменение статуса заказа
     */
    public function updateStatus(User $user, Order $order): bool
    {
        return $user->restaurant_id === $order->restaurant_id
            && $user->hasPermission('orders.edit');
    }

    /**
     * Удаление (soft delete) — только admin+
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->restaurant_id === $order->restaurant_id
            && $user->isAdmin();
    }

    public function restore(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return false; // Никогда не удалять заказы навсегда
    }
}
