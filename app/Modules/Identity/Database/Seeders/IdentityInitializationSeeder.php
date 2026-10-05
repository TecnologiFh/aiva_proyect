<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Core\Enums\RoleType;
use App\Core\Enums\Permission\IdentityPermission;
use App\Core\Enums\Permission\BusinessPermission;
use App\Core\Enums\Permission\OrderPermission;
use App\Core\Enums\Permission\ConversationPermission;
use App\Core\Enums\Permission\ReportPermission;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use Illuminate\Database\Seeder;

class IdentityInitializationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Catálogo de Permisos (Fuente de Verdad en Enums)
        $enumClasses = [
            IdentityPermission::class,
            BusinessPermission::class,
            OrderPermission::class,
            ConversationPermission::class,
            ReportPermission::class,
        ];

        $createdPermissions = [];

        foreach ($enumClasses as $enumClass) {
            foreach ($enumClass::cases() as $case) {
                $permission = Permission::firstOrCreate(
                    ['code' => $case->value],
                    [
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'name' => str_replace([':', '_'], [' ', ' '], ucfirst($case->value)),
                    ]
                );
                $createdPermissions[$case->value] = $permission->id;
            }
        }

        // 2. Roles y Matriz RBAC desde config/rbac.php
        $rbacMatrix = config('rbac.matrix', []);

        foreach (RoleType::cases() as $roleType) {
            $roleName = $roleType->value;
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                [
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'description' => "Rol oficial {$roleName} para usuarios del tenant",
                ]
            );

            $permissionCodes = $rbacMatrix[$roleName] ?? [];
            $permissionIdsToSync = [];

            foreach ($permissionCodes as $code) {
                if (isset($createdPermissions[$code])) {
                    $permissionIdsToSync[] = $createdPermissions[$code];
                }
            }

            // Sincronizar en la tabla pivote role_permission
            $role->permissions()->sync($permissionIdsToSync);
        }
    }
}
