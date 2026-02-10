<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Policy для управления сотрудниками.
 *
 * Авторизация основана на:
 * - Принадлежности к ресторану
 * - Правах доступа (permissions)
 * - Иерархии ролей (нельзя управлять вышестоящим)
 */
class StaffPolicy
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
     * Просмотр списка сотрудников
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('staff.view');
    }

    /**
     * Просмотр профиля сотрудника — только своего ресторана
     */
    public function view(User $user, User $staff): bool
    {
        return $user->restaurant_id === $staff->restaurant_id
            && $user->hasPermission('staff.view');
    }

    /**
     * Создание сотрудника
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('staff.create');
    }

    /**
     * Обновление сотрудника — нельзя редактировать вышестоящего
     */
    public function update(User $user, User $staff): bool
    {
        if ($user->restaurant_id !== $staff->restaurant_id) {
            return false;
        }

        if (!$user->hasPermission('staff.edit')) {
            return false;
        }

        // Нельзя редактировать owner, если ты не owner
        if ($staff->role === User::ROLE_OWNER && !$user->isAdmin()) {
            return false;
        }

        return true;
    }

    /**
     * Увольнение/удаление сотрудника
     */
    public function delete(User $user, User $staff): bool
    {
        if ($user->restaurant_id !== $staff->restaurant_id) {
            return false;
        }

        if (!$user->hasPermission('staff.delete')) {
            return false;
        }

        // Нельзя удалить самого себя
        if ($user->id === $staff->id) {
            return false;
        }

        // Нельзя удалить owner
        if ($staff->role === User::ROLE_OWNER) {
            return false;
        }

        return true;
    }

    /**
     * Управление расписанием сотрудника
     */
    public function manageSchedule(User $user, User $staff): bool
    {
        if ($user->restaurant_id !== $staff->restaurant_id) {
            return false;
        }

        return $user->hasPermission('staff.edit');
    }

    /**
     * Просмотр зарплатных данных
     */
    public function viewSalary(User $user, User $staff): bool
    {
        if ($user->restaurant_id !== $staff->restaurant_id) {
            return false;
        }

        // Свою зарплату может смотреть каждый
        if ($user->id === $staff->id) {
            return true;
        }

        return $user->hasPermission('finance.view');
    }

    /**
     * Изменение зарплатных данных
     */
    public function editSalary(User $user, User $staff): bool
    {
        if ($user->restaurant_id !== $staff->restaurant_id) {
            return false;
        }

        return $user->hasPermission('finance.edit');
    }
}
