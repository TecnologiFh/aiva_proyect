<?php

namespace App\Core\Events;

use App\Modules\Identity\Models\User;

class UserActivated
{
    public function __construct(
        public readonly User $user,
        public readonly int $activatedBy
    ) {}
}
