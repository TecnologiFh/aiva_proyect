<?php

namespace App\Modules\Identity\Application\TransferOwnership;

use App\Modules\Identity\Models\User;

class TransferOwnershipResult
{
    public function __construct(
        public readonly User $previousOwner,
        public readonly User $newOwner
    ) {}

    public function toArray(): array
    {
        return [
            'previous_owner' => [
                'uuid' => $this->previousOwner->uuid,
                'name' => $this->previousOwner->name,
                'role' => $this->previousOwner->roles->pluck('name')->first(),
            ],
            'new_owner' => [
                'uuid' => $this->newOwner->uuid,
                'name' => $this->newOwner->name,
                'role' => 'OWNER',
            ],
            'transferred_at' => now()->toIso8601String(),
        ];
    }
}
