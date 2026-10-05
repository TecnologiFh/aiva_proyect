<?php

namespace App\Core\Contracts\Repositories;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Collection;

interface TenantRepositoryInterface
{
    public function create(array $data): Tenant;
    public function findById(int $id): ?Tenant;
    public function findByUuid(string $uuid): ?Tenant;
    public function findBySlug(string $slug): ?Tenant;
    public function update(int $id, array $data): Tenant;
    public function delete(int $id): bool;
}
