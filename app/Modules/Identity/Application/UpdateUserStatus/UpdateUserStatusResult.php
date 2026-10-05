<?php

namespace App\Modules\Identity\Application\UpdateUserStatus;

use App\Modules\Identity\Models\User;

class UpdateUserStatusResult
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
            'status' => $this->user->status->value,
            'updated_at' => $this->user->updated_at?->toIso8601String(),
        ];
    }
}
