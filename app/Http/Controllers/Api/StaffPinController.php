<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StaffPinController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Генерация нового PIN-кода
     */
    public function generate(): JsonResponse
    {
        $pin = User::generatePin();

        // Проверяем уникальность
        while (User::where('pin_code', \Hash::make($pin))->exists()) {
            $pin = User::generatePin();
        }

        return response()->json([
            'success' => true,
            'data' => ['pin' => $pin],
        ]);
    }

    /**
     * Сменить PIN-код сотрудника
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'pin' => 'required|string|min:4|max:6|regex:/^\d+$/',
        ]);

        $user->setPin($validated['pin']);

        return response()->json([
            'success' => true,
            'message' => 'PIN-код изменён',
        ]);
    }

    /**
     * Проверить PIN-код сотрудника (для подтверждения действий)
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => 'required|string|min:4|max:6',
            'role' => 'nullable|string',
        ]);

        $restaurantId = $this->getRestaurantId($request);
        $requiredRole = $validated['role'] ?? null;

        // Роли по уровню доступа (от низшего к высшему)
        $roleHierarchy = ['waiter' => 1, 'cashier' => 2, 'cook' => 2, 'courier' => 1, 'hostess' => 1, 'manager' => 3, 'admin' => 4, 'owner' => 5, 'super_admin' => 5];

        // Ищем сотрудника по PIN
        $users = User::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->whereNotNull('pin_code')
            ->get();

        foreach ($users as $user) {
            if ($user->verifyPin($validated['pin'])) {
                // Проверяем роль
                if ($requiredRole) {
                    $userLevel = $roleHierarchy[$user->role] ?? 0;
                    $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;

                    if ($userLevel < $requiredLevel) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Недостаточно прав для этого действия',
                        ], 403);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'PIN подтверждён',
                    'staff_id' => $user->id,
                    'staff_name' => $user->name,
                    'staff_role' => $user->role,
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Неверный PIN-код',
        ], 401);
    }
}
