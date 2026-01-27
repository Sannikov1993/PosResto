<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Создаём все разрешения
        $this->createPermissions();

        // Создаём роли с разрешениями
        $this->createRoles();
    }

    private function createPermissions(): void
    {
        $groups = Permission::getGroups();

        foreach ($groups as $groupKey => $group) {
            foreach ($group['permissions'] as $key => $name) {
                Permission::firstOrCreate(
                    ['key' => $key, 'restaurant_id' => null],
                    [
                        'name' => $name,
                        'group' => $groupKey,
                        'description' => null,
                        'is_system' => true,
                    ]
                );
            }
        }

        // Добавляем специальное разрешение "полный доступ"
        Permission::firstOrCreate(
            ['key' => '*', 'restaurant_id' => null],
            [
                'name' => 'Полный доступ',
                'group' => 'system',
                'description' => 'Доступ ко всем функциям системы',
                'is_system' => true,
            ]
        );
    }

    private function createRoles(): void
    {
        $defaultRoles = Role::getDefaultRoles();

        foreach ($defaultRoles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);

            $role = Role::firstOrCreate(
                ['key' => $roleData['key'], 'restaurant_id' => null],
                $roleData
            );

            // Синхронизируем разрешения
            $permissionIds = Permission::whereIn('key', $permissions)
                ->whereNull('restaurant_id')
                ->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }
}
