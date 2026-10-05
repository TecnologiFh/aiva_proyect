<?php

namespace App\Modules\Identity\Application\AuthenticateUser;

class AuthenticateUserInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $email,
        public readonly string $password
    ) {}
}
