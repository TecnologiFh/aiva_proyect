<?php

namespace App\Modules\Identity\Application\AssignUserRole;

use App\Modules\Identity\Models\User;

class AssignUserRoleResult
{
    public function __construct(
        public readonly User $user
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->user->uuid,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'roles' => $this->user->roles->pluck('name')->toArray(),
            'updated_at' => $this->user->updated_at?->toIso8601String(),
        ];
    }
}
