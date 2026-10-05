<?php

namespace App\Modules\Identity\Repositories;

use App\Core\Contracts\Repositories\UserRepositoryInterface;
use App\Modules\Identity\Models\User;

class UserRepository implements UserRepositoryInterface
{
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByUuid(string $uuid): ?User
    {
        return User::whereUuid($uuid)->first();
    }

    public function findByEmail(string $email, int $tenantId): ?User
    {
        return User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->first();
    }

    public function update(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $user;
    }
}
