<?php

namespace App\Core\Events;

use App\Modules\Identity\Models\User;

class UserRegistered
{
    public function __construct(
        public readonly User $user
    ) {}
}
