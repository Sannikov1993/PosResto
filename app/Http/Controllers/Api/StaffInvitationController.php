<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StaffInvitation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StaffInvitationController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Список приглашений
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $invitations = StaffInvitation::where('restaurant_id', $restaurantId)
            ->with(['creator', 'acceptedByUser'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $invitations,
        ]);
    }

    /**
     * Отправить приглашение существующему сотруднику
     */
    public function sendInvite(User $user): JsonResponse
    {
        $invitation = StaffInvitation::create([
            'restaurant_id' => $user->restaurant_id,
            'created_by' => auth()->id(),
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'role_id' => $user->role_id,
            'salary_type' => $user->salary_type ?? 'fixed',
            'salary_amount' => $user->salary_amount ?? 0,
            'hourly_rate' => $user->hourly_rate ?? 0,
            'token' => StaffInvitation::generateToken(),
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Приглашение создано',
            'data' => $invitation,
            'invite_url' => $invitation->invite_url,
        ]);
    }

    /**
     * Повторно отправить приглашение (генерирует новый токен)
     */
    public function resend(StaffInvitation $invitation): JsonResponse
    {
        $invitation->update([
            'token' => StaffInvitation::generateToken(),
            'status' => 'pending',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Приглашение обновлено',
            'data' => $invitation->fresh(),
            'invite_url' => $invitation->invite_url,
        ]);
    }

    /**
     * Отменить приглашение
     */
    public function cancel(StaffInvitation $invitation): JsonResponse
    {
        $invitation->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Приглашение отменено',
        ]);
    }
}
