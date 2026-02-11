<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StaffInvitation;
use App\Models\User;

class StaffInvitationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin() || $user->isTenantOwner()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('staff.view');
    }

    public function resend(User $user, StaffInvitation $invitation): bool
    {
        return $user->restaurant_id === $invitation->restaurant_id
            && $user->hasPermission('staff.edit');
    }

    public function cancel(User $user, StaffInvitation $invitation): bool
    {
        return $user->restaurant_id === $invitation->restaurant_id
            && $user->hasPermission('staff.delete');
    }
}
