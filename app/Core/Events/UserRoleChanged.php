<?php

namespace App\Core\Events;

use App\Modules\Identity\Models\User;

class UserRoleChanged
{
    public function __construct(
        public readonly User $user,
        public readonly string $oldRole,
        public readonly string $newRole,
        public readonly int $assignedBy
    ) {}
}
