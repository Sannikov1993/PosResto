<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy for Reservation model authorization.
 *
 * Handles all authorization checks for reservation operations.
 * Called by controller or action classes before performing operations.
 *
 * Authorization is based on:
 * - User's restaurant membership
 * - User's role permissions
 * - Reservation status (for certain operations)
 */
class ReservationPolicy
{
    use HandlesAuthorization;

    /**
     * Roles that can manage all reservations.
     */
    private const MANAGER_ROLES = ['super_admin', 'admin', 'manager'];

    /**
     * Roles that can only view/seat reservations.
     */
    private const STAFF_ROLES = ['waiter', 'hostess', 'cashier'];

    /**
     * Determine if user can view list of reservations.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAnyRole($user);
    }

    /**
     * Determine if user can view a specific reservation.
     */
    public function view(User $user, Reservation $reservation): bool
    {
        return $this->belongsToSameRestaurant($user, $reservation);
    }

    /**
     * Determine if user can create reservations.
     */
    public function create(User $user): bool
    {
        return $this->hasAnyRole($user);
    }

    /**
     * Determine if user can update a reservation.
     */
    public function update(User $user, Reservation $reservation): bool
    {
        if (!$this->belongsToSameRestaurant($user, $reservation)) {
            return false;
        }

        // Can't update completed, cancelled, or no-show reservations
        if (in_array($reservation->status, ['completed', 'cancelled', 'no_show'])) {
            return $this->isManager($user);
        }

        return $this->hasAnyRole($user);
    }

    /**
     * Determine if user can delete a reservation.
     */
    public function delete(User $user, Reservation $reservation): bool
    {
        if (!$this->belongsToSameRestaurant($user, $reservation)) {
            return false;
        }

        // Can't delete if deposit is paid (must refund first)
        if ($reservation->deposit_status === 'paid') {
            return false;
        }

        // Only managers can delete
        return $this->isManager($user);
    }

    /**
     * Determine if user can confirm a reservation.
     */
    public function confirm(User $user, Reservation $reservation): bool
    {
        if (!$this->belongsToSameRestaurant($user, $reservation)) {
            return false;
        }

        // Can only confirm pending reservations
        if ($reservation->status !== 'pending') {
            return false;
        }

        return $this->hasAnyRole($user);
    }

    /**
     * Determine if user can seat guests.
     */
    public function seat(User $user, Reservation $reservation): bool
    {
        if (!$this->belongsToSameRestaurant($user, $reservation)) {
            return false;
        }

        // Can seat from pending or confirmed status
        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return false;
        }

        return $this->hasAnyRole($user);
    }

    /**
     * Determine if user can unseat guests.
     */
    public function unseat(User $user, Reservation $reservation): bool
    {
        if (!$this->belongsToSameRestaurant($user, $reservation)) {
            return false;
        }

        // Can only unseat seated reservations
        if ($reservation->status !== 'seated') {
            return false;
        }

        // Staff needs manager approval to unseat (force flag)
        // Managers can always unseat
        return $this->isManager($user);
    }

    /**
     * Determine if user can complete a reservation.
     */
    public function complete(User $user, Reservation $reservation): bool
    {
        if (!$this->belongsToSameRestaurant($user, $reservation)) {
            return false;
        }

        // Can complete from seated status
        if ($reservation->status !== 'seated') {
            return false;
        }

        return $this->hasAnyRole($user);
    }

    /**
     * Determine if user can cancel a reservation.
     */
    public function cancel(User $user, Reservation $reservation): bool
    {
        if (!$this->belongsToSameRestaurant($user, $reservation)) {
            return false;
        }

        // Can cancel pending, confirmed, or seated reservations
        if (!in_array($reservation->status, ['pending', 'confirmed', 'seated'])) {
            return false;
        }

        // If seated, only managers can cancel
        if ($reservation->status === 'seated') {
            return $this->isManager($user);
        }

        return $this->hasAnyRole($user);
    }

    /**
     * Determine if user can mark reservation as no-show.
     */
    public function markNoShow(User $user, Reservation $reservation): bool
    {
        if (!$this->belongsToSameRestaurant($user, $reservation)) {
            return false;
        }

        // Can mark no-show for pending or confirmed reservations
        if (!in_array($reservation->status, ['pending', 'confirmed'])) {
            return false;
        }

        return $this->hasAnyRole($user);
    }

    /**
     * Determine if user can pay deposit.
     */
    public function payDeposit(User $user, Reservation $reservation): bool
    {
        if (!$this->belongsToSameRestaurant($user, $reservation)) {
            return false;
        }

        // Can only pay if deposit is pending and required
        if ($reservation->deposit <= 0 || $reservation->deposit_status !== 'pending') {
            return false;
        }

        return $this->hasAnyRole($user);
    }

    /**
     * Determine if user can refund deposit.
     */
    public function refundDeposit(User $user, Reservation $reservation): bool
    {
        if (!$this->belongsToSameRestaurant($user, $reservation)) {
            return false;
        }

        // Can only refund paid deposits
        if ($reservation->deposit_status !== 'paid') {
            return false;
        }

        // Only managers can refund
        return $this->isManager($user);
    }

    /**
     * Check if user belongs to the same restaurant as the reservation.
     */
    private function belongsToSameRestaurant(User $user, Reservation $reservation): bool
    {
        // Super admin can access all
        if ($user->role === 'super_admin') {
            return true;
        }

        return $user->restaurant_id === $reservation->restaurant_id;
    }

    /**
     * Check if user has any allowed role.
     */
    private function hasAnyRole(User $user): bool
    {
        return in_array($user->role, array_merge(self::MANAGER_ROLES, self::STAFF_ROLES));
    }

    /**
     * Check if user is a manager.
     */
    private function isManager(User $user): bool
    {
        return in_array($user->role, self::MANAGER_ROLES);
    }
}
