<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    use Traits\ResolvesRestaurantId;

    /**
     * Список всех ролей
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $this->getRestaurantId($request);

        $roles = Role::where(function ($q) use ($restaurantId) {
                $q->whereNull('restaurant_id')
                  ->orWhere('restaurant_id', $restaurantId);
            })
            ->active()
            ->ordered()
            ->with('permissions:id,key,name,group')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Получить все доступные разрешения
     */
    public function permissions(): JsonResponse
    {
        $groups = Permission::getGroups();

        return response()->json([
            'success' => true,
            'data' => [
                'groups' => $groups,
                'all' => Permission::getAllPermissions(),
                'interfaces' => Role::getInterfaceOptions(),
            ],
        ]);
    }

    /**
     * Показать роль с разрешениями
     */
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions:id,key,name,group');

        return response()->json([
            'success' => true,
            'data' => $role,
        ]);
    }

    /**
     * Создать новую роль
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'key' => 'nullable|string|max:50|regex:/^[a-z0-9_]+$/',
            'description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:10',
            'permissions' => 'array',
            'permissions.*' => 'string',
            // Лимиты
            'max_discount_percent' => 'nullable|integer|min:0|max:100',
            'max_refund_amount' => 'nullable|integer|min:0',
            'max_cancel_amount' => 'nullable|integer|min:0',
            // Доступ к интерфейсам
            'can_access_pos' => 'nullable|boolean',
            'can_access_backoffice' => 'nullable|boolean',
            'can_access_kitchen' => 'nullable|boolean',
            'can_access_delivery' => 'nullable|boolean',
            // Ограничения
            'require_manager_confirm' => 'nullable|boolean',
            'allowed_halls' => 'nullable|array',
            'allowed_payment_methods' => 'nullable|array',
        ]);

        $restaurantId = $this->getRestaurantId($request);

        // Автогенерация ключа из названия если не указан
        $key = $validated['key'] ?? $this->generateKeyFromName($validated['name']);

        // Проверяем уникальность ключа
        $originalKey = $key;
        $counter = 1;
        while (Role::where('key', $key)->exists()) {
            $key = $originalKey . '_' . $counter++;
        }

        $role = Role::create([
            'restaurant_id' => $restaurantId,
            'key' => $key,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? '#6b7280',
            'icon' => $validated['icon'] ?? '👤',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => Role::max('sort_order') + 1,
            // Лимиты
            'max_discount_percent' => $validated['max_discount_percent'] ?? 0,
            'max_refund_amount' => $validated['max_refund_amount'] ?? 0,
            'max_cancel_amount' => $validated['max_cancel_amount'] ?? 0,
            // Доступ к интерфейсам
            'can_access_pos' => $validated['can_access_pos'] ?? false,
            'can_access_backoffice' => $validated['can_access_backoffice'] ?? false,
            'can_access_kitchen' => $validated['can_access_kitchen'] ?? false,
            'can_access_delivery' => $validated['can_access_delivery'] ?? false,
            // Ограничения
            'require_manager_confirm' => $validated['require_manager_confirm'] ?? false,
            'allowed_halls' => $validated['allowed_halls'] ?? null,
            'allowed_payment_methods' => $validated['allowed_payment_methods'] ?? null,
        ]);

        // Синхронизируем разрешения
        if (!empty($validated['permissions'])) {
            $this->syncPermissions($role, $validated['permissions'], $restaurantId);
        }

        $role->load('permissions:id,key,name,group');

        return response()->json([
            'success' => true,
            'message' => 'Роль создана',
            'data' => $role,
        ], 201);
    }

    /**
     * Обновить роль
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:10',
            'permissions' => 'array',
            'permissions.*' => 'string',
            'is_active' => 'sometimes|boolean',
            // Лимиты
            'max_discount_percent' => 'nullable|integer|min:0|max:100',
            'max_refund_amount' => 'nullable|integer|min:0',
            'max_cancel_amount' => 'nullable|integer|min:0',
            // Доступ к интерфейсам
            'can_access_pos' => 'nullable|boolean',
            'can_access_backoffice' => 'nullable|boolean',
            'can_access_kitchen' => 'nullable|boolean',
            'can_access_delivery' => 'nullable|boolean',
            // Ограничения
            'require_manager_confirm' => 'nullable|boolean',
            'allowed_halls' => 'nullable|array',
            'allowed_payment_methods' => 'nullable|array',
        ]);

        // Системную роль нельзя переименовать ключ
        if ($role->is_system && isset($validated['key'])) {
            unset($validated['key']);
        }

        $updateData = [
            'name' => $validated['name'] ?? $role->name,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $role->description,
            'color' => $validated['color'] ?? $role->color,
            'icon' => $validated['icon'] ?? $role->icon,
            'is_active' => $validated['is_active'] ?? $role->is_active,
        ];

        // Добавляем лимиты если переданы
        if (array_key_exists('max_discount_percent', $validated)) {
            $updateData['max_discount_percent'] = $validated['max_discount_percent'];
        }
        if (array_key_exists('max_refund_amount', $validated)) {
            $updateData['max_refund_amount'] = $validated['max_refund_amount'];
        }
        if (array_key_exists('max_cancel_amount', $validated)) {
            $updateData['max_cancel_amount'] = $validated['max_cancel_amount'];
        }

        // Добавляем доступы к интерфейсам
        if (array_key_exists('can_access_pos', $validated)) {
            $updateData['can_access_pos'] = $validated['can_access_pos'];
        }
        if (array_key_exists('can_access_backoffice', $validated)) {
            $updateData['can_access_backoffice'] = $validated['can_access_backoffice'];
        }
        if (array_key_exists('can_access_kitchen', $validated)) {
            $updateData['can_access_kitchen'] = $validated['can_access_kitchen'];
        }
        if (array_key_exists('can_access_delivery', $validated)) {
            $updateData['can_access_delivery'] = $validated['can_access_delivery'];
        }

        // Ограничения
        if (array_key_exists('require_manager_confirm', $validated)) {
            $updateData['require_manager_confirm'] = $validated['require_manager_confirm'];
        }
        if (array_key_exists('allowed_halls', $validated)) {
            $updateData['allowed_halls'] = $validated['allowed_halls'];
        }
        if (array_key_exists('allowed_payment_methods', $validated)) {
            $updateData['allowed_payment_methods'] = $validated['allowed_payment_methods'];
        }

        $role->update($updateData);

        // Синхронизируем разрешения
        if (isset($validated['permissions'])) {
            $restaurantId = $this->getRestaurantId($request);
            $this->syncPermissions($role, $validated['permissions'], $restaurantId);
        }

        $role->load('permissions:id,key,name,group');

        return response()->json([
            'success' => true,
            'message' => 'Роль обновлена',
            'data' => $role->fresh()->load('permissions:id,key,name,group'),
        ]);
    }

    /**
     * Удалить роль
     */
    public function destroy(Role $role): JsonResponse
    {
        // Системную роль нельзя удалить
        if ($role->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'Системную роль нельзя удалить',
            ], 422);
        }

        // Проверяем, есть ли пользователи с этой ролью
        if ($role->users_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалить роль с назначенными сотрудниками',
            ], 422);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Роль удалена',
        ]);
    }

    /**
     * Переключить активность роли
     */
    public function toggleActive(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя деактивировать системную роль',
            ], 422);
        }

        $role->update(['is_active' => !$role->is_active]);

        return response()->json([
            'success' => true,
            'message' => $role->is_active ? 'Роль активирована' : 'Роль деактивирована',
            'data' => $role,
        ]);
    }

    /**
     * Обновить порядок ролей
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:roles,id',
        ]);

        foreach ($validated['order'] as $index => $roleId) {
            Role::where('id', $roleId)->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Порядок обновлён',
        ]);
    }

    /**
     * Клонировать роль
     */
    public function clone(Role $role): JsonResponse
    {
        $newRole = $role->replicate();
        $newRole->key = $role->key . '_copy_' . time();
        $newRole->name = $role->name . ' (копия)';
        $newRole->is_system = false;
        $newRole->sort_order = Role::max('sort_order') + 1;
        $newRole->save();

        // Копируем разрешения
        $newRole->permissions()->attach($role->permissions->pluck('id'));

        $newRole->load('permissions:id,key,name,group');

        return response()->json([
            'success' => true,
            'message' => 'Роль скопирована',
            'data' => $newRole,
        ]);
    }

    /**
     * Синхронизация разрешений для роли
     */
    private function syncPermissions(Role $role, array $permissionKeys, int $restaurantId): void
    {
        // Получаем или создаём разрешения
        $permissionIds = [];

        foreach ($permissionKeys as $key) {
            $permission = Permission::firstOrCreate(
                ['key' => $key, 'restaurant_id' => null],
                [
                    'name' => Permission::getAllPermissions()[$key]['name'] ?? $key,
                    'group' => Permission::getAllPermissions()[$key]['group'] ?? 'other',
                    'is_system' => true,
                ]
            );
            $permissionIds[] = $permission->id;
        }

        $role->permissions()->sync($permissionIds);
    }

    /**
     * Генерация ключа из названия роли (транслитерация)
     */
    private function generateKeyFromName(string $name): string
    {
        $translitMap = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
            'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
            'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            ' ' => '_', '-' => '_',
        ];

        $name = mb_strtolower($name);
        $key = '';

        for ($i = 0; $i < mb_strlen($name); $i++) {
            $char = mb_substr($name, $i, 1);
            if (isset($translitMap[$char])) {
                $key .= $translitMap[$char];
            } elseif (preg_match('/[a-z0-9_]/', $char)) {
                $key .= $char;
            }
        }

        // Убираем двойные подчёркивания и обрезаем
        $key = preg_replace('/_+/', '_', $key);
        $key = trim($key, '_');

        return substr($key, 0, 50) ?: 'custom_role';
    }
}
