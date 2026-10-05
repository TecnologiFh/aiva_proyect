<?php

namespace App\Core\Contracts\Repositories;

use App\Modules\Identity\Models\User;

interface UserRepositoryInterface
{
    public function create(array $data): User;
    public function findById(int $id): ?User;
    public function findByUuid(string $uuid): ?User;
    public function findByEmail(string $email, int $tenantId): ?User;
    public function update(int $id, array $data): User;
}
