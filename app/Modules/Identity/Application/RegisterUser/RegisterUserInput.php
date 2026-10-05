<?php

namespace App\Modules\Identity\Application\RegisterUser;

class RegisterUserInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly string $role = 'OWNER'
    ) {}
}
