<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StaffPasswordController extends Controller
{
    /**
     * Сменить пароль сотрудника
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'nullable|string|min:6',
        ]);

        // Cast 'hashed' в модели хеширует автоматически — НЕ использовать Hash::make()
        $user->update([
            'password' => $validated['password'] ?? \Str::random(12),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Пароль изменён',
        ]);
    }

    /**
     * Отправить ссылку для сброса пароля сотруднику
     */
    public function sendReset(Request $request, User $user): JsonResponse
    {
        if (!$user->email) {
            return response()->json([
                'success' => false,
                'message' => 'У сотрудника не указан email',
            ], 422);
        }

        // Delete any existing password reset invitations
        \App\Models\StaffInvitation::where('user_id', $user->id)
            ->where('type', 'password_reset')
            ->delete();

        // Create password reset invitation
        $invitation = \App\Models\StaffInvitation::create([
            'restaurant_id' => $user->restaurant_id,
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
            'type' => 'password_reset',
            'token' => \Str::random(64),
            'expires_at' => now()->addHours(24),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ссылка для сброса пароля отправлена на ' . $user->email,
            'data' => [
                'reset_url' => url('/staff/reset-password/' . $invitation->token),
            ],
        ]);
    }
}
