<?php

namespace App\Modules\Identity\Application\CreateEmployee;

class CreateEmployeeInput
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly string $role = 'EMPLOYEE',
        public readonly int $createdById = 0
    ) {}
}
