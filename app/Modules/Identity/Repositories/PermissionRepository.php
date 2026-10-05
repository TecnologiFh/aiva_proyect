<?php

namespace App\Modules\Identity\Repositories;

use App\Modules\Identity\Models\Permission;
use Illuminate\Support\Collection;

class PermissionRepository
{
    public function firstOrCreateByCode(string $code, string $name): Permission
    {
        return Permission::firstOrCreate(
            ['code' => $code],
            ['name' => $name]
        );
    }

    public function all(): Collection
    {
        return Permission::all();
    }
}
