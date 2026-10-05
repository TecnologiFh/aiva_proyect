<?php

namespace App\Modules\Tenant\Repositories;

use App\Core\Contracts\Repositories\TenantRepositoryInterface;
use App\Modules\Tenant\Models\Tenant;

class TenantRepository implements TenantRepositoryInterface
{
    public function create(array $data): Tenant
    {
        return Tenant::create($data);
    }

    public function findById(int $id): ?Tenant
    {
        return Tenant::find($id);
    }

    public function findByUuid(string $uuid): ?Tenant
    {
        return Tenant::whereUuid($uuid)->first();
    }

    public function findBySlug(string $slug): ?Tenant
    {
        return Tenant::where('slug', $slug)->first();
    }

    public function update(int $id, array $data): Tenant
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update($data);
        return $tenant;
    }

    public function delete(int $id): bool
    {
        $tenant = Tenant::findOrFail($id);
        return $tenant->delete();
    }
}
