<?php

namespace App\Modules\Identity\Repositories;

use App\Modules\Identity\Models\Role;
use Illuminate\Support\Collection;

class RoleRepository
{
    public function findByName(string $name): ?Role
    {
        return Role::where('name', $name)->first();
    }

    public function findByUuid(string $uuid): ?Role
    {
        return Role::whereUuid($uuid)->first();
    }

    public function all(): Collection
    {
        return Role::with('permissions')->get();
    }

    public function syncPermissions(Role $role, array $permissionIds): void
    {
        $role->permissions()->sync($permissionIds);
    }
}
