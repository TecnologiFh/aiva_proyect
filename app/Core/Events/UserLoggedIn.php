<?php

namespace App\Core\Events;

use App\Modules\Identity\Models\User;

class UserLoggedIn
{
    public function __construct(
        public readonly User $user,
        public readonly ?string $ipAddress = null
    ) {}
}
