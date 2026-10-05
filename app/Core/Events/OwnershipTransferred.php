<?php

namespace App\Core\Events;

use App\Modules\Identity\Models\User;

class OwnershipTransferred
{
    public function __construct(
        public readonly int $tenantId,
        public readonly User $previousOwner,
        public readonly User $newOwner
    ) {}
}
